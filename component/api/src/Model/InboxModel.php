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
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\Exception\ResourceNotFound;
use RuntimeException;

class InboxModel extends OutboxModel
{
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

		$actorTable = $this->getActorTable($username);

		// First, try to run the request through the plugins
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

		// If the request was handled by the plugins, return.
		if ($handled)
		{
			return;
		}

		// Try with the internal handlers
		foreach ($this->getHandlerAdapters() as $adapter)
		{
			$handled = $adapter->handle($activity, $actorTable);

			if ($handled)
			{
				return;
			}
		}

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