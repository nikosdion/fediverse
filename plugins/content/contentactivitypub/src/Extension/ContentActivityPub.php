<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Plugin\Content\ContentActivityPub\Extension;

\defined('_JEXEC') || die;

use ActivityPhp\Type;
use ActivityPhp\Type\AbstractObject;
use ActivityPhp\Type\Core\AbstractActivity;
use ActivityPhp\Type\Extended\Object\Tombstone;
use Algo26\IdnaConvert\Exception\AlreadyPunycodeException;
use Algo26\IdnaConvert\Exception\InvalidCharacterException;
use Algo26\IdnaConvert\ToIdn;
use Dionysopoulos\Component\ActivityPub\Administrator\Event\GetActivity;
use Dionysopoulos\Component\ActivityPub\Administrator\Event\GetActivityListQuery;
use Dionysopoulos\Component\ActivityPub\Administrator\Event\GetObject;
use Dionysopoulos\Component\ActivityPub\Administrator\Mixin\GetActorTrait;
use Dionysopoulos\Component\ActivityPub\Administrator\Model\QueueModel;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\ActorTable;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Image\Image;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Component\Content\Administrator\Model\ArticleModel;
use Joomla\Component\Content\Administrator\Table\ArticleTable;
use Joomla\Component\Content\Site\Helper\RouteHelper;
use Joomla\Component\Tags\Site\Helper\RouteHelper as TagsRouteHelper;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\DatabaseIterator;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use kornrunner\Blurhash\Blurhash;
use RangeException;

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
			'onActivityPubGetObject'            => 'getObject',
			'onContentChangeState'              => 'onContentChangeState',
			'onContentAfterDelete'              => 'onContentAfterDelete',
			'onContentAfterSave'                => 'onContentAfterSave',
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
				$db->quoteName('publish_down'),
				$db->quoteName('modified'),
				$db->quoteName('state'),
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
	 * Get the object given an object identifier.
	 *
	 * @param   GetObject  $event
	 *
	 * @return  void
	 * @throws  AlreadyPunycodeException
	 * @throws  InvalidCharacterException
	 * @since   2.0.0
	 */
	public function getObject(GetObject $event)
	{
		if ($event->getArgument('context') !== $this->context)
		{
			return;
		}

		/** @var ActorTable $actorTable */
		$actorTable = $event->getArgument('actor');
		$id         = (int) $event->getArgument('id');

		/** @var MVCFactory $comContentFactory */
		$comContentFactory = $this->getApplication()
			->bootComponent('com_content')
			->getMVCFactory();
		/** @var ArticleTable $article */
		$article = $comContentFactory->createTable('Article', 'Administrator');

		if (!$article->load($id))
		{
			return;
		}

		$user = empty($actorTable->username)
			? Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($actorTable->user_id)
			: $this->getUserFromUsername($actorTable->username);

		$object = $this->getObjectFromRawContent($article, $user);

		$event->addResult($object);
	}

	/**
	 * Gets triggered when a content item changes state (published, unpublished, trashed).
	 *
	 * We send a "Create" activity on publish and a "Delete" on unpublish.
	 *
	 * @param   Event  $event  The onContentChangeState event
	 *
	 * @return  void
	 * @throws  AlreadyPunycodeException
	 * @throws  InvalidCharacterException
	 * @since   2.0.0
	 */
	public function onContentChangeState(Event $event): void
	{
		/**
		 * @var string $context The context of the state change
		 * @var array  $pks     The primary keys of the content whose state is being changed
		 * @var int    $value   The new publishing state
		 */
		[$context, $pks, $value] = $event->getArguments();

		// We only concern ourselves with core content (articles)
		if ($context !== 'com_content.article')
		{
			return;
		}

		/** @var MVCFactory $comContentFactory */
		$comContentFactory = $this->getApplication()
			->bootComponent('com_content')
			->getMVCFactory();
		/** @var ArticleTable $article */
		$article = $comContentFactory->createTable('Article', 'Administrator');

		foreach ($pks as $id)
		{
			// Get the article
			$article->reset();

			if (!$article->load($id))
			{
				continue;
			}

			// Get the Activity type (we only do Delete and Create, there is no “Unpublish”)
			$type = match ($value)
			{
				0, 2 => 'Delete',
				1 => 'Create',
				default => null
			};

			if (empty($type))
			{
				continue;
			}

			// Find which Actors apply to this content
			try
			{
				$applicableActors = $this->getActorsForContent($article);
			}
			catch (Exception $e)
			{
				$applicableActors = [];
			}

			if (empty($applicableActors))
			{
				continue;
			}

			// For each Actor, get an Activity and notify its followers
			foreach ($applicableActors as $actorTable)
			{
				// Get the activity
				$user = empty($actorTable->username)
					? Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($actorTable->user_id)
					: $this->getUserFromUsername($actorTable->username);

				if ($type === 'Create')
				{
					$activity = $this->getActivityFromRawContent($article, $user);
				}
				else
				{
					$activity = Type::create(
						'Delete',
						[
							'@context' => [
								'https://www.w3.org/ns/activitystreams',
							],
							'actor'    => $this->getApiUriForUser($user, 'actor'),
							'object'   => $this->getApiUriForUser($user, 'object') . '/' . $this->context . '.' . $article->id,
						]
					);
				}

				// Notify followers
				/** @var QueueModel $queueModel */
				$queueModel = $this->getApplication()
					->bootComponent('com_activitypub')
					->getMVCFactory()
					->createModel('Queue', 'Administrator');

				$queueModel->notifyFollowers($actorTable, $activity);
			}
		}
	}

	/**
	 * Gets triggered when a content items is saved.
	 *
	 * We will send a "Create" activity on publish (or new, published content creation), "Delete" on unpublish, or an
	 * "Update" if a published content is edited. The latter is only possible when you have Versions enabled in Joomla.
	 *
	 * N.B.: If Versions are disabled in Joomla we can't tell how an article was modified.
	 *
	 * @param   Event  $event  The onContentAfterSave event
	 *
	 * @return  void
	 * @throws  AlreadyPunycodeException
	 * @throws  InvalidCharacterException
	 *
	 * @since   2.0.0
	 */
	public function onContentAfterSave(Event $event)
	{
		/**
		 * @var string             $context The context of the item being saved
		 * @var Table|ArticleTable $article The table object just saved
		 * @var int|bool           $isNew   Is this a new item?
		 * @var object|array       $data    Raw data sent to the model
		 */
		[$context, $article, $isNew, $data] = $event->getArguments();

		// We only concern ourselves with core content (articles)
		if ($context !== 'com_content.article')
		{
			return;
		}

		// Is the item published?
		$isPublished = $article->state == 1;

		if (!empty($article->publish_up) && $article->publish_up != $this->getDatabase()->getNullDate())
		{
			$isPublished = $isPublished && Factory::getDate($article->publish_up) <= Factory::getDate();
		}

		if (!empty($article->publish_down) && $article->publish_down != $this->getDatabase()->getNullDate())
		{
			$isPublished = $isPublished && Factory::getDate($article->publish_down) >= Factory::getDate();
		}

		// If it's a new item I only care if it's published
		if ($isNew && !$isPublished)
		{
			return;
		}

		// Find which Actors apply to this content
		try
		{
			$applicableActors = $this->getActorsForContent($article);
		}
		catch (Exception $e)
		{
			$applicableActors = [];
		}

		if (empty($applicableActors))
		{
			return;
		}

		/**
		 * Get the article's previous version and determine its publishing state.
		 *
		 * Joomla only fires plugin events AFTER binding the new data to the table. As a result I cannot normally tell
		 * how the article was edited.
		 *
		 * I am going through Joomla's Versions (article version history) to find the previous state of the article and
		 * determine if it was published or not. If the previous versions have been removed OR Versions are disabled in
		 * Joomla I cannot know how the article has changed, period.
		 */
		$previousVersion = $isNew ? null : $this->getPreviousVersion($article);

		if (!empty($previousVersion))
		{
			$wasPublished = $previousVersion->state == 1;

			if (!empty($previousVersion->publish_up) && $previousVersion->publish_up != $this->getDatabase()->getNullDate())
			{
				$wasPublished = $wasPublished && Factory::getDate($previousVersion->publish_up) <= Factory::getDate();
			}

			if (!empty($previousVersion->publish_down) && $previousVersion->publish_down != $this->getDatabase()->getNullDate())
			{
				$wasPublished = $wasPublished && Factory::getDate($previousVersion->publish_down) >= Factory::getDate();
			}
		}

		// For each Actor, get an Activity and notify its followers
		foreach ($applicableActors as $actorTable)
		{
			// Get the activity
			$user = empty($actorTable->username)
				? Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($actorTable->user_id)
				: $this->getUserFromUsername($actorTable->username);

			/**
			 * Get the activity.
			 *
			 * If this is a new article I will always send a Create activity — as I have already checked that it's both
			 * a new article, and published.
			 *
			 * If I was able to get the previous version of the article I have its current and its previous publishing
			 * state. As a result, I can have a detailed approach to which activity to issue:
			 *
			 * - **An unpublished article remained unpublished**. No notification is necessary. I had already sent a
			 *   Delete activity when the article was first unpublished.
			 * - **An unpublished article got published**. Send a Create activity. Handled by getActivityFromRawContent.
			 * - **A published article got unpublished**. Send a Delete activity. Handled by getActivityFromRawContent.
			 * - **A published article remained published**. Send an Update activity.
			 *
			 * If I could not get the previous version of the article I only have its _current_ state — but not its
			 * previous publishing state. As a result I can only send a Create or Delete activity:
			 *
			 * - **The article is now published**. Send a Create activity.
			 * - **The article is now unpublished**. Send a Delete activity.
			 *
			 * If the publishing state has changed saving the article this is not a problem. However, if the publishing
			 * state didn't change I am sending something confusing to the federated server. If an article remained
			 * published I am sending a "Create" activity for content the remote server has already seen me creating. If
			 * the article remained unpublished I am sending a Delete activity for content the remote server has already
			 * seen me delete (if a published item got unpublished in the past) or, worse, it sees me deleting content
			 * which it's never seen me create (if I had created an unpublished item and kept editing and saving it as
			 * an unpublished item). Yeah, this is problematic.
			 *
			 * Ideally, Joomla should give me a copy of the table data _before_ the new data was bound to it. Of course
			 * this requires core developers actually understanding the plethora of use cases where this is necessary,
			 * and I'm not talking just about ActivityPub. Having the before and after state would allow developers to
			 * find the way content changed and enable smart actions. For example, updating just the publish up / down
			 * dates on a content category listing events could be used to update an events calendar — but if these
			 * fields are never changed no such time-consuming change would be necessary. Updating the title or images
			 * of an article could mean that a social sharing card needs to be regenerated — but if none of that is
			 * changed there's no need to waste server resources on this time-consuming process. These are just two
			 * examples of things I have had to do in the recent past. But I digress.
			 */
			$activity = $this->getActivityFromRawContent($article, $user);

			if (!empty($previousVersion))
			{
				/**
				 * An unpublished article remained unpublished.
				 *
				 * No notification is necessary.
				 */
				if (!$isPublished && !$wasPublished)
				{
					return;
				}

				/**
				 * A published article was edited.
				 *
				 * Return an Update activity
				 */
				if ($isPublished && $wasPublished)
				{
					$activity = Type::create(
						'Update',
						[
							'@context' => [
								'https://www.w3.org/ns/activitystreams',
							],
							'actor'    => $activity->actor,
							'object'   => $this->getObjectFromRawContent($article, $user),
						]
					);
				}
			}

			// Notify followers
			/** @var QueueModel $queueModel */
			$queueModel = $this->getApplication()
				->bootComponent('com_activitypub')
				->getMVCFactory()
				->createModel('Queue', 'Administrator');

			$queueModel->notifyFollowers($actorTable, $activity);
		}
	}

	/**
	 * Gets triggered when a content item is deleted.
	 *
	 * If a published item is deleted we send a Delete activity. When an unpublished item gets deleted we do nothing;
	 * we had already sent a Delete activity when the content was unpublished.
	 *
	 * @param   Event  $event
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   2.0.0
	 */
	public function onContentAfterDelete(Event $event)
	{
		/**
		 * @var string             $context The context of the item being saved
		 * @var Table|ArticleTable $article The table object just saved
		 */
		[$context, $article] = $event->getArguments();

		// We only concern ourselves with core content (articles)
		if ($context !== 'com_content.article')
		{
			return;
		}

		// Was the item published before being deleted?
		$isPublished = $article->state == 1;

		if (!empty($article->publish_up) && $article->publish_up != $this->getDatabase()->getNullDate())
		{
			$isPublished = $isPublished && Factory::getDate($article->publish_up) <= Factory::getDate();
		}

		if (!empty($article->publish_down) && $article->publish_down != $this->getDatabase()->getNullDate())
		{
			$isPublished = $isPublished && Factory::getDate($article->publish_down) >= Factory::getDate();
		}

		// Find which Actors apply to this content
		try
		{
			$applicableActors = $this->getActorsForContent($article);
		}
		catch (Exception $e)
		{
			$applicableActors = [];
		}

		if (empty($applicableActors))
		{
			return;
		}

		// For each Actor, get an Activity and notify its followers
		foreach ($applicableActors as $actorTable)
		{
			// Get the activity
			$user = empty($actorTable->username)
				? Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($actorTable->user_id)
				: $this->getUserFromUsername($actorTable->username);

			/** @var Type\Extended\Activity\Delete $activity */
			$activity = Type::create(
				'Delete',
				[
					'@context' => [
						'https://www.w3.org/ns/activitystreams',
					],
					'actor'    => $this->getApiUriForUser($user, 'actor'),
					'object'   => $this->getApiUriForUser($user, 'object') . '/' . $this->context . '.' . $article->id,
				]
			);

			// Notify followers
			/** @var QueueModel $queueModel */
			$queueModel = $this->getApplication()
				->bootComponent('com_activitypub')
				->getMVCFactory()
				->createModel('Queue', 'Administrator');

			$queueModel->notifyFollowers($actorTable, $activity);
		}
	}

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
		$this->loadLanguage('plg_content_contentactivitypub', JPATH_ADMINISTRATOR);
		$this->loadLanguage('com_content', JPATH_SITE);
		$useAltReadmore = false;
		try
		{
			Factory::getApplication()->getLanguage()
				->load('com_content', JPATH_SITE, reload: true);
		}
		catch (Exception $e)
		{
			$useAltReadmore = true;
		}

		$sourceType          = $this->params->get('fulltext', 'introtext');
		$attachImages        = $this->params->get('images', '1') == 1;
		$preferredObjectType = $this->params->get('object_type', 'Note') ?: 'Note';
		$urlBehaviour        = $this->params->get('url', 'both') ?: 'both';

		// Is the item published?
		$isPublished = $rawData->state == 1;

		if (!empty($rawData->publish_up) && $rawData->publish_up != $this->getDatabase()->getNullDate())
		{
			$isPublished = $isPublished && Factory::getDate($rawData->publish_up) <= Factory::getDate();
		}

		if (!empty($rawData->publish_down) && $rawData->publish_down != $this->getDatabase()->getNullDate())
		{
			$isPublished = $isPublished && Factory::getDate($rawData->publish_down) >= Factory::getDate();
		}

		// Get the basic information about the article
		$objectId         = $this->getApiUriForUser($user, 'object') . '/' . $this->context . '.' . $rawData->id;
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

		// If the item is not published anymore return a Tombstone
		if (!$isPublished)
		{
			$sourceObject['formerType'] = $sourceObjectType;
			$sourceObject['deleted']    = Factory::getDate(
				$rawData->publish_down ?: $rawData->modified ?: $rawData->created,
				'GMT'
			)
				->format(DATE_ATOM);

			/** @noinspection PhpIncompatibleReturnTypeInspection */
			return Type::create('Tombstone', $sourceObject);
		}

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
			$content .= sprintf(
				'<p><a href="%s">%s</a></p>',
				$rawUrl,
				$useAltReadmore
					? Text::sprintf('PLG_CONTENT_CONTENTACTIVITYPUB_READMORE', Factory::getApplication()->get('sitename'))
					: Text::sprintf('COM_CONTENT_READ_MORE_TITLE', $rawData->title)
			);
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
					$altContent .= sprintf(
						'<p><a href="%s">%s</a></p>',
						$rawUrl,
						$useAltReadmore
							? Text::sprintf('PLG_CONTENT_CONTENTACTIVITYPUB_READMORE', Factory::getApplication()->get('sitename'))
							: Text::sprintf('COM_CONTENT_READ_MORE_TITLE', $rawData->title)
					);
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
	 * Get an Activity object from the raw article data.
	 *
	 * @param   object  $rawData  The raw article data.
	 * @param   User    $user     The user which the Activity is for.
	 *
	 * @return  AbstractActivity
	 * @throws AlreadyPunycodeException
	 * @throws InvalidCharacterException
	 * @since   2.0.0
	 */
	private function getActivityFromRawContent(object $rawData, User $user): AbstractActivity
	{
		// Get the source object
		$sourceObject = $this->getObjectFromRawContent($rawData, $user);

		// If the object is unpublished, return a "Delete" activity
		if ($sourceObject instanceof Tombstone)
		{
			/** @noinspection PhpIncompatibleReturnTypeInspection */
			return Type::create(
				'Delete',
				[
					'@context' => [
						'https://www.w3.org/ns/activitystreams',
					],
					'actor'    => $this->getApiUriForUser($user, 'actor'),
					'object'   => $this->getApiUriForUser($user, 'object') . '/' . $this->context . '.' . $rawData->id,
				]
			);
		}

		// If the object is published, return a "Create" activity

		/** @noinspection PhpIncompatibleReturnTypeInspection */
		return Type::create(
			'Create',
			[
				'@context'  => [
					'https://www.w3.org/ns/activitystreams',
					[
						"ostatus"          => "http://ostatus.org#",
						"atomUri"          => "ostatus:atomUri",
						"inReplyToAtomUri" => "ostatus:inReplyToAtomUri",
						'sensitive'        => 'as:sensitive',
						'toot'             => 'http://joinmastodon.org/ns#',
						'blurhash'         => 'toot:blurhash',
					],
				],
				'id'        => $this->getApiUriForUser($user, 'object') . '/' . $this->context . '.' . $rawData->id,
				'actor'     => $this->getApiUriForUser($user, 'actor'),
				'published' => (clone Factory::getDate(
					$rawData->publish_up ?: $rawData->created,
					'GMT'
				))
					->format(DATE_ATOM),
				'to'        => [
					'https://www.w3.org/ns/activitystreams#Public',
				],
				'cc'        => [
					$this->getApiUriForUser($user, 'followers'),
				],
				'object'    => $sourceObject,
			]
		);
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

	/**
	 * Get a list of the applicable actors for this piece of content.
	 *
	 * There are two methods we try:
	 *
	 * * **Using the MySQL JSON extensions**. This is lightning fast, but relies on a database feature which might not
	 *   be enabled on most commercial hosts.
	 * * **Using plain old PHP to inspect the JSON**. This is excruciatingly slow, but does not rely on any database
	 *   features Joomla itself does not use.
	 *
	 * Note that either way only actors who have a non-zero follow count are queried for performance reasons.
	 *
	 * @param   ArticleTable  $article  The article to filter for
	 *
	 * @return  ActorTable[]
	 * @throws  Exception
	 * @since   2.0.0
	 */
	private function getActorsForContent(ArticleTable $article): array
	{
		return $this->getActorsForContentUsingMySQLJSON($article)
			?? $this->getActorsForContentUsingPHP($article)
			?? [];
	}

	/**
	 * Get a list of applicable actors using the MySQL JSON extensions.
	 *
	 * This is **very** fast, but it's not always available on commercial hosts.
	 *
	 * Note that only actors who have a non-zero follow count are queried for performance reasons.
	 *
	 * @param   ArticleTable  $article  The article to filter for
	 *
	 * @return  ActorTable[]|null
	 * @throws  Exception
	 * @since   2.0.0
	 */
	private function getActorsForContentUsingMySQLJSON(ArticleTable $article): ?array
	{
		$db = $this->getDatabase();

		$existsQuery = $db->getQuery(true)
			->select('1')
			->from($db->quoteName('#__activitypub_followers', 'f'))
			->where(
				$db->quoteName('f.actor_id') . ' = ' . $db->quoteName('a.id')
			);

		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__activitypub_actors', 'a'))
			->where([
				'EXISTS(' . $existsQuery . ')',
				'JSON_EXTRACT(' . $db->quoteName('params') . ', \'$.content.enable\') = 1',
				'JSON_CONTAINS(' . $db->quoteName('params') . ', \'["' . (int) $article->catid . '"]\', \'$.content.categories\')',
				'JSON_CONTAINS(' . $db->quoteName('params') . ', \'["' . (int) $article->access . '"]\', \'$.content.accesslevel\')',
			]);
		try
		{
			$actors = $db->setQuery($query)->loadObjectList('id');
		}
		catch (Exception $e)
		{
			return null;
		}

		/** @var MVCFactory $mvcFactory */
		$mvcFactory = $this->getApplication()
			->bootComponent('com_activitypub')
			->getMVCFactory();

		return array_map(
			function ($rawActor) use ($mvcFactory) {
				$table = $mvcFactory->createTable('Actor', 'Administrator');
				$table->bind((array) $rawActor);

				return $table;
			},
			$actors
		);
	}

	/**
	 * Get a list of the applicable actors using plain old PHP.
	 *
	 * This is excruciatingly slow. It is only used as a fallback, in case the MySQL JSON extensions are not enabled on
	 * the server.
	 *
	 * Note that only actors who have a non-zero follow count are queried for performance reasons.
	 *
	 * @param   ArticleTable  $article  The article to filter for
	 *
	 * @return  ActorTable[]|null
	 * @throws  Exception
	 * @since   2.0.0
	 */
	private function getActorsForContentUsingPHP(ArticleTable $article): ?array
	{
		$db = $this->getDatabase();

		$existsQuery = $db->getQuery(true)
			->select('1')
			->from($db->quoteName('#__activitypub_followers', 'f'))
			->where(
				$db->quoteName('f.actor_id') . ' = ' . $db->quoteName('a.id')
			);

		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__activitypub_actors', 'a'))
			->where([
				'EXISTS(' . $existsQuery . ')',
			]);

		try
		{
			/** @var DatabaseIterator $iterator */
			$iterator = $db->setQuery($query)->getIterator();
		}
		catch (Exception $e)
		{
			return null;
		}

		if (count($iterator) === 0)
		{
			$iterator = null;

			return [];
		}

		/** @var MVCFactory $mvcFactory */
		$mvcFactory = $this->getApplication()
			->bootComponent('com_activitypub')
			->getMVCFactory();
		$results    = [];

		foreach ($iterator as $actorData)
		{
			$params      = new Registry($actorData->params);
			$categories  = $params->get('content.categories', []);
			$accessLevel = $params->get('content.accesslevel', [1, 5]);

			if ($params->get('content.enable', 1) == 0 || empty($categories) || empty($accessLevel))
			{
				continue;
			}

			if (!in_array($article->catid, $categories) || !in_array($article->access, $accessLevel))
			{
				continue;
			}

			$actorTable = $mvcFactory->createTable('Actor', 'Administrator');
			$actorTable->bind((array) $actorData);
			$results[] = $actorTable;
		}

		return $results;
	}

	/**
	 * Get the previous version of an article
	 *
	 * @param   ArticleTable  $article
	 *
	 * @return  ArticleTable|null The previous version of the article. NULL if there is none, or Versions are disabled.
	 * @throws  Exception
	 * @since   2.0.0
	 */
	private function getPreviousVersion(ArticleTable $article): ?ArticleTable
	{
		/** @var MVCFactory $comContentFactory */
		$comContentFactory = $this->getApplication()
			->bootComponent('com_content')
			->getMVCFactory();
		/** @var ArticleModel $articleModel */
		$articleModel = $comContentFactory->createModel('Article', 'Administrator');

		$version = $article->version;
		$table   = clone $article;

		while ($version > 0)
		{
			try
			{
				$versionId = $this->getArticleVersionIdByVersionCounter($article->id, --$version);
			}
			catch (RangeException $e)
			{
				// Ah, yes, we found out this article has no versions. Get the heck outta here.
				return null;
			}

			if (empty($versionId))
			{
				continue;
			}

			if ($articleModel->loadHistory($versionId, $table))
			{
				return $table;
			}
		}

		return null;
	}

	/**
	 * Try to find the ACTUAL version ID for an article given the useless version counter Joomla stores.
	 *
	 * Joomla stores a version _counter_ in the `#__content` table. This is basically useless. The
	 * \Joomla\CMS\Versioning\VersionableModelTrait::loadHistory() method needs the version_id key in the `#__history`
	 * table which has sod all to do with the version counter. The version_counter is stored in the `version_data` of
	 * the `#__history` table... inside a JSON document. We have to query the `#__history` table to find the actual
	 * `version_id`.
	 *
	 * I try some shortcuts to make sure this does not take forever and a bazillion queries. We MIGHT miss a version,
	 * but frankly that's a small risk I am willing to take.
	 *
	 * @param   int  $articleId
	 * @param   int  $versionCounter
	 *
	 * @return  int|null
	 * @since   2.0.0
	 */
	private function getArticleVersionIdByVersionCounter(int $articleId, int $versionCounter): ?int
	{
		$itemId = sprintf('com_content.article.%d', $articleId);
		$db     = $this->getDatabase();

		// Try to get the exact record very fast using a JSON database query
		$query = $db->getQuery($db)
			->select($db->quoteName('version_id'))
			->from($db->quoteName('#__history'))
			->where([
				$db->quoteName('item_id') . ' = :itemId',
				'JSON_EXTRACT(' . $db->quoteName('version_data') . ', \'$.version\') = :counter',
			])
			->bind(':itemId', $itemId)
			->bind(':counter', $versionCounter);

		try
		{
			return $db->setQuery($query)->loadResult() ?: null;
		}
		catch (Exception $e)
		{
			// Fall through to the next attempt.
		}

		// Just try to get the past 10 versions and figure out which one to use.
		$query = $db->getQuery($db)
			->select([
				$db->quoteName('version_id'),
				$db->quoteName('version_data'),
			])
			->from($db->quoteName('#__history'))
			->where([
				$db->quoteName('item_id') . ' = :itemId',
			])
			->bind(':itemId', $itemId)
			->order($db->quoteName('version_id') . ' DESC');

		try
		{
			$objectsList = $db->setQuery($query, 0, 10)->loadObjectList() ?: [];
		}
		catch (Exception $e)
		{
			$objectsList = [];
		}

		if (empty($objectsList))
		{
			// In this case I need to communicate that you wil never find ANY version whatsoever. Throw an exception!
			throw new RangeException('This article has no versions, LOL!');
		}

		/**
		 * Okay, listen. I am trying to find the previous version of an article, right? So look for any version counter
		 * which is less than or equal to what I am looking for, so I can save some time.
		 */
		foreach ($objectsList as $item)
		{
			$params = json_decode($item->version_data);

			if ($params?->version <= $versionCounter)
			{
				return $item->version_id;
			}
		}

		// If we didn't find what we're looking for in the past 10 versions we'll never find it. Bye-bye!
		throw new RangeException('This article has no versions, LOL!');
	}
}