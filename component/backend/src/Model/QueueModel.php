<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\Model;

\defined('_JEXEC') || die;

use Dionysopoulos\Component\ActivityPub\Administrator\Table\QueueTable;
use Exception;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseQuery;

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
			->where($db->quoteName('next_try') . ' >= NOW()')
			->order($db->quoteName('next_try') .' ASC');

		$results = $db->setQuery($query, 0, $limit)->loadObjectList('id') ?: [];

		if (empty($results))
		{
			return [];
		}

		/** @var QueueTable $table */
		$table = $this->getTable();

		return array_map(
			fn($rawData) => (clone $table)->bind($rawData),
			$results
		);
	}
}