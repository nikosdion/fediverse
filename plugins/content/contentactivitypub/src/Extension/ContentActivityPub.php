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
use ActivityPhp\Type\Extended\Activity\Delete;
use ActivityPhp\Type\Extended\Object\Tombstone;
use Algo26\IdnaConvert\Exception\AlreadyPunycodeException;
use Algo26\IdnaConvert\Exception\InvalidCharacterException;
use Dionysopoulos\Component\ActivityPub\Administrator\Event\GetObject;
use Dionysopoulos\Component\ActivityPub\Administrator\Mixin\GetActorTrait;
use Dionysopoulos\Component\ActivityPub\Administrator\Model\QueueModel;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\ActorTable;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\ObjectTable;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Table\Table;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Component\Content\Administrator\Table\ArticleTable;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

class ContentActivityPub extends CMSPlugin implements SubscriberInterface, DatabaseAwareInterface
{
	use DatabaseAwareTrait;
	use GetActorTrait;
	use ContentToActivityObjectTrait;
	use ContentToActorTrait;

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
			'onActivityPubGetObject' => 'getObject',
			'onContentChangeState'   => 'onContentChangeState',
			'onContentAfterDelete'   => 'onContentAfterDelete',
			'onContentAfterSave'     => 'onContentAfterSave',
		];
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
		$requestedContext = $event->getArgument('context');

		if ($requestedContext !== $this->context)
		{
			return;
		}

		/** @var ActorTable $actorTable */
		$actorTable = $event->getArgument('actor');
		$id         = $event->getArgument('id');

		/** @var MVCFactory $comActivityPubFactory */
		$comActivityPubFactory = $this->getApplication()
			->bootComponent('com_activitypub')
			->getMVCFactory();

		/** @var ObjectTable $objectTable */
		$objectTable = $comActivityPubFactory->createTable('Object', 'Administrator');

		if (!$objectTable->load($id))
		{
			// Object ID not found; will result in a 404
			return;
		}

		$user = empty($actorTable->username)
			? Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($actorTable->user_id)
			: $this->getUserFromUsername($actorTable->username);

		if ($objectTable->status == 0)
		{
			$event->addResult($this->getTombstone($user, $objectTable));

			return;
		}

		/** @var MVCFactory $comContentFactory */
		$comContentFactory = $this->getApplication()
			->bootComponent('com_content')
			->getMVCFactory();
		/** @var ArticleTable $article */
		$article = $comContentFactory->createTable('Article', 'Administrator');

		$contextReference = $objectTable->context_reference;
		$parts = explode('.', $contextReference, 3);

		if (count($parts) !== 3)
		{
			return;
		}

		$context = $parts[0] . '.' . $parts[1];

		if ($context !== $requestedContext)
		{
			return;
		}

		$articleId = (int) $parts[2];

		if (!$article->load($articleId))
		{
			// Someone deleted the article in a way that did not trigger this plugin. First, mark the object deleted.
			$objectTable->save([
				'status'   => 0,
				'modified' => Factory::getDate()->toSql(),
			]);

			// Then indicate we need to send a Delete notification to followers.
			$mustSendDeleteNotification = true;

			// Finally, return a Tombstone
			$event->addResult($this->getTombstone($user, $objectTable));
		}
		else
		{
			$object = $this->getObjectFromRawContent($article, $user);

			/**
			 * If I got a Tombstone for an object which was ostensibly still published it means that the object actually
			 * got deleted in a way that did not trigger this plugin. Therefore, I need to send a Delete ActivityPub
			 * notification to followers.
			 */
			$mustSendDeleteNotification = $object instanceof Tombstone && $objectTable->status == 1;

			$event->addResult($object);
		}

		// Send a Delete notification to followers if I need to.
		if ($mustSendDeleteNotification)
		{
			$activity = Type::create(
				'Delete',
				[
					'@context' => [
						'https://www.w3.org/ns/activitystreams',
					],
					'actor'    => $this->getApiUriForUser($user, 'actor'),
					'object'   => $this->getApiUriForUser($user, 'object') . '/' . $objectTable->id,
				]
			);

			// Notify followers
			/** @var QueueModel $queueModel */
			$queueModel = $this->getApplication()
				->bootComponent('com_activitypub')
				->getMVCFactory()
				->createModel('Queue', 'Administrator');

			$queueModel->addToOutboxAndNotifyFollowers($actorTable, $activity);
		}
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
			$type = ($value == 1) ? 'Create' : 'Delete';

			// Find which Actors apply to this content
			try
			{
				$applicableActors = $this->getActorsForContent($article) ?? [];
			}
			catch (Exception $e)
			{
				continue;
			}

			// For each Actor, get an Activity and notify its followers
			foreach ($applicableActors as $actorTable)
			{
				// Get the latest stored ObjectTable for this content and user
				try
				{
					$objectTable = $this->getLatestObjectTableForContent($id, $actorTable->id, null, false);
				}
				catch (Exception $e)
				{
					$objectTable = null;
				}

				/**
				 * If the latest object already has the "new" publishing state we don't have to update anything!
				 */
				if (!empty($objectTable) && $objectTable->status == ($this->isArticlePublished($article) ? 1 : 0))
				{
					continue;
				}

				$user = empty($actorTable->username)
					? Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($actorTable->user_id)
					: $this->getUserFromUsername($actorTable->username);

				if ($type == 'Delete')
				{
					// Update the ObjectTable, marking the object as deleted
					$this->getCanonicalObjectTableForContent($user, $article);

					$activity = Type::create(
						'Delete',
						[
							'@context' => [
								'https://www.w3.org/ns/activitystreams',
							],
							'actor'    => $this->getApiUriForUser($user, 'actor'),
							'object'   => $this->getApiUriForUser($user, 'object') . '/' . $objectTable->id,
						]
					);
				}
				else
				{
					$activity = $this->getActivityFromRawContent($article, $user);
				}

				if (empty($activity))
				{
					continue;
				}

				// Notify followers
				/** @var QueueModel $queueModel */
				$queueModel = $this->getApplication()
					->bootComponent('com_activitypub')
					->getMVCFactory()
					->createModel('Queue', 'Administrator');

				$queueModel->addToOutboxAndNotifyFollowers($actorTable, $activity);
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
		$isPublished = $this->isArticlePublished($article);

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
			// A new, unpublished object results in no notification BUT must create a new ObjectTable.
			if ($isNew && !$isPublished)
			{
				$objectTable = $this->getLatestObjectTableForContent($article->id, $actorTable->id, 0, true);

				continue;
			}

			// Get the activity
			$user = empty($actorTable->username)
				? Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($actorTable->user_id)
				: $this->getUserFromUsername($actorTable->username);

			$activity = $this->getActivityFromRawContent($article, $user);

			if (empty($activity))
			{
				continue;
			}

			// Notify followers
			/** @var QueueModel $queueModel */
			$queueModel = $this->getApplication()
				->bootComponent('com_activitypub')
				->getMVCFactory()
				->createModel('Queue', 'Administrator');

			$queueModel->addToOutboxAndNotifyFollowers($actorTable, $activity);
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
		$isPublished = $this->isArticlePublished($article);

		// Find which Actors apply to this content
		$applicableActors = $this->getActorsForContent($article) ?: [];

		// For each Actor, get an Activity and notify its followers
		foreach ($applicableActors as $actorTable)
		{
			// Get the last object for this content
			try
			{
				$lastObject = $this->getLatestObjectTableForContent($article->id, $actorTable->id, null, false);

				/**
				 * Last time this actor sent an update for this object it was a Delete activity, likely because the
				 * content was unpublished. No need to send another notification that the content is deleted.
				 */
				if ($lastObject->status == 0)
				{
					continue;
				}
			}
			catch (Exception $e)
			{
				// I have not sent a notification for that content before. No problem. Fall through to the Delete code.
			}

			// Get the activity
			$user = empty($actorTable->username)
				? Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($actorTable->user_id)
				: $this->getUserFromUsername($actorTable->username);

			// This creates or updates the ObjectTable
			$objectTable = $this->getCanonicalObjectTableForContent($user, $article);

			/** @var Delete $activity */
			$activity = Type::create(
				'Delete',
				[
					'@context' => [
						'https://www.w3.org/ns/activitystreams',
					],
					'actor'    => $this->getApiUriForUser($user, 'actor'),
					'object'   => $this->getApiUriForUser($user, 'object') . '/' . $objectTable->id,
				]
			);

			// Notify followers
			/** @var QueueModel $queueModel */
			$queueModel = $this->getApplication()
				->bootComponent('com_activitypub')
				->getMVCFactory()
				->createModel('Queue', 'Administrator');

			$queueModel->addToOutboxAndNotifyFollowers($actorTable, $activity);
		}
	}

	/**
	 * Get an Activity object from the raw article data.
	 *
	 * @param   object  $article  The raw article data.
	 * @param   User    $user     The user which the Activity is for.
	 *
	 * @return  AbstractActivity
	 * @throws AlreadyPunycodeException
	 * @throws InvalidCharacterException
	 * @since   2.0.0
	 */
	private function getActivityFromRawContent(object $article, User $user): ?AbstractActivity
	{
		// Get the previous ObjectTable for this content and user to find out its last publish state.
		$actorTable = $this->getActorRecordForUser($user);

		// Try to find a previous ObjectTable for this content which will tell me the last seen publish state.
		try
		{
			$objectTable  = $this
				->getLatestObjectTableForContent($article->id, $actorTable->id, null, false);
			$wasPublished = $objectTable
					->status == 1;
			$isPublished  = $this->isArticlePublished($article);

			// A published article remained published. Return an Update activity.
			if ($wasPublished && $isPublished)
			{
				// We need to update the object
				$objectTable->save([
					'modified' => Factory::getDate($article->modified)->format(DATE_ATOM)
				]);

				// And then we can return an Update activity.
				/** @noinspection PhpIncompatibleReturnTypeInspection */
				return Type::create(
					'Update',
					[
						'@context' => [
							'https://www.w3.org/ns/activitystreams',
						],
						'actor'    => $this->getApiUriForUser($user, 'actor'),
						'object'   => $this->getObjectFromRawContent($article, $user),
					]
				);
			}
			// An unpublished article remains unpublished. No activity.
			elseif (!$wasPublished && !$isPublished)
			{
				return null;
			}
		}
		catch (Exception $e)
		{
			/**
			 * That's a change to an existing article I have never seen before. Fall through to the code below which is
			 * identical to what I run for an article which changed its publish state.
			 */
		}

		// Get the source object. This updates the ObjectTable if need be.
		$sourceObject = $this->getObjectFromRawContent($article, $user);

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
					'object'   => $sourceObject->id,
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
				'id'        => $sourceObject->id,
				'actor'     => $this->getApiUriForUser($user, 'actor'),
				'object'    => $sourceObject,
			]
		);
	}
}