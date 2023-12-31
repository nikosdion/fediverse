<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2024 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\Model;

\defined('_JEXEC') || die;

use ActivityPhp\Type\Core\AbstractActivity;
use Dionysopoulos\Component\ActivityPub\Administrator\DataShape\Request;
use Dionysopoulos\Component\ActivityPub\Administrator\Helper\MultiRequest;
use Dionysopoulos\Component\ActivityPub\Administrator\Service\Signature;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\ActorTable;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\OutboxTable;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\QueueTable;
use Dionysopoulos\Component\ActivityPub\Administrator\Traits\RegisterFileLoggerTrait;
use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Application\ConsoleApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Table\Table;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseIterator;
use Throwable;

class QueueModel extends BaseDatabaseModel
{
	use RegisterFileLoggerTrait;

	/**
	 * Method to get a table object.
	 *
	 * @param   string  $type    The table name. Optional.
	 * @param   string  $prefix  The class prefix. Optional.
	 * @param   array   $config  Configuration array for model. Optional.
	 *
	 * @return  Table|QueueTable  A Table object
	 *
	 * @throws  Exception
	 * @since   2.0.0
	 */
	public function getTable($type = 'Queue', $prefix = 'Administrator', $config = [])
	{
		return parent::getTable($type, $prefix, $config);
	}

	/**
	 * Get a number of pending activities to notify
	 *
	 * @param   int  $limit  Maximum number of pending activities to include
	 *
	 * @return  array
	 * @throws Exception
	 * @since   2.0.0
	 */
	public function getPending(int $limit = 10): array
	{
		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__activitypub_queue'))
			->where($db->quoteName('next_try') . ' <= NOW()')
			->order($db->quoteName('next_try') . ' ASC');

		$results = $db->setQuery($query, 0, $limit)->loadObjectList('id') ?: [];

		if (empty($results))
		{
			return [];
		}

		/** @var QueueTable $table */
		$table = $this->getTable();

		return array_map(
			function ($rawData) use ($table) {
				$newTable = clone $table;
				$newTable->bind($rawData);

				return $newTable;
			},
			$results
		);
	}

	/**
	 * Returns a database iterator object with the follower information for the given actor.
	 *
	 * @param   int  $actor_id  The actor ID
	 *
	 * @return  DatabaseIterator|null
	 * @since   2.0.0
	 */
	public function getFollowersIteratorForActor(int $actor_id): ?DatabaseIterator
	{
		$db = $this->getDatabase();

		$query = $db->getQuery(true)
			->select([
				'DISTINCT ' . $db->quoteName('shared_inbox', 'inbox'),
				'NULL AS ' . $db->quoteName('follower_id'),
			])
			->from($db->quoteName('#__activitypub_followers'))
			->where([
				$db->quoteName('actor_id') . ' = ' . $actor_id,
				$db->quoteName('shared_inbox') . ' IS NOT NULL',
				$db->quoteName('shared_inbox') . ' != ' . $db->quote(''),
			]);

		$query2 = $db->getQuery(true)
			->select([
				$db->quoteName('inbox'),
				$db->quoteName('id', 'follower_id'),
			])
			->from($db->quoteName('#__activitypub_followers'))
			->where(
				$db->quoteName('actor_id') . ' = ' . $actor_id,
			)
			->extendWhere('AND',
				[
					$db->quoteName('shared_inbox') . ' IS NULL',
					$db->quoteName('shared_inbox') . ' = ' . $db->quote(''),
				],
				'OR'
			);

		$query->union($query2);

		try
		{
			return $db->setQuery($query)->getIterator();
		}
		catch (Exception $e)
		{
			return null;
		}
	}

	/**
	 * Adds an activity to the actor's Outbox and notifies its followers about it.
	 *
	 * @param   ActorTable        $actorTable  The actor generating the activity
	 * @param   AbstractActivity  $activity    The activity to store and notify about
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   2.0.0
	 */
	public function addToOutboxAndNotifyFollowers(ActorTable $actorTable, AbstractActivity $activity, bool $autoProcessQueue = false): void
	{
		$this->addToOutbox($actorTable, $activity);

		$this->notifyFollowers($actorTable, $activity);

		if ($autoProcessQueue)
		{
			$this->processQueue();
		}
	}

	/**
	 * Adds an activity to the actor's Outbox (without notifying followers).
	 *
	 * **This method SHOULD NOT be used directly.** It will result in activities federated servers do not know about.
	 *
	 * The only reason you might want to use this method is if you're populating an Actor's inbox with past activities
	 * (history) in which case you do not want to notify any federated servers.
	 *
	 * @param   ActorTable        $actorTable  The actor generating the activity
	 * @param   AbstractActivity  $activity    The activity to store
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   2.0.0
	 */
	public function addToOutbox(ActorTable $actorTable, AbstractActivity $activity): void
	{
		$outboxTable = OutboxTable::fromActivity($actorTable->id, $activity);

		if (!$outboxTable->store())
		{
			throw new \RuntimeException($outboxTable->getError());
		}
	}

	/**
	 * Notify an Actor's followers of an activity (without adding it to the Outbox).
	 *
	 * **This method SHOULD NOT be used directly** unless the referenced activity is _already_ in the Outbox. Otherwise,
	 * it will create an inconsistency between the state on this site and what federated servers know.
	 *
	 * The only reason you might want to use this is to send a new notification to federated servers about an activity
	 * already in the Outbox, i.e. either resending a notification or retrying to send a notification which previously
	 * failed to send.
	 *
	 * @param   ActorTable        $actorTable
	 * @param   AbstractActivity  $activity
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	public function notifyFollowers(ActorTable $actorTable, AbstractActivity $activity): void
	{
		$followers = $this->getFollowersIteratorForActor($actorTable->id);

		if (count($followers) === 0)
		{
			return;
		}

		$db           = $this->getDatabase();
		$now          = Factory::getDate();
		$activityJson = $activity->toJson();

		foreach ($followers as $follower)
		{
			$queueObject = (object) [
				'activity'    => $activityJson,
				'inbox'       => $follower->inbox,
				'actor_id'    => $actorTable->id,
				'follower_id' => $follower->follower_id,
				'retry_count' => 0,
				'next_try'    => $now->toSql(),
			];

			try
			{
				$db->insertObject('#__activitypub_queue', $queueObject);
			}
			catch (Exception $e)
			{
				// Well, if it fails, it fails.
			}
		}
	}

	/**
	 * Processes the pending queue.
	 *
	 * Expects the following state variables:
	 * - `option.timeLimit`  The maximum amount of time to spent, in seconds (default 10, minimum 5).
	 * - `option.runtimeBias`  Guard time to abort the loop, as a percentage of the timeLimit (20 to 90, default 80).
	 * - `option.requestLimit`  The maximum number of concurrent requests (default 10, min 1, max 50)
	 *
	 * @return  void
	 * @throws  Throwable
	 *
	 * @since   2.0.0
	 */
	public function processQueue(): void
	{
		$timeLimit    = $this->getState('option.timeLimit', 10);
		$runtimeBias  = $this->getState('option.runtimeBias', 80);
		$requestLimit = $this->getState('option.requestLimit', 10);

		$this->registerFileLogger('task.activitypub');

		$runtimeBias  = max(20, min(90, $runtimeBias));
		$requestLimit = min(50, max(1, $requestLimit));
		$timeLimit    = max(5, $timeLimit);
		$bailoutLimit = max($timeLimit * ($runtimeBias / 100), 2.0);

		/** @var ActorTable $actorTable */
		$actorTable = $this->getTable('Actor');
		$startTime  = microtime(true);
		$db         = $this->getDatabase();

		// Keep churning activity notifications until we are out of time or have no more pending items
		while ((microtime(true) - $startTime) < $bailoutLimit)
		{
			Log::add(
				sprintf("Getting up to %d record(s)", $requestLimit),
				Log::DEBUG,
				'task.activitypub'
			);

			// Lock table to maintain consistency while reading
			$db->lockTable('#__activitypub_queue');

			// Get up to $requestLimit pending requests
			$pendingQueueItems = $this->getPending($requestLimit);

			// If there are no requests do a fast task exit: return Status::OK
			if (empty($pendingQueueItems))
			{
				Log::add(
					'No records found.',
					Log::DEBUG,
					'task.activitypub'
				);

				$db->unlockTables();

				return;
			}

			Log::add(
				'Temporarily removing activities from the queue',
				Log::DEBUG,
				'task.activitypub'
			);

			/**
			 * Remove the activities from the pool.
			 *
			 * IMPORTANT: Do not start a transaction!
			 *
			 * Starting a transaction implicitly removes table locks.
			 * @see https://dev.mysql.com/doc/refman/8.0/en/commit.html
			 */
			/** @var QueueTable $queueTable */
			foreach ($pendingQueueItems as $queueTable)
			{
				$queueTable->delete($queueTable->id);
			}

			// Unlock the queue table
			$db->unlockTables();

			/** @var CMSApplication|ConsoleApplication $application */
			$application      = Factory::getApplication();
			$now              = Factory::getDate();
			$signatureService = new Signature(
				$this->getDatabase(),
				Factory::getContainer()->get(UserFactoryInterface::class),
				$application
			);

			try
			{
				Log::add(
					sprintf('Preparing to send %d notification request(s)', count($pendingQueueItems)),
					Log::INFO,
					'task.activitypub'
				);

				$options = (defined('JDEBUG') && JDEBUG)
					? [
						CURLOPT_SSL_VERIFYHOST   => 0,
						CURLOPT_SSL_VERIFYPEER   => 0,
						CURLOPT_SSL_VERIFYSTATUS => 0,
					] : [];

				$multiRequest = new MultiRequest(
					maxRequests: $requestLimit,
					headers: [
						'Accept'       => 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"',
						'Content-Type' => 'application/activity+json',
						'Date'         => $now->format(\DateTimeInterface::RFC7231, false, false),
					],
					options: $options,
					callback: function (?string $response, array $requestInfo) use (&$pendingQueueItems) {
						/** @var Request $requestDeclaration */
						$requestDeclaration = $requestInfo['request'];
						/** @var QueueTable $queueItem */
						$queueItem = $requestDeclaration->userData;
						$httpCode  = (int) ($requestInfo['http_code'] ?? 500);

						// If the request finished successfully we remove it from the pending queue
						if ($requestInfo['result'] === CURLE_OK && in_array($httpCode, [200, 201, 202]))
						{
							Log::add(
								sprintf('Request %d finished successfully', $queueItem->id),
								Log::DEBUG,
								'task.activitypub'
							);

							$pendingQueueItems[$queueItem->id] = null;

							return;
						}

						if ($httpCode > 0 && $httpCode != 200)
						{
							$errorReason = sprintf('HTTP status %s', $httpCode);
						}
						else
						{
							$errNo = curl_errno($requestInfo['handle']);
							$error = curl_error($requestInfo['handle']);

							$errorReason = sprintf(
								'cURL error #%d: %s',
								$errNo, $error
							);
						}

						// Try to bump the retry count. If we have already tried too many times, remove from the queue.
						Log::add(
							sprintf(
								'Request %d failed (%s). Bumping retry count.',
								$queueItem->id,
								$errorReason
							),
							Log::DEBUG,
							'task.activitypub'
						);

						if (!$queueItem->bumpRetryCount())
						{
							Log::add(
								sprintf('Request %d failed more than 10 consecutive times; removing from the pool.', $queueItem->id),
								Log::NOTICE,
								'task.activitypub'
							);

							$pendingQueueItems[$queueItem->id] = null;

							return;
						}

						// Throw the queued item back into the queue.
						$pendingQueueItems[$queueItem->id] = $queueItem;
					}
				);

				/** @var QueueTable $queueItem */
				foreach ($pendingQueueItems as $queueItem)
				{
					$actorTable->reset();
					$actorTable->id = null;

					if (!$actorTable->load($queueItem->actor_id))
					{
						// Pending items with an invalid actor are silently discarded
						continue;
					}

					$postBody = $queueItem->activity;
					$digest   = $signatureService->digest($postBody);

					Log::add(
						sprintf('Adding request %d to the queue', $queueItem->id),
						Log::DEBUG,
						'task.activitypub'
					);

					$multiRequest->enqueue(
						url: $queueItem->inbox,
						postData: $postBody,
						userData: $queueItem,
						headers: [
							'Digest'    => 'SHA-256=' . $digest,
							'Signature' => $signatureService->sign($actorTable, $queueItem->inbox, $now, $digest),
						]
					);
				}

				// Execute the multirequest
				$multiRequest->execute();
			}
			catch (Throwable $e)
			{
				Log::add(
					'Received error processing the queue.',
					Log::ERROR,
					'task.activitypub'
				);


				// Suppress the exception, so we can throw it after persisting the failed items into the database.
				$exception = $e;
			}

			// Persist the failed items into the database
			$pendingQueueItems = array_filter($pendingQueueItems);

			if (!empty($pendingQueueItems))
			{
				Log::add(
					sprintf('There are %d requests to put back into the queue', count($pendingQueueItems)),
					Log::DEBUG,
					'task.activitypub'
				);

				$db->transactionStart();

				foreach ($pendingQueueItems as $queueItem)
				{
					try
					{
						$queueItem->id = null;
						$queueItem->store();
					}
					catch (\Exception $e)
					{
						// If it dies, it dies.
					}
				}

				$db->transactionCommit();
			}

			// So, we had a Throwable. Throw it back so the task saves a Knockout status for this execution.
			if (isset($exception))
			{
				Log::add(
					sprintf(
						'Delayed error is now thrown [%s:%d]: %s',
						$exception->getFile(),
						$exception->getLine(),
						$exception->getMessage(),
					),
					Log::CRITICAL,
					'task.activitypub'
				);

				throw $exception;
			}
		}

		Log::add(
			'Batch processing done.',
			Log::DEBUG,
			'task.activitypub'
		);
	}
}