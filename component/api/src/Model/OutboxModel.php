<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Api\Model;

use ActivityPhp\Type\AbstractObject;
use Dionysopoulos\Component\ActivityPub\Administrator\Event\HandleActivity;
use Dionysopoulos\Component\ActivityPub\Administrator\Mixin\GetActorTrait;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\ActorTable;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\Exception\ResourceNotFound;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Pagination\Pagination;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\DatabaseQuery;
use Joomla\Database\ParameterType;
use RuntimeException;

class OutboxModel extends BaseDatabaseModel
{
	/**
	 * Internal memory based cache array of data.
	 *
	 * @var    array
	 * @since  2.0.0
	 */
	protected array $cache = [];

	/**
	 * Context string for the model type.  This is used to handle uniqueness
	 * when dealing with the getStoreId() method and caching data structures.
	 *
	 * @var    string|null
	 * @since  2.0.0
	 */
	protected ?string $context = null;

	/**
	 * List of state keys which participate in creating cache IDs.
	 *
	 * @var    array
	 * @since  2.0.0
	 */
	protected array $cacheRelevantFilters = [];

	use GetActorTrait;

	protected string $defaultTarget = 'outbox';

	/**
	 * The actor for which we're listing Activities in their Outbox.
	 *
	 * @var    ActorTable|null
	 * @since  2.0.0
	 */
	private ?ActorTable $actorTable = null;

	/**
	 * Constructor
	 *
	 * @param   array                     $config   An array of configuration options
	 * @param   MVCFactoryInterface|null  $factory  The factory.
	 *
	 * @throws  Exception
	 * @since   2.0.0
	 */
	public function __construct($config = [], MVCFactoryInterface $factory = null)
	{
		parent::__construct($config, $factory);

		$this->context                = strtolower($this->option . '.' . $this->getName());
		$this->cacheRelevantFilters[] = 'filter.username';
	}

	/**
	 * Handles a POST request
	 *
	 * @param   string          $username  The local actor username which received a POST request
	 * @param   AbstractObject  $activity  The activity which was POSTed
	 * @param   string          $target    The POST target: inbox or outbox
	 *
	 * @return  void
	 * @throws  Exception  When the request cannot be handled
	 * @since   2.0.0
	 */
	public function handlePost(string $username, AbstractObject $activity, string $target = ''): void
	{
		$target = $target ?: $this->defaultTarget;

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
	 * Method to get an array of data items.
	 *
	 * @return  array  An array of data items
	 *
	 * @throws  Exception  On failure
	 * @since   2.0.0
	 */
	public function getItems(): array
	{
		$store = $this->getStoreId();

		return $this->cache[$store] ??= $this->_getList(
			$this->_getListQuery(),
			$this->getStart(),
			$this->getState('list.limit')
		);
	}

	protected function _getList($query, $limitstart = 0, $limit = 0)
	{
		return array_map(
			fn($x) => json_decode($x->activity, true),
			parent::_getList($query, $limitstart, $limit) ?: []
		);
	}


	/**
	 * Method to get a Pagination object for the data set.
	 *
	 * @return  Pagination  A Pagination object for the data set.
	 *
	 * @since   2.0.0
	 */
	public function getPagination(): Pagination
	{
		$store = $this->getStoreId('getPagination');

		return $this->cache[$store] ??= new Pagination(
			$this->getTotal(),
			$this->getStart(),
			(int) $this->getState('list.limit')
			- (int) $this->getState('list.links')
		);
	}

	/**
	 * Method to get the total number of items for the data set.
	 *
	 * @return  int  The total number of items available in the data set.
	 *
	 * @throws  RuntimeException  On failure
	 * @since   2.0.0
	 */
	public function getTotal(): int
	{
		$store = $this->getStoreId('getTotal');

		return $this->cache[$store] ??= (int) $this->_getListCount($this->_getListQuery());
	}

	/**
	 * Method to get the starting number of items for the data set.
	 *
	 * @return  int  The starting number of items available in the data set.
	 *
	 * @since   2.0.0
	 */
	public function getStart(): int
	{
		$store = $this->getStoreId('getstart');

		return $this->cache[$store] ??= call_user_func(
			function () {
				$start = $this->getState('list.start', 0);

				if ($start <= 0)
				{
					return $start;
				}

				$limit = $this->getState('list.limit', 20);
				$total = $this->getTotal();

				if ($start <= $total - $limit)
				{
					return $start;
				}

				return max(0, (int) (ceil($total / $limit) - 1) * $limit);
			}
		);
	}

	/**
	 * Method to get a cached copy of the query constructed.
	 *
	 * @return  DatabaseQuery  A DatabaseQuery object
	 *
	 * @since   2.0.0
	 */
	protected function _getListQuery(): DatabaseQuery
	{
		// Compute the current store id.
		$store = $this->getStoreId('query');

		return $this->cache[$store] ??= $this->getListQuery();
	}

	/**
	 * Method to get a store id based on the model configuration state.
	 *
	 * This is necessary because the model is used by the component and
	 * different modules that might need different sets of data or different
	 * ordering requirements.
	 *
	 * @param   string  $id  An identifier string to generate the store id.
	 *
	 * @return  string  A store id.
	 *
	 * @since   2.0.0
	 */
	protected function getStoreId(string $id = ''): string
	{
		// Add the list state to the store id.
		$id .= ':' . $this->getState('list.start') ?? 0;
		$id .= ':' . $this->getState('list.limit') ?? 20;
		$id .= ':' . $this->getState('list.ordering') ?? '';
		$id .= ':' . $this->getState('list.direction') ?? '';

		foreach ($this->cacheRelevantFilters as $key)
		{
			$id .= ':' . $this->getState($key) ?? '';
		}

		return md5($this->context . ':' . $id);
	}

	/**
	 * Method to get a DatabaseQuery object for retrieving the data set from a database.
	 *
	 * @return  DatabaseQuery  A DatabaseQuery object to retrieve the data set.
	 *
	 * @throws  Exception
	 * @since   2.0.0
	 */
	protected function getListQuery(): DatabaseQuery
	{
		$username         = $this->getUsername();
		$this->actorTable = $this->getActorTable($username);

		/** @var DatabaseDriver $db */
		$db = $this->getDatabase();

		$query = $db->getQuery(true);
		$query->select([
			$db->quoteName('activity'),
		])
			->from($db->quoteName('#__activitypub_outbox'))
			->where($db->quoteName('actor_id') . ' = :actorId')
			->bind(':actorId', $this->actorTable->id, ParameterType::INTEGER)
			->order($db->quoteName('id') . ' DESC');

		return $query;
	}

	/**
	 * Get the username set up in the model state, or throw an exception.
	 *
	 * @return  string
	 * @throws  ResourceNotFound  If there is no username set up.
	 * @since   2.0.0
	 */
	private function getUsername(): string
	{
		$username = trim($this->getState('filter.username') ?? '');

		if (empty($username))
		{
			throw new ResourceNotFound('Not Found', 404);
		}

		return $username;
	}

	/**
	 * Get the ActorTable corresponding to a username
	 *
	 * @param   string  $username  The username to look up.
	 *
	 * @return  ActorTable
	 * @throws  Exception  If there is no such user, or there is an error.
	 * @since   2.0.0
	 */
	private function getActorTable(string $username): ActorTable
	{
		/** @var ActorModel $actorModel */
		$actorModel = $this->getMVCFactory()
			->createModel('Actor', 'Api', ['ignore_request' => true]);
		$user       = $this->getUserFromUsername($username);
		$actorTable = $actorModel->getActorRecordForUser($user, false);

		if ($actorTable === null)
		{
			throw new ResourceNotFound('Not Found', 404);
		}

		return $actorTable;
	}

	/**
	 * Returns the Outbox handler adapters
	 *
	 * @return  PostHandlerAdapterInterface[]
	 * @since   2.0.0
	 */
	private function getHandlerAdapters(): array
	{
		$namespace = __NAMESPACE__ . '\\' . ucfirst($this->defaultTarget) . 'Adapter\\';
		$di        = new \DirectoryIterator(__DIR__ . '/' . ucfirst($this->defaultTarget) . 'Adapter');
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