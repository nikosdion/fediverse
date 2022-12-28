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
use Dionysopoulos\Component\ActivityPub\Administrator\Event\GetActivity;
use Dionysopoulos\Component\ActivityPub\Administrator\Event\GetActivityListQuery;
use Dionysopoulos\Component\ActivityPub\Administrator\Mixin\GetActorTrait;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\ActorTable;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\User\User;
use Joomla\Component\Content\Site\Helper\RouteHelper;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseDriver;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
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
		$jPublished   = new Date($rawData->publish_up ?: $rawData->created, 'GMT');
		$published    = $jPublished->format(DATE_ATOM);
		$url          = RouteHelper::getArticleRoute($rawData->id, $rawData->catid, $rawData->language);
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

		$sourceType   = $sourceType === 'metadesc' ? 'Note' : 'Article';
		$sourceObject = [
			'id'               => $activityId,
			'type'             => $sourceType,
			'summary'          => (empty($rawData->metadesc) || $sourceType === 'Note') ? null : $rawData->metadesc,
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

		if ($sourceType === 'Article')
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
}