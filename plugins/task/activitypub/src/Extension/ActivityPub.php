<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Plugin\Task\ActivityPub\Extension;

\defined('_JEXEC') || die;

use Dionysopoulos\Component\ActivityPub\Administrator\Model\QueueModel;
use Dionysopoulos\Component\ActivityPub\Administrator\Service\Signature;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\ActorTable;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\QueueTable;
use Dionysopoulos\Plugin\Task\ActivityPub\Library\DataShape\Request;
use Dionysopoulos\Plugin\Task\ActivityPub\Library\MultiRequest;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryServiceInterface;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Task\Task;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;

class ActivityPub extends CMSPlugin implements SubscriberInterface, DatabaseAwareInterface
{
	use DatabaseAwareTrait;
	use TaskPluginTrait;

	/**
	 * Describes the scheduled task types implemented by this plugin
	 *
	 * @since 2.0.0
	 */
	private const TASKS_MAP = [
		'activitypub.notify' => [
			'langConstPrefix' => 'PLG_TASK_ACTIVITYPUB_TASK_NOTIFY',
			'method'          => 'scan',
			'form'            => 'notifyForm',
		],
	];

	/**
	 * Affects constructor behavior. If true, language files will be loaded automatically.
	 *
	 * @var   boolean
	 * @since 2.0.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * Returns an array of events this subscriber will listen to.
	 *
	 * This is mostly boilerplate code as per every built-in Task plugin in Joomla.
	 *
	 * @return  array
	 * @since   2.0.0
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onTaskOptionsList'    => 'advertiseRoutines',
			'onExecuteTask'        => 'standardRoutineHandler',
			'onContentPrepareForm' => 'enhanceTaskItemForm',
		];
	}

	private function notifyForm(ExecuteTaskEvent $event): int
	{
		// Get some basic information about the task at hand.
		/** @var Task $task */
		$task         = $event->getArgument('subject');
		$params       = $event->getArgument('params') ?: (new \stdClass());
		$timeLimitCli = (int) $params->time_limit_cli ?? -1;
		$timeLimitWeb = (int) $params->time_limit_web ?? -1;
		$requestLimit = 10;
		$timeLimit    = $this->getApplication()->isClient('cli') ? $timeLimitCli : $timeLimitWeb;
		$timeLimit    = max(5, $timeLimit);
		$bailoutLimit = max($timeLimit / 5, 2.0);

		// Make sure ActivityPub is installed and enabled.
		$component = ComponentHelper::isEnabled('com_activitypub')
			? Factory::getApplication()->bootComponent('com_activitypub')
			: null;

		if (!($component instanceof MVCFactoryServiceInterface))
		{
			throw new \RuntimeException('The ActivityPub component is not installed or has been disabled.');
		}

		/** @var QueueModel $queueModel */
		$queueModel = $component->getMVCFactory()
			->createModel('Queue', 'Administrator', ['ignore_request' => true]);
		/** @var ActorTable $actorTable */
		$actorTable = $component->getMVCFactory()
			->createTable('Actor', 'Administrator');

		$startTime = microtime(true);
		$db        = $this->getDatabase();

		// Keep churning activity notifications until we are out of time or have no more pending items
		while ((microtime(true) - $startTime) > $bailoutLimit)
		{
			// Lock table to maintain consistency while reading
			$db->lockTable('#__activitypub_queue');

			// Get up to $requestLimit pending requests
			$pendingQueueItems = $queueModel->getPending($requestLimit);

			// If there are no requests do a fast task exit: return Status::OK
			if (empty($pendingQueueItems))
			{
				$db->unlockTables();

				return Status::OK;
			}

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

			$now              = Factory::getDate();
			$signatureService = new Signature(
				$this->getDatabase(),
				Factory::getContainer()->get(UserFactoryInterface::class),
				Factory::getApplication()
			);

			try
			{
				$multiRequest = new MultiRequest(
					maxRequests: $requestLimit,
					headers: [
						'Accept'       => 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"',
						'Content-Type' => 'application/activity+json',
						'Date'         => $now->format(\DateTimeInterface::RFC7231, false, false),
					],
					callback: function (string $response, array $requestInfo) use (&$pendingQueueItems) {
						/** @var Request $requestDeclaration */
						$requestDeclaration = $requestInfo['request'];
						/** @var QueueTable $queueItem */
						$queueItem = $requestDeclaration->userData;
						$httpCode  = (int) ($requestInfo['http_code'] ?? 500);

						// If the request finished successfully we remove it from the pending queue
						if ($requestInfo['result'] === CURLE_OK && $httpCode === 200)
						{
							$pendingQueueItems[$queueItem->id] = null;

							return;
						}

						// Try to bump the retry count. If we have already tried too many times, remove from the queue.
						if (!$queueItem->bumpRetryCount())
						{
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
					$postBody = $queueItem->activity;
					$digest   = $signatureService->digest($postBody);

					$actorTable->reset();
					$actorTable->id = null;

					if (!$actorTable->load($queueItem->actor_id))
					{
						// Pending items with an invalid actor are silently discarded
						continue;
					}

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
			catch (\Throwable $e)
			{
				// Suppress the exception so we can throw it after persisting the failed items into the database.
				$exception = $e;
			}

			// Persist the failed items into the database
			$pendingQueueItems = array_filter($pendingQueueItems);

			if (!empty($pendingQueueItems))
			{
				$db->transactionStart();

				foreach ($pendingQueueItems as $queueItem)
				{
					try
					{
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
				throw $exception;
			}
		}

		// Indicate we finished successfully
		return Status::OK;
	}
}