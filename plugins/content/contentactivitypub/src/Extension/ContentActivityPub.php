<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Joomla\Plugin\Content\ContentActivityPub\Extension;

\defined('_JEXEC') || die;

use ActivityPhp\Type;
use ActivityPhp\Type\Extended\Activity\Create;
use Algo26\IdnaConvert\ToIdn;
use Dionysopoulos\Component\ActivityPub\Administrator\Event\GetActivity;
use Dionysopoulos\Component\ActivityPub\Administrator\Event\GetActivityListQuery;
use Dionysopoulos\Component\ActivityPub\Administrator\Mixin\GetActorTrait;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\ActorTable;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\User\User;
use Joomla\Component\Content\Site\Helper\RouteHelper;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseDriver;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use Joomla\Utilities\ArrayHelper;

class ContentActivityPub extends CMSPlugin implements SubscriberInterface, DatabaseAwareInterface
{
	use DatabaseAwareTrait;
	use GetActorTrait;

	protected string $context = 'com_content.article';

	public static function getSubscribedEvents(): array
	{
		return [
			'onActivityPubGetActivityListQuery' => 'getListQuery',
			'onActivityPubGetActivity'          => 'getActivities',
		];
	}

	public function getListQuery(GetActivityListQuery $event): void
	{
		/** @var ActorTable $actor */
		$actor  = $event->getArgument('actor');
		$params = new Registry($actor->params);

		if ($params->get('content.enable', 1) != 1)
		{
			return;
		}

		$userId      = $actor->user_id;
		$categories  = $params->get('content.categories', []);
		$categories  = is_array($categories) ? $categories : [];
		$accessLevel = $params->get('content.accesslevel', [1, 5]);
		$accessLevel = is_array($accessLevel) ? $accessLevel : [1, 5];

		/** @var DatabaseDriver $db */
		$db = $this->getDatabase();
		// Note: can't use prepared statements in these queries to avoid naming clashes across multiple plugins.
		$query = $db->getQuery(true)
			->select(
				[
					$db->quoteName('id', 'id'),
					$db->quoteName('created', 'timestamp'),
					$db->quote($this->context) . ' AS ' . $db->quoteName('context'),
				]
			)
			->from($db->quoteName('#__content'))
			->where([
				$db->quoteName('state') . ' = ' . $db->quote('1'),
				$db->quoteName('publish_up') . ' < NOW()',
			])
			->extendWhere(
				'AND',
				[
					$db->quoteName('publish_down') . ' IS NULL',
					$db->quoteName('publish_down') . ' > NOW()',
				],
				'OR'
			);

		if ($userId)
		{
			$query->where($db->quoteName('created_by') . ' = ' . $db->quote((int) $userId));
		}

		if (!empty($categories))
		{
			$categories = array_map([$db, 'quote'], ArrayHelper::toInteger($categories));
			$query->where($db->quoteName('catid') . ' IN (' . implode(',', $categories) . ')');
		}

		if (!empty($accessLevel))
		{
			$accessLevel = array_map([$db, 'quote'], ArrayHelper::toInteger($accessLevel));
			$query->where($db->quoteName('access') . ' IN (' . implode(',', $accessLevel) . ')');
		}

		$event->addResult($query);
	}

	public function getActivities(GetActivity $event): void
	{
		/** @var ActorTable $actor */
		$actor = $event->getArgument('actor');
		/** @var string $context */
		$context = $event->getArgument('context');
		/** @var array $ids */
		$ids = $event->getArgument('ids');

		if ($context != $this->context)
		{
			return;
		}

		$sourceType   = $this->params->get('fulltext', 'introtext');
		$attachImages = $this->params->get('images', '1') == 1;

		/** @var DatabaseDriver $db */
		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('id'),
				$db->quoteName('title'),
				$db->quoteName('alias'),
				$db->quoteName('catid'),
				$db->quoteName('created'),
				$db->quoteName('publish_up'),
				$db->quoteName('metadesc'),
				$db->quoteName('language'),
			])
			->from($db->quoteName('#__content'))
			->whereIn($db->quoteName('id'), $ids);

		switch ($sourceType)
		{
			case 'introtext':
				$query->select($db->quoteName('introtext'));
				break;

			case 'fulltext':
				$query->select($db->quoteName('fulltext'));
				break;

			case 'both':
				$query->select([
					$db->quoteName('introtext'),
					$db->quoteName('fulltext'),
				]);
				break;

		}

		if ($attachImages)
		{
			$query->select($db->quoteName('images'));
		}

		try
		{
			$items = $db->setQuery($query)->loadObjectList('id') ?: [];
		}
		catch (\Exception $e)
		{
			return;
		}

		$results = [];

		if ($actor->user_id > 0)
		{
			$user = Factory::getUser($actor->user_id);
		}
		else
		{
			$user           = new User();
			$user->username = $actor->username;
			$user->name     = $actor->name;
		}

		foreach ($items as $id => $rawData)
		{
			$results[$this->context . '.' . $id] = $this->getActivityFromRawContent($rawData, $user);
		}

		$event->addResult($results);
	}

	private function getActivityFromRawContent(object $rawData, User $user): Create
	{
		$sourceType   = $this->params->get('fulltext', 'introtext');
		$attachImages = $this->params->get('images', '1') == 1;

		$activityId   = $this->getApiUriForUser($user, 'activity') . '/' . $this->context . '.' . $rawData->id;
		$actorUri     = $this->getApiUriForUser($user, 'actor');
		$followersUri = $this->getApiUriForUser($user, 'followers');
		$jPublished   = clone Factory::getDate($rawData->publish_up ?: $rawData->created, 'GMT');
		$published    = $jPublished->format(DATE_ATOM);
		$url          = Route::link(
			client: 'site',
			url: RouteHelper::getArticleRoute($rawData->id, $rawData->catid, $rawData->language),
			xhtml: false,
			absolute: true
		);
		$language     = ($rawData->language === '*' || empty($rawData->language))
			? $this->getApplication()->getLanguage()->getTag()
			: $rawData->language;
		$content      = match ($sourceType)
		{
			'introtext' => $rawData->introtext,
			'fulltext' => $rawData->fulltext,
			'both' => $rawData->introtext . '<hr/>' . $rawData->fulltext,
			'metadesc' => $rawData->metadesc
		};

		/**
		 * Here's a fun one! The ActivityPhp URL validator uses PHP's filter_var which only supports URLs with ASCII
		 * characters. Guess what happens when the URL contains UTF-8 characters? That's right, it throws an error. So,
		 * we have to transliterate the URL.
		 */
		$url = $this->transliterateUrl($url);

		$sourceObjectType = $sourceType === 'metadesc' ? 'Note' : 'Article';
		$sourceObject     = [
			'id'               => $activityId,
			'type'             => $sourceObjectType,
			'summary'          => (empty($rawData->metadesc) || $sourceObjectType === 'Note') ? null : $rawData->metadesc,
			'inReplyTo'        => null,
			'atomUri'          => $activityId,
			'inReplyToAtomUri' => null,
			'published'        => $published,
			'url'              => $url,
			'attributedTo'     => $actorUri,
			'to'               => [
				'https://www.w3.org/ns/activitystreams#Public',
			],
			'cc'               => [
				$followersUri,
			],
			'sensitive'        => false,
			'content'          => $content,
			'contentMap'       => [
				$language => $content,
			],
		];

		if ($rawData->language !== '*')
		{
			foreach ($this->getAssociatedContent($rawData->id) as $langCode => $assocRawData)
			{
				$sourceObject['contentMap'][$langCode] = match ($sourceType)
				{
					'introtext' => $assocRawData->introtext,
					'fulltext' => $assocRawData->fulltext,
					'both' => $assocRawData->introtext . '<hr/>' . $assocRawData->fulltext,
					'metadesc' => $assocRawData->metadesc
				};
			}
		}

		if ($sourceObjectType === 'Article')
		{
			$sourceObject['name'] = $rawData->title;
		}

		if ($attachImages)
		{
			// TODO Images as attachments
		}

		$attributes = [
			'id'        => $activityId,
			'actor'     => $actorUri,
			'published' => $published,
			'to'        => [
				'https://www.w3.org/ns/activitystreams#Public',
			],
			'cc'        => [
				$followersUri,
			],
			'object'    => $sourceObject,
		];

		/** @noinspection PhpIncompatibleReturnTypeInspection */
		return Type::create('Create', $attributes);
	}

	private function getAssociatedContent(int $articleId): array
	{
		/** @var DatabaseDriver $db */
		$db = $this->getDatabase();

		$existsQuery = $db->getQuery(true)
			->select(1)
			->from($db->quoteName('#__associations', 'a2'))
			->where([
				$db->quoteName('a2.context') . ' = ' . $db->quote('com_content.item'),
				$db->quoteName('a2.id') . ' = ' . (int) $articleId,
				$db->quoteName('a1.id') . ' != ' . (int) $articleId,
				$db->quoteName('a1.key') . ' = ' . $db->quoteName('a2.key'),
			]);

		$innerQuery = $db->getQuery(true)
			->select($db->quoteName('id'))
			->from($db->quoteName('#__associations', 'a1'))
			->where('EXISTS(' . $existsQuery . ')');

		$query = $db->getQuery(true)
			->select([
				$db->quoteName('id'),
				$db->quoteName('introtext'),
				$db->quoteName('fulltext'),
				$db->quoteName('metadesc'),
				$db->quoteName('language'),
			])
			->from($db->quoteName('#__content'))
			->where([
				$db->quoteName('id') . ' IN(' . $innerQuery . ')',
				$db->quoteName('language') . ' != ' . $db->quote('*'),
			]);

		return $db->setQuery($query)->loadObjectList('language') ?: [];
	}

	/**
	 * Converts a UTF-8 URL into its IDN- and URL-encoded representation.
	 *
	 * @param   string  $url
	 *
	 * @return  string
	 * @throws  \Algo26\IdnaConvert\Exception\AlreadyPunycodeException
	 * @throws  \Algo26\IdnaConvert\Exception\InvalidCharacterException
	 * @since   2.0.0
	 * @see     https://en.wikipedia.org/wiki/Internationalized_domain_name for IDN encoding of hostnames
	 * @see     https://en.wikipedia.org/wiki/Percent-encoding for URL encoding
	 */
	private function transliterateUrl(string $url): string
	{
		if (filter_var($url, FILTER_VALIDATE_URL))
		{
			return $url;
		}

		$uri = new Uri($url);

		// Transliterate the host using IDNA
		$uri->setHost((new ToIdn())->convert($uri->getHost()));

		// Transliterate the path
		$uri->setPath(implode('/', array_map('urlencode', explode('/', $uri->getPath()))));

		// Transliterate any query string parameters
		$vars = $uri->getQuery(true);
		$vars = array_combine(
			array_map('urlencode', array_keys($vars)),
			array_map('urlencode', array_values($vars))
		);
		$uri->setQuery($vars);

		// Return the transliterated whole
		return $uri->toString();
	}
}