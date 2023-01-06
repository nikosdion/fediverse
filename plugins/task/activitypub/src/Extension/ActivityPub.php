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
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Router\Router;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Task\Task;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;
use ReflectionClass;
use Throwable;

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
			'method'          => 'activityPubNotify',
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

	/**
	 * Initialise the site URL and routing under the CLI application
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	private function initCliRouting(): void
	{
		$app = $this->getApplication();

		if (!$app->isClient('cli'))
		{
			return;
		}

		$cParams = ComponentHelper::getParams('com_activitypub');
		$siteURL = $cParams->get('siteurl', null) ?: $app->set('live_site', null);

		if (empty($siteURL) || $siteURL === 'https://joomla.invalid/set/by/console/application')
		{
			throw new \RuntimeException('You need to visit the ActivityPub component\'s Actors page before using this task in CLI');
		}

		$app->set('live_site', $siteURL);

		// Set up the base site URL in JUri
		$uri                    = Uri::getInstance($siteURL);
		$_SERVER['HTTP_HOST']   = $uri->toString(['host', 'port']);
		$_SERVER['REQUEST_URI'] = $uri->getPath();
		$_SERVER['HTTPS']       = $uri->getScheme() === 'https' ? 'on' : 'off';

		$refClass     = new ReflectionClass(Uri::class);
		$refInstances = $refClass->getProperty('instances');
		$refInstances->setAccessible(true);
		$instances           = $refInstances->getValue();
		$instances['SERVER'] = $uri;
		$refInstances->setValue($instances);

		$base = [
			'prefix' => $uri->toString(['scheme', 'host', 'port']),
			'path'   => rtrim($uri->toString(['path']), '/\\'),
		];

		$refBase = $refClass->getProperty('base');
		$refBase->setAccessible(true);
		$refBase->setValue($base);
	}

	/**
	 * Send a batch of notifications to remote servers
	 *
	 * @param   ExecuteTaskEvent  $event
	 *
	 * @return  int
	 * @throws  Throwable
	 *
	 * @since        2.0.0
	 * @noinspection PhpUnusedPrivateMethodInspection
	 */
	private function activityPubNotify(ExecuteTaskEvent $event): int
	{
		// Initialise the site URL in case we're under CLI
		$this->initCliRouting();

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

		$this->registerFileLogger('task.activitypub');

		// Make sure ActivityPub is installed and enabled.
		$component = ComponentHelper::isEnabled('com_activitypub')
			? Factory::getApplication()->bootComponent('com_activitypub')
			: null;

		if ($component === null)
		{
			Log::add('The ActivityPub component is not installed or has been disabled.', Log::DEBUG, 'task.activitypub');

			return Status::OK;
		}

		try
		{
			/** @var QueueModel $queueModel */
			$queueModel = $component->getMVCFactory()
				->createModel('Queue', 'Administrator', ['ignore_request' => true]);
			/** @var ActorTable $actorTable */
			$actorTable = $component->getMVCFactory()
				->createTable('Actor', 'Administrator');
		}
		catch (Throwable $e)
		{
			Log::add(
				sprintf(
					'Error getting the models [%s:%d]: %s',
					$e->getFile(),
					$e->getLine(),
					$e->getMessage()
				),
				Log::ERROR,
				'task.activitypub'
			);

			throw $e;
		}

		Log::add(
			sprintf('Begin processing ActivityPub notifications, batch size %s', $requestLimit),
			Log::INFO,
			'task.activitypub'
		);

		$startTime = microtime(true);
		$db        = $this->getDatabase();

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
			$pendingQueueItems = $queueModel->getPending($requestLimit);

			// If there are no requests do a fast task exit: return Status::OK
			if (empty($pendingQueueItems))
			{
				Log::add(
					'No records found.',
					Log::DEBUG,
					'task.activitypub'
				);

				$db->unlockTables();

				return Status::OK;
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

			$now              = Factory::getDate();
			$signatureService = new Signature(
				$this->getDatabase(),
				Factory::getContainer()->get(UserFactoryInterface::class),
				Factory::getApplication()
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

		// Indicate we finished successfully
		return Status::OK;
	}

	/**
	 * Register a file logger for the given context if we have not already done so.
	 *
	 * If no file is specified a log file will be created, named after the context. For example, the context 'foo.bar'
	 * is logged to the file 'foo_bar.php' in Joomla's configured `logs` directory.
	 *
	 * The minimum log level to write to the file is determined by Joomla's debug flag. If you have enabled Site Debug
	 * the log level is JLog::All which log everything, including debug information. If Site Debug is disabled the
	 * log level is JLog::INFO which logs everything *except* debug information.
	 *
	 * @param   string       $context  The context to register
	 * @param   string|null  $file     The file to use for this context
	 *
	 * @return  void
	 *
	 * @since   2.0.0
	 */
	private function registerFileLogger(string $context, ?string $file = null): void
	{
		static $registeredLoggers = [];

		// Make sure we are not double-registering a logger
		$sig = md5($context . '.file');

		if (in_array($sig, $registeredLoggers))
		{
			return;
		}

		$registeredLoggers[] = $sig;

		/**
		 * If no file is specified we will create a filename based on the context.
		 *
		 * For example the context 'ats.cron' results in the log filename 'ats_cron.php'
		 */
		if (is_null($file))
		{
			$filter          = InputFilter::getInstance();
			$filteredContext = $filter->clean($context, 'cmd');
			$file            = str_replace('.', '_', $filteredContext) . '.php';
		}

		// Register the file logger
		$logLevel = $this->getJoomlaDebug() ? Log::ALL : Log::INFO;

		Log::addLogger(['text_file' => $file], $logLevel, [$context]);
	}

	/**
	 * Get Joomla's debug flag
	 *
	 * @return  bool
	 *
	 * @since   2.0.0
	 */
	private function getJoomlaDebug(): bool
	{
		// If the JDEBUG constant is defined return its value cast as a boolean
		if (defined('JDEBUG'))
		{
			return (bool) JDEBUG;
		}

		// Joomla 3 & 4 – go through the application object to get the application configuration value
		try
		{
			return (bool) (Factory::getApplication()->get('debug', 0));
		}
		catch (Throwable $e)
		{
			return false;
		}
	}

}