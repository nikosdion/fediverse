<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Api\Model;

use ActivityPhp\Type\Core\AbstractActivity;
use Dionysopoulos\Component\ActivityPub\Administrator\Event\GetActivity;
use Dionysopoulos\Component\ActivityPub\Administrator\Event\GetActivityListQuery;
use Dionysopoulos\Component\ActivityPub\Administrator\Mixin\GetActorTrait;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\ActorTable;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\Exception\ResourceNotFound;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\DatabaseQuery;

class OutboxModel extends AbstractListModel
{
	use GetActorTrait;

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

		$this->cacheRelevantFilters[] = 'filter.username';
	}

	/**
	 * Method to get a DatabaseQuery object for retrieving the data set from a database.
	 *
	 * The query will return a list of context, id, and timestamp — not activities. This partial information is
	 * retrieved very quickly, so we can get an accurate tally of the total number of Activities for pagination reasons.
	 * Once paginated, the much more limited results undergo the much slower transformation to Activity objects by the
	 * overridden _getList() method.
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
		$queryList        = $this->getQueryList($this->actorTable);

		/** @var DatabaseDriver $db */
		$db = $this->getDatabase();

		if (count($queryList) === 1)
		{
			/** @var DatabaseQuery $query */
			$query = array_shift($queryList);
			$query->order($db->quoteName('timestamp') . ' DESC');

			return $query;
		}

		$query = $db->getQuery(true);
		/** @noinspection PhpParamsInspection */
		$query->querySet(array_shift($queryList));

		while (!empty($queryList))
		{
			$query->union(array_shift($queryList));
		}

		$query->order($db->quoteName('timestamp') . ' DESC');

		return $query;
	}

	/**
	 * Returns a list of items for display by the View.
	 *
	 * Normally, the query (provided by the getListQuery method) only returns some partial information. These results
	 * are then fed through plugin events which convert them to Activity objects. This method returns the Activity
	 * objects.
	 *
	 * @param   string   $query       The query.
	 * @param   integer  $limitstart  Offset.
	 * @param   integer  $limit       The number of records.
	 *
	 * @return  AbstractActivity[]
	 * @throws  Exception
	 * @since   2.0.0
	 */
	protected function _getList($query, $limitstart = 0, $limit = 0)
	{
		$items = parent::_getList($query, $limitstart, $limit);

		if (empty($items))
		{
			/** @noinspection PhpIncompatibleReturnTypeInspection */
			return $items;
		}

		// Keys: id, timestamp, context
		$perContext = [];

		foreach ($items as $item)
		{
			$perContext[$item->context]   ??= [];
			$perContext[$item->context][] = $item->id;
		}

		// Call plugins to convert the per-context list of IDs to Activity objects
		PluginHelper::importPlugin('activitypub');
		PluginHelper::importPlugin('content');

		$dispatcher = Factory::getApplication()->getDispatcher();
		$results    = [];

		foreach ($perContext as $context => $ids)
		{
			$event = new GetActivity($this->actorTable, $context, $ids);
			$dispatcher->dispatch($event->getName(), $event);
			$activities = $event->getArgument('result');
			$activities = is_array($activities) ? $activities : [];

			foreach($activities as $activityList)
			{
				$results = array_merge($results, $activityList);
			}
		}

		// Convert the items list to activities using the above $results and return the result
		return array_filter(array_map(
			fn($item) => $results[$item->context . '.' . $item->id] ?? null,
			$items
		));
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
	 * Get the list of queries to join with UNION for the specified ActorTable object.
	 *
	 * @param   ActorTable  $actorTable  The actor for which the Activity queries will be retrieved.
	 *
	 * @return  DatabaseQuery[]
	 * @throws  Exception  If there are no queries returned, or there is an error.
	 * @since   2.0.0
	 */
	private function getQueryList(ActorTable $actorTable): array
	{
		PluginHelper::importPlugin('activitypub');
		PluginHelper::importPlugin('content');

		$event      = new GetActivityListQuery($actorTable);
		$dispatcher = Factory::getApplication()->getDispatcher();
		$dispatcher->dispatch($event->getName(), $event);

		$queryList = $event->getArgument('result', []);

		if (empty($queryList))
		{
			throw new ResourceNotFound('Not Found', 404);
		}

		return $queryList;
	}
}