<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Plugin\Content\ContentActivityPub\Extension;

use ActivityPhp\Type;
use ActivityPhp\Type\AbstractObject;
use Algo26\IdnaConvert\Exception\AlreadyPunycodeException;
use Algo26\IdnaConvert\Exception\InvalidCharacterException;
use Algo26\IdnaConvert\ToIdn;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\ObjectTable;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\Component\Content\Site\Helper\RouteHelper;
use Joomla\Component\Tags\Site\Helper\RouteHelper as TagsRouteHelper;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use OutOfBoundsException;
use RuntimeException;

trait ContentToActivityObjectTrait
{
	use ImageHandlingTrait;

	private array $urlCache = [];

	/**
	 * Get an Activity object from the raw article data.
	 *
	 * @param   object  $rawData  The raw article data.
	 * @param   User    $user     The user which the Activity is for.
	 *
	 * @return  AbstractObject
	 * @throws  AlreadyPunycodeException
	 * @throws  InvalidCharacterException
	 * @since   2.0.0
	 */
	private function getObjectFromRawContent(object $rawData, User $user): AbstractObject
	{
		$sourceType          = $this->params->get('fulltext', 'introtext');
		$attachImages        = $this->params->get('images', '1') == 1;
		$preferredObjectType = $this->params->get('object_type', 'Note') ?: 'Note';
		$urlBehaviour        = $this->params->get('url', 'both') ?: 'both';

		/**
		 * Find the #__activitypub_objects record for this content. Updates the record if the content got unpublished
		 * without triggering this plugin but DOES NOT send out notifications. This is handled by the event handlers of
		 * this here plugin.
		 */
		$objectTable = $this->getCanonicalObjectTableForContent($user, $rawData);

		// If the item is not published anymore return a Tombstone
		if ($objectTable->status == 0)
		{
			return $this->getTombstone($user, $objectTable);
		}

		// Get the basic information about the article
		$objectId         = $this->getApiUriForUser($user, 'object') . '/' . $objectTable->id;
		$actorUri         = $this->getApiUriForUser($user, 'actor');
		$sourceObjectType = $sourceType === 'metadesc' ? 'Note' : $preferredObjectType;

		$followersUri = $this->getApiUriForUser($user, 'followers');
		$jPublished   = clone Factory::getDate($rawData->publish_up ?: $rawData->created, 'GMT');
		$published    = $jPublished->format(DATE_ATOM);
		$jUpdated     = clone Factory::getDate($rawData->modified ?: $rawData->created, 'GMT');
		$updated      = $jUpdated->format(DATE_ATOM);

		$rawUrl = Route::link(
			client: 'site',
			url: RouteHelper::getArticleRoute($rawData->id, $rawData->catid, $rawData->language),
			xhtml: false,
			absolute: true
		);

		$sourceObject = [
			'inReplyTo'        => null,
			'atomUri'          => $objectId,
			'inReplyToAtomUri' => null,
			'published'        => $published,
			'updated'          => $updated,
			'attributedTo'     => $actorUri,
			'to'               => [
				'https://www.w3.org/ns/activitystreams#Public',
			],
			'cc'               => [
				$followersUri,
			],
			'sensitive'        => false,
			'attachment'       => [],
			'tag'              => [],
		];

		/**
		 * Add the article URL
		 *
		 * Here's a fun one! The ActivityPhp URL validator uses PHP's filter_var which only supports URLs with ASCII
		 * characters. Guess what happens when the URL contains UTF-8 characters? That's right, it throws an error. So,
		 * we have to transliterate the URL.
		 */
		if (in_array($urlBehaviour, ['url', 'both']))
		{
			$sourceObject['url'] = $this->transliterateUrl($rawUrl);
		}

		// Add the article title
		if ($sourceObjectType === 'Article')
		{
			$sourceObject['name'] = $rawData->title;
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

		$language = ($rawData->language === '*' || empty($rawData->language))
			? $this->getApplication()->getLanguage()->getTag()
			: $rawData->language;

		if (str_contains($language, '-'))
		{
			[$language,] = explode('-', $language, 2);
		}

		$content = match ($sourceType)
		{
			'introtext' => $rawData->introtext,
			'fulltext' => $rawData->fulltext,
			'both' => $rawData->introtext . '<hr/>' . $rawData->fulltext,
			'metadesc' => '<p>' . htmlentities($rawData->metadesc) . '</p>'
		};

		try
		{
			$content = HTMLHelper::_('content.prepare', $content);
		}
		catch (Exception $e)
		{
		}

		if (in_array($urlBehaviour, ['link', 'both']))
		{
			$this->addReadMore($content, $rawData);
		}

		$sourceObject['id']         = $objectId;
		$sourceObject['summary']    = (empty($rawData->metadesc) || $sourceObjectType === 'Note') ? null : $rawData->metadesc;
		$sourceObject['content']    = $content;
		$sourceObject['contentMap'] = [
			$language => $content,
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

				if (in_array($urlBehaviour, ['link', 'both']))
				{
					$this->addReadMore($altContent, $rawData, $langCode);
				}

				if (str_contains($langCode, '-'))
				{
					[$langCode,] = explode('-', $langCode, 2);
				}

				$sourceObject['contentMap'][$langCode] = $altContent;
			}
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

		return Type::create($sourceObjectType, $sourceObject);
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

	/**
	 * Adds a "Read more" link to the content.
	 *
	 * @param   string       $content   The HTML content to modify
	 * @param   object       $article   The article object
	 * @param   string|null  $language  The language of the article
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   2.0.0
	 */
	private function addReadMore(string &$content, object $article, ?string $language = null): void
	{
		static $lastLang = 'xx-XX';

		if ($language !== $lastLang)
		{
			$language       ??= (empty($article->language) || $article->language === '*') ? null : $article->language;
			$lastLang       = $language;
			$useAltReadmore = false;

			try
			{
				$jLang = Factory::getApplication()->getLanguage();
				$jLang->load('plg_content_contentactivitypub', JPATH_ADMINISTRATOR, $language, true);
				$jLang->load('com_content', JPATH_SITE, $language, true);
			}
			catch (Exception $e)
			{
				$this->loadLanguage('plg_content_contentactivitypub');

				$useAltReadmore = true;
			}
		}

		$signature = sprintf('%d:%d:%s', $article->id, $article->catid, $article->language);

		$this->urlCache[$signature] ??= Route::link(
			client: 'site',
			url: RouteHelper::getArticleRoute($article->id, $article->catid, $article->language),
			xhtml: false,
			absolute: true
		);

		$content .= sprintf(
			'<p><a href="%s">%s</a></p>',
			$this->urlCache[$signature],
			$useAltReadmore
				? Text::sprintf('PLG_CONTENT_CONTENTACTIVITYPUB_READMORE', Factory::getApplication()->get('sitename'))
				: Text::sprintf('COM_CONTENT_READ_MORE_TITLE', $article->title)
		);
	}

	/**
	 * Get the latest #__activitypub_objects record matching the content ID, actor, and status provided.
	 *
	 * @param   int   $contentId  The article ID
	 * @param   int   $actorId    The actor ID
	 * @param   int   $status     The status we're looking for (1: exists, 0: deleted)
	 * @param   bool  $create     Should I create a missing record?
	 *
	 * @return  ObjectTable
	 * @throws  Exception When an object cannot be created or there was a database error
	 * @since   2.0.0
	 */
	private function getLatestObjectTableForContent(int $contentId, int $actorId, ?int $status = 1, bool $create = true): ObjectTable
	{
		static $objectTable = null;

		/** @var ObjectTable $objectTable */
		$objectTable ??= $this->getApplication()
			->bootComponent('com_activitypub')
			->getMVCFactory()
			->createTable('Object', 'Administrator');

		$contextReference = sprintf('%s.%d', $this->context, $contentId);

		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__activitypub_objects'))
			->where([
				$db->quoteName('actor_id') . ' = :actor_id',
				$db->quoteName('context_reference') . ' = :context_reference',
			])
			->bind(':actor_id', $actorId, ParameterType::INTEGER)
			->bind(':context_reference', $contextReference, ParameterType::STRING)
			->order($db->quoteName('id') . ' DESC');

		if ($status !== null)
		{
			$query->where($db->quoteName('status') . ' = :status')
				->bind(':status', $status, ParameterType::INTEGER);
		}

		$tableData = $db->setQuery($query, 0, 1)->loadAssoc() ?: [];
		$table     = clone $objectTable;
		$table->reset();

		if (!empty($tableData))
		{
			$table->bind($tableData);

			return $table;
		}

		if (!$create)
		{
			throw new OutOfBoundsException('No such object');
		}

		$data = [
			'id'                => (new \DateTime('now', new \DateTimeZone('GMT')))->format('Uv'),
			'actor_id'          => $actorId,
			'context_reference' => $contextReference,
			'status'            => $status,
			'created'           => Factory::getDate()->toSql(),
			'modified'          => null,
		];

		if (!$table->save($data))
		{
			throw new RuntimeException(
				sprintf('Cannot create activity object: %s', $table->getError())
			);
		}

		return $table;
	}

	/**
	 * Get the ObjectTable object for the content, updating an existing record if necessary.
	 *
	 * @param   User    $user     The user (real or virtual) who owns this object
	 * @param   object  $article  The raw article data
	 *
	 * @return  ObjectTable
	 * @throws  Exception
	 * @since   2.0.0
	 */
	private function getCanonicalObjectTableForContent(User $user, object $article): ObjectTable
	{
		$isPublished = $this->isArticlePublished($article);

		// Get the actor table
		$actorTable = $this->getActorRecordForUser($user);

		// Get the latest object for the published state
		try
		{
			$lastPublishedObject = $this->getLatestObjectTableForContent($article->id, $actorTable->id, 1, false);
		}
		catch (Exception $e)
		{
			$lastPublishedObject = null;
		}

		// Get the latest object for the unpublished state
		try
		{
			$lastUnpublishedObject = $this->getLatestObjectTableForContent($article->id, $actorTable->id, 0, false);
		}
		catch (Exception $e)
		{
			$lastUnpublishedObject = null;
		}

		// Handle published articles
		if ($isPublished)
		{
			/**
			 * I will need to create a new object in the following cases:
			 * - There is no object for the published state at all.
			 * - There are objects for the published and unpublished state BUT the unpublished state object is newer. I
			 *   cannot go from unpublished to published as ActivityPub does not let me send a Create activity for an
			 *   object I have already sent a Delete activity for. Therefore, I will have to create a new object, with a
			 *   new ID, so that the Create activity it gets is valid for ActivityPub.
			 *
			 * In all other cases I return the object I loaded from the database
			 */
			if (
				// If there is no last published object: create one now.
				empty($lastPublishedObject)
				// If there is a last published object AND a last unpublished object which is newer: create a new published object
				|| (
					!empty($lastPublishedObject)
					&& !empty($lastUnpublishedObject)
					&& $lastUnpublishedObject->id > $lastPublishedObject->id
				)
			)
			{
				$lastPublishedObject = $this->getLatestObjectTableForContent($article->id, $actorTable->id, 1, true);
			}

			return $lastPublishedObject;
		}

		// Get the best fit for an unpublish date between the modified, publish_down, and created dates
		$nullDate        = $this->getDatabase()->getNullDate();
		$modifiedDate    = $article->modified === $nullDate ? null : $article->modified;
		$publishDownDate = $article->publish_down === $nullDate ? null : $article->publish_down;
		$createdDate     = $article->created === $nullDate ? null : $article->created;
		$bestFitDate     = $modifiedDate ?: $publishDownDate ?: $createdDate ?: Factory::getDate()->toSql();

		// Special consideration: if publish_down is later than modified or created we need to use it instead.
		if (!empty($publishDownDate) && ($publishDownDate !== $nullDate) && ($bestFitDate != $publishDownDate))
		{
			$jBestFit     = Factory::getDate($bestFitDate);
			$jPublishDown = Factory::getDate($publishDownDate);

			if ($jPublishDown > $jBestFit)
			{
				$bestFitDate = $publishDownDate;
			}
		}

		// There is no published or unpublished state: create an unpublished state object
		if (empty($lastPublishedObject) && empty($lastUnpublishedObject))
		{
			$lastUnpublishedObject           = $this->getLatestObjectTableForContent($article->id, $actorTable->id, 0, true);
			$lastUnpublishedObject->modified = $bestFitDate;
			$lastUnpublishedObject->store();

			return $lastUnpublishedObject;
		}

		// There is a last published object but no last unpublished object. Update the last published object.
		if (empty($lastUnpublishedObject) && !empty($lastPublishedObject))
		{
			$lastPublishedObject->save([
				'status'   => 0,
				'modified' => $bestFitDate,
			]);

			return $lastPublishedObject;
		}

		/**
		 * There is a last published and last unpublished object. The unpublished object is older than the published
		 * object. Update the last published object.
		 */
		if (!empty($lastPublishedObject) && !empty($lastPublishedObject) && $lastUnpublishedObject->id < $lastPublishedObject->id)
		{
			$lastPublishedObject->save([
				'status'   => 0,
				'modified' => $bestFitDate,
			]);

			return $lastPublishedObject;
		}

		// Any other case: return the last unpublished object we loaded from the database.
		return $lastUnpublishedObject;
	}

	/**
	 * Return a Tombstone for unpublished or deleted content
	 *
	 * @param   User|null    $user         The user (real or fake) of the actor who owner this content
	 * @param   ObjectTable  $objectTable  The ObjectTable instance
	 *
	 * @return  Type\AbstractObject
	 * @throws  Exception
	 * @since   2.0.0
	 */
	private function getTombstone(?User $user, ObjectTable $objectTable): Type\AbstractObject
	{
		$sourceType          = $this->params->get('fulltext', 'introtext');
		$preferredObjectType = $this->params->get('object_type', 'Note') ?: 'Note';
		$sourceObjectType    = $sourceType === 'metadesc' ? 'Note' : $preferredObjectType;

		return Type::create(
			'Tombstone',
			[
				'formerType' => $sourceObjectType,
				'id'         => $this->getApiUriForUser($user, 'object') . '/' . $objectTable->id,
				'deleted'    => Factory::getDate($objectTable->modified)->format(DATE_ATOM),
			]
		);
	}

	/**
	 * Is an article published?
	 *
	 * @param   object  $article  The raw article data
	 *
	 * @return  bool
	 * @since   2.0.0
	 */
	private function isArticlePublished(object $article): bool
	{
		// Is the content published?
		$isPublished = $article->state == 1;

		if (!empty($article->publish_up) && $article->publish_up != $this->getDatabase()->getNullDate())
		{
			$isPublished = $isPublished && Factory::getDate($article->publish_up) <= Factory::getDate();
		}

		if (!empty($article->publish_down) && $article->publish_down != $this->getDatabase()->getNullDate())
		{
			$isPublished = $isPublished && Factory::getDate($article->publish_down) >= Factory::getDate();
		}

		return $isPublished;
	}

}