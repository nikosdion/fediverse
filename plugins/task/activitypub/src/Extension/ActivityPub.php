<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Plugin\Task\ActivityPub\Extension;

\defined('_JEXEC') || die;

use Dionysopoulos\Component\ActivityPub\Administrator\DataShape\Request;
use Dionysopoulos\Component\ActivityPub\Administrator\Helper\MultiRequest;
use Dionysopoulos\Component\ActivityPub\Administrator\Model\QueueModel;
use Dionysopoulos\Component\ActivityPub\Administrator\Service\Signature;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\ActorTable;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\QueueTable;
use Dionysopoulos\Component\ActivityPub\Administrator\Traits\RegisterFileLoggerTrait;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
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
	use RegisterFileLoggerTrait;

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
		$runtimeBias  = 80;

		$timeLimit = $this->getApplication()->isClient('cli') ? $timeLimitCli : $timeLimitWeb;
		$timeLimit = max(5, $timeLimit);

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

		$queueModel->setState('option.timeLimit', $timeLimit);
		$queueModel->setState('option.runtimeBias', $runtimeBias);
		$queueModel->setState('option.requestLimit', $requestLimit);
		$queueModel->processQueue();

		return Status::OK;
	}
}