<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\Model;

\defined('_JEXEC') || die;

use ActivityPhp\Type\Core\AbstractActivity;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\ActorTable;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\QueueTable;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseIterator;

class QueueModel extends BaseDatabaseModel
{
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
	 * Enqueue notifications for the activity to all followers of the given actor.
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
}