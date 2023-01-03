<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Plugin\Content\ContentActivityPub\Extension;

\defined('_JEXEC') || die;

use ActivityPhp\Type;
use ActivityPhp\Type\Core\AbstractActivity;
use Algo26\IdnaConvert\ToIdn;
use Dionysopoulos\Component\ActivityPub\Administrator\Event\GetActivity;
use Dionysopoulos\Component\ActivityPub\Administrator\Event\GetActivityListQuery;
use Dionysopoulos\Component\ActivityPub\Administrator\Mixin\GetActorTrait;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\ActorTable;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Image\Image;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\Component\Content\Site\Helper\RouteHelper;
use Joomla\Component\Tags\Site\Helper\RouteHelper as TagsRouteHelper;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseDriver;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use kornrunner\Blurhash\Blurhash;

class ContentActivityPub extends CMSPlugin implements SubscriberInterface, DatabaseAwareInterface
{
	use DatabaseAwareTrait;
	use GetActorTrait;

	/**
	 * Maximum pixels in the largest image dimension to sample for BlurHash.
	 *
	 * Smaller values are faster but the BlurHash is less accurate. Higher values are more accurate but far slower AND
	 * use a lot of memory. Values between 32 and 128 work best, based on a subjective trial against a few dozen photos
	 * and illustrations I had at hand.
	 *
	 * @since  2.0.0
	 */
	private const MAX_HASH_PIXELS = 64;

	/**
	 * Cache of BlurHash keyed by image location, to speed things up a smidge.
	 *
	 * @var    array
	 * @since  2.0.0
	 */
	private static array $blurHashCache = [];

	/**
	 * The context of the Activity.
	 *
	 * This has no meaning in Joomla. It's only used to key ActivityPub items.
	 *
	 * @var    string
	 * @since  2.0.0
	 */
	protected string $context = 'com_content.article';

	/**
	 * Returns an array of events this subscriber will listen to.
	 *
	 * @return  array
	 *
	 * @since   2.0.0
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onActivityPubGetActivityListQuery' => 'getListQuery',
			'onActivityPubGetActivity'          => 'getActivities',
		];
	}

	/**
	 * Get the list query for the compact activity data (id, context, timestamp) for an Actor.
	 *
	 * @param   GetActivityListQuery  $event
	 *
	 * @return  void
	 * @since   2.0.0.
	 */
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
		$languages   = $params->get('content.language', []);
		$languages   = is_array($languages) ? $languages : [1, 5];
		$languages   = empty($languages) ? $languages : array_merge(['*'], $languages);

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

		if (!empty($languages))
		{
			$languages = array_map([$db, 'quote'], $languages);
			$query->where($db->quoteName('language') . ' IN(' . implode(',', $languages) . ')');
		}

		$event->addResult($query);
	}

	/**
	 * Get the full Activity objects given a list of IDs, a context, and an Actor.
	 *
	 * @param   GetActivity  $event
	 *
	 * @return  void
	 * @since   2.0.0
	 */
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
		catch (Exception $e)
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

	/**
	 * Get an Activity object from the raw article data.
	 *
	 * @param   object  $rawData  The raw article data.
	 * @param   User    $user     The user which the Activity is for.
	 *
	 * @return  AbstractActivity
	 * @since   2.0.0
	 */
	private function getActivityFromRawContent(object $rawData, User $user): AbstractActivity
	{
		$sourceType   = $this->params->get('fulltext', 'introtext');
		$attachImages = $this->params->get('images', '1') == 1;

		$objectId     = $this->getApiUriForUser($user, 'object') . '/' . $this->context . '.' . $rawData->id;
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

		try
		{
			$content = HTMLHelper::_('content.prepare', $content);
		}
		catch (Exception $e)
		{
		}

		/**
		 * Here's a fun one! The ActivityPhp URL validator uses PHP's filter_var which only supports URLs with ASCII
		 * characters. Guess what happens when the URL contains UTF-8 characters? That's right, it throws an error. So,
		 * we have to transliterate the URL.
		 */
		$url = $this->transliterateUrl($url);

		$sourceObjectType = $sourceType === 'metadesc' ? 'Note' : 'Article';
		$sourceObject     = [
			'id'               => $objectId,
			'type'             => $sourceObjectType,
			'summary'          => (empty($rawData->metadesc) || $sourceObjectType === 'Note') ? null : $rawData->metadesc,
			'inReplyTo'        => null,
			'atomUri'          => $objectId,
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
			'attachment'       => [],
			'tag'              => [],
		];

		// Get associated languages
		if ($rawData->language !== '*')
		{
			foreach ($this->getAssociatedContent($rawData->id) as $langCode => $assocRawData)
			{
				$altContent = match ($sourceType)
				{
					'introtext' => $assocRawData->introtext,
					'fulltext' => $assocRawData->fulltext,
					'both' => $assocRawData->introtext . '<hr/>' . $assocRawData->fulltext,
					'metadesc' => $assocRawData->metadesc
				};

				try
				{
					$altContent = HTMLHelper::_('content.prepare', $altContent);
				}
				catch (Exception $e)
				{
				}

				$sourceObject['contentMap'][$langCode] = $altContent;
			}
		}

		// Add the article title
		if ($sourceObjectType === 'Article')
		{
			$sourceObject['name'] = $rawData->title;
		}

		// Attach images
		if ($attachImages)
		{
			foreach ($this->getImageAttachments($rawData->images, $sourceType) as $attachment)
			{
				if (empty($attachment))
				{
					continue;
				}

				$sourceObject['attachment'][] = $attachment;
			}
		}

		// Add tags as hashtags
		foreach ($this->getTags($rawData->id) as $tag)
		{
			if (empty($tag))
			{
				continue;
			}

			$sourceObject['tag'][] = $tag;
		}

		// Create the activity
		$attributes = [
			'id'        => $objectId,
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

	/**
	 * Returns the content in different, associated languages.
	 *
	 * This only works if the original article has a language other than “All” (*) and you have set up Associations in
	 * Joomla between it and its translations in other languages.
	 *
	 * Please note that this does not use core Joomla code. Joomla's Associations helpers only return URLs, not the
	 * actual content. Moreover, Joomla is using extremely inefficient database queries with multiple JOINs. We use
	 * WHERE clauses with the EXISTS keywords to let MySQL 8.0.15 and later perform "half-joins" which are far more
	 * efficient.
	 *
	 * @param   int  $articleId  The ID of the original article
	 *
	 * @return  array  Associative array with languages as keys and the corresponding content as values.
	 * @since   2.0.0
	 */
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

	/**
	 * Attaches images to the source object
	 *
	 * @param   string  $imagesSource  The JSON-encoded information about the article's images
	 * @param   string  $sourceType    Where does the Activity get the source of its content?
	 *
	 * @return  array
	 * @since   2.0.0
	 */
	private function getImageAttachments(string $imagesSource, string $sourceType): array
	{
		$ret           = [];
		$params        = new Registry($imagesSource);
		$introImage    = $params->get('image_intro');
		$introAlt      = $params->get('image_intro_alt');
		$fulltextImage = $params->get('image_filltext');
		$fulltextAlt   = $params->get('image_filltext_alt');

		if ($sourceType !== 'fulltext' && !empty($introImage))
		{
			$ret[] = $this->getImageAttachment($introImage, $introAlt);
		}

		if ($sourceType !== 'introtext' && !empty($fulltextImage))
		{
			$ret[] = $this->getImageAttachment($fulltextImage, $fulltextAlt);
		}

		return $ret;
	}

	/**
	 * Get an array representing an Image object given some image data.
	 *
	 * @param   string|null  $imageSource  The Joomla image source.
	 * @param   string|null  $altText      The alt text of the image.
	 *
	 * @return  array|null  The Image object; NULL if the image cannot be processed
	 * @throws  Exception
	 * @since   2.0.0
	 */
	private function getImageAttachment(?string $imageSource, ?string $altText): ?array
	{
		// No image?
		if (empty($imageSource))
		{
			return null;
		}

		// Invalid image?
		$info = HTMLHelper::cleanImageURL($imageSource);

		try
		{
			$props = Image::getImageFileProperties($info->url);
		}
		catch (Exception $e)
		{
			$props = null;
		}

		try
		{
			$props = $props ?? Image::getImageFileProperties(JPATH_ROOT . '/' . ltrim($info->url, '/'));
		}
		catch (Exception $e)
		{
			return null;
		}

		$url = str_starts_with($info->url, 'http://') || str_starts_with($info->url, 'https://')
			? $info->url
			: ($this->getFrontendBasePath() . '/' . ltrim($info->url, '/'));

		return [
			'type'      => 'Image',
			'mediaType' => $props->mime,
			'url'       => $url,
			'name'      => $altText ?? '',
			'blurhash'  => $this->getBlurHash($info->url),
			'width'     => $info->attributes['width'] ?? 0,
			'height'    => $info->attributes['height'] ?? 0,
		];
	}

	/**
	 * Calculates the BlurHash of an image file
	 *
	 * @param   string  $file  The URL or path to the file
	 *
	 * @return  string  The BlurHash; empty string if it cannot be calculated.
	 * @since   2.0.0
	 */
	private function getBlurHash(string $file): string
	{
		$key = md5($file);

		if (isset(self::$blurHashCache[$key]))
		{
			return self::$blurHashCache[$key];
		}

		$path = str_starts_with($file, 'http://') || str_starts_with($file, 'https://')
			? $file
			: JPATH_ROOT . '/' . ltrim($file, '/');

		if (
			!function_exists('imagecreatefromstring')
			|| !function_exists('imagesx')
			|| !function_exists('imagesy')
			|| !function_exists('imagecolorat')
			|| !function_exists('imagecolorsforindex')
			|| !function_exists('imagedestroy')
		)
		{
			return self::$blurHashCache[$key] = '';
		}

		$imageContents = file_get_contents($path);

		if ($imageContents === false)
		{
			return self::$blurHashCache[$key] = '';
		}

		$image = imagecreatefromstring($imageContents);

		if ($image === false)
		{
			return self::$blurHashCache[$key] = '';
		}

		$width  = imagesx($image);
		$height = imagesy($image);
		$pixels = [];

		$aspectRatio = $width / $height;

		if ($aspectRatio >= 1)
		{
			$maxWidth  = self::MAX_HASH_PIXELS;
			$maxHeight = floor(self::MAX_HASH_PIXELS / $aspectRatio);
		}
		else
		{
			$maxWidth  = floor(self::MAX_HASH_PIXELS * $aspectRatio);
			$maxHeight = self::MAX_HASH_PIXELS;
		}

		$stepsX = floor($width / $maxWidth);
		$stepsY = floor($height / $maxHeight);

		for ($y = 0; $y < $height; $y += $stepsY)
		{
			$row = [];

			for ($x = 0; $x < $width; $x += $stepsX)
			{
				$index  = imagecolorat($image, $x, $y);
				$colors = imagecolorsforindex($image, $index);

				$row[] = [$colors['red'], $colors['green'], $colors['blue']];
			}

			$pixels[] = $row;
		}

		imagedestroy($image);

		$components_x = 4;
		$components_y = 3;

		return self::$blurHashCache[$key] = Blurhash::encode($pixels, $components_x, $components_y);
	}

	/**
	 * Get the Joomla tags as Hashtag objects (as supported by Mastodon)
	 *
	 * @param   int  $id  The article ID
	 *
	 * @return  array  Array of associative arrays, each inner array is a Hashtag
	 * @throws  Exception
	 * @since   2.0.0
	 */
	private function getTags(int $id)
	{
		/** @var DatabaseDriver $db */
		$db = $this->getDatabase();

		$whereQuery = $db->getQuery(true)
			->select([
				$db->quoteName('m.tag_id'),
			])
			->from($db->quoteName('#__contentitem_tag_map', 'm'))
			->where([
				$db->quoteName('m.content_item_id') . ' = ' . $id,
				$db->quoteName('m.type_alias') . '=' . $db->quote('com_content.article'),
			]);

		$query = $db->getQuery(true)
			->select([
				$db->quoteName('id'),
				$db->quoteName('title'),
				$db->quoteName('language'),
			])->from($db->quoteName('#__tags', 't'))
			->where($db->quoteName('published') . ' = 1')
			->extendWhere('AND', [
				$db->quoteName('t.publish_up') . ' IS NULL',
				$db->quoteName('t.publish_up') . ' < NOW()',
			], 'OR')
			->extendWhere('AND', [
				$db->quoteName('t.publish_down') . ' IS NULL',
				$db->quoteName('t.publish_down') . ' > NOW()',
			], 'OR')
			->where($db->quoteName('id') . 'IN(' . $whereQuery . ')');

		return array_map(
			function ($info): ?array {
				return [
					'type' => 'Hashtag',
					'href' => Route::link(
						'site',
						TagsRouteHelper::getComponentTagRoute($info->id, $info->language),
						false,
						absolute: true
					),
					'name' => '#' . $info->title,
				];
			},
			$db->setQuery($query)->loadObjectList() ?: []
		);
	}
}