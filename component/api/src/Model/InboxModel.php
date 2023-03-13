<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Api\Model;

\defined('_JEXEC') || die;

use ActivityPhp\Type\AbstractObject;
use Dionysopoulos\Component\ActivityPub\Administrator\Event\HandleActivity;
use Dionysopoulos\Component\ActivityPub\Administrator\Traits\RegisterFileLoggerTrait;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\Exception\ResourceNotFound;
use Joomla\CMS\Uri\Uri;
use RuntimeException;

class InboxModel extends OutboxModel
{
	use RegisterFileLoggerTrait;

	/**
	 * Handles a POST request
	 *
	 * @param   string          $username  The local actor username which received a POST request
	 * @param   AbstractObject  $activity  The activity which was POSTed
	 *
	 * @return  void
	 * @throws  Exception  When the request cannot be handled
	 * @since   2.0.0
	 */
	public function handlePost(string $username, AbstractObject $activity): void
	{
		$target = 'inbox';

		if (empty($username))
		{
			throw new ResourceNotFound('Not Found', 404);
		}

		// Log requests during development.
		if (defined('JDEBUG') && JDEBUG)
		{
			$this->registerFileLogger('activitypub.inbox');

			$input  = Factory::getApplication()->input;
			$method = strtoupper($input->getMethod());

			Log::add(
				sprintf(
					'Received %s to inbox — %s',
					$method,
					Uri::current()
				),
				Log::DEBUG, 'activitypub.inbox');

			Log::add(
				print_r($input->getArray(), true),
				Log::DEBUG, 'activitypub.inbox');

			Log::add(
				print_r($activity->toJson(JSON_PRETTY_PRINT), true),
				Log::DEBUG, 'activitypub.inbox');
		}

		$actorTable = $this->getActorTable($username);

		// First, try to run the request through the plugins
		try
		{
			$event      = new HandleActivity($activity, $actorTable, $target);
			$dispatcher = Factory::getApplication()->getDispatcher();
			$dispatcher->dispatch($event->getName(), $event);
			$results = $event->getArgument('result', []) ?: [];
			$results = is_array($results) ? $results : [];
			$handled = array_reduce(
				$results,
				fn($carry, $result) => $carry || ($result === true),
				false
			);
		}
		catch (\Throwable $e)
		{
			if (defined('JDEBUG') && JDEBUG)
			{
				Log::add(
					sprintf(
						'Plugins threw an exception: #%d %s (%s:%d)',
						$e->getCode(),
						$e->getMessage(),
						$e->getFile(),
						$e->getLine()
					),
					Log::DEBUG, 'activitypub.inbox');
			}

			throw $e;
		}

		// If the request was handled by the plugins, return.
		if ($handled)
		{
			if (defined('JDEBUG') && JDEBUG)
			{
				Log::add(
					'Request handled by plugin',
					Log::DEBUG, 'activitypub.inbox');
			}

			return;
		}

		// Try with the internal handlers
		foreach ($this->getHandlerAdapters() as $adapter)
		{
			try
			{
				$handled = $adapter->handle($activity, $actorTable);
			}
			catch (Exception $e)
			{
				if (defined('JDEBUG') && JDEBUG)
				{
					Log::add(
						sprintf(
							'Internal adapter ‘%s’ threw an exception: #%d %s (%s:%d)',
							get_class($adapter),
							$e->getCode(),
							$e->getMessage(),
							$e->getFile(),
							$e->getLine()
						),
						Log::DEBUG, 'activitypub.inbox');
				}

				throw $e;
			}

			if ($handled)
			{
				if (defined('JDEBUG') && JDEBUG)
				{
					Log::add(
						sprintf(
							'Request handled by included adapter %s',
							get_class($adapter)
						),
						Log::DEBUG, 'activitypub.inbox');
				}

				return;
			}
		}

		Log::add(
			'Unhandled request; will return HTTP 501',
			Log::DEBUG, 'activitypub.inbox');

		throw new RuntimeException('Not implemented', 501);
	}

	/**
	 * Returns the Inbox handler adapters
	 *
	 * @return  PostHandlerAdapterInterface[]
	 * @since   2.0.0
	 */
	private function getHandlerAdapters(): array
	{
		$namespace = __NAMESPACE__ . '\\InboxAdapter\\';
		$di        = new \DirectoryIterator(__DIR__ . '/InboxAdapter');
		$ret       = [];

		/** @var \DirectoryIterator $file */
		foreach ($di as $file)
		{
			if (!$file->isFile() || !$file->isReadable() || $file->getExtension() !== 'php')
			{
				continue;
			}

			$basename  = $file->getBasename('.php');
			$className = $namespace . $basename;

			if (!class_exists($className))
			{
				continue;
			}

			try
			{
				$ret[] = new $className($this->getDatabase(), $this->getMVCFactory());
			}
			catch (\Throwable $e)
			{
				continue;
			}
		}

		return $ret;
	}

}