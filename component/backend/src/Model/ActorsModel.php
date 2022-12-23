<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\Model;

defined('_JEXEC') || die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseQuery;
use Joomla\Database\ParameterType;

class ActorsModel extends ListModel
{
	/**
	 * Constructor.
	 *
	 * @param   array  $config  An optional associative array of configuration settings.
	 *
	 * @throws  \Exception
	 * @since   2.0.0
	 */
	public function __construct($config = [])
	{
		if (empty($config['filter_fields']))
		{
			$config['filter_fields'] = [
				'id', 'a.id',
				'cid', 'a.cid',
				'user_id', 'a.user_id',
				'name', 'a.name',
				'username', 'a.username',
				'type', 'a.type',
			];
		}

		parent::__construct($config);
	}

	/**
	 * Method to get a table object.
	 *
	 * @param   string  $type    The table name. Optional.
	 * @param   string  $prefix  The class prefix. Optional.
	 * @param   array   $config  Configuration array for model. Optional.
	 *
	 * @return  Table  A Table object
	 *
	 * @throws  \Exception
	 * @since   2.0.0
	 */
	public function getTable($type = 'Actor', $prefix = 'Administrator', $config = [])
	{
		return parent::getTable($type, $prefix, $config);
	}

	/**
	 * Method to get a DatabaseQuery object for retrieving the data set from a database.
	 *
	 * @return  DatabaseQuery  A DatabaseQuery object to retrieve the data set.
	 *
	 * @since   2.0.0
	 */
	protected function getListQuery()
	{
		$db    = $this->getDatabase();
		$query = parent::getListQuery();

		$query->select(
			$this->getState(
				'list.select',
				[
					$db->quoteName('a.id'),
					$db->quoteName('a.user_id'),
					$db->quoteName('a.name'),
					$db->quoteName('a.username'),
					$db->quoteName('a.type'),
				]
			)
		)
			->from($db->quoteName('#__activitypub_actors', 'a'))
			->where('TRUE');

		// Filter by name, username, or email address (search)
		$search = $this->getState('filter.search');

		if (!empty($search))
		{
			if (str_starts_with($search, 'id:'))
			{
				$search = (int) substr($search, 3);
				$query->where($db->quoteName('a.id') . ' = :search')
					->bind(':search', $search, ParameterType::INTEGER);
			}
			else
			{
				$query->leftJoin(
					$db->quoteName('#__users', 'u'),
					$db->quoteName('a.user_id') . ' = ' . $db->quoteName('u.user_id')
				);

				if (str_starts_with($search, 'name:'))
				{
					$search = '%' . trim(substr($search, 5)) . '%';

					$query
						->extendWhere(
							'AND',
							[
								$db->quoteName('a.name') . ' LIKE :search1',
								$db->quoteName('u.name') . ' LIKE :search2',
							],
							'OR'
						)
						->bind(':search1', $search)
						->bind(':search2', $search);
				}
				elseif (str_starts_with($search, 'username:'))
				{
					$search = '%' . trim(substr($search, 9)) . '%';

					$query
						->extendWhere(
							'AND',
							[
								$db->quoteName('a.username') . ' LIKE :search3',
								$db->quoteName('u.username') . ' LIKE :search4',
							],
							'OR'
						)
						->bind(':search3', $search)
						->bind(':search4', $search);
				}
				elseif (str_starts_with($search, 'email:'))
				{
					$search = '%' . trim(substr($search, 6)) . '%';

					$query
						->extendWhere(
							'AND',
							[
								$db->quoteName('u.email') . ' LIKE :search5',
							],
							'OR'
						)
						->bind(':search5', $search);
				}
				else
				{
					$search = '%' . $search . '%';

					$query
						->extendWhere(
							'AND',
							[
								$db->quoteName('a.name') . ' LIKE :search1',
								$db->quoteName('u.name') . ' LIKE :search2',
								$db->quoteName('a.username') . ' LIKE :search3',
								$db->quoteName('u.username') . ' LIKE :search4',
								$db->quoteName('u.email') . ' LIKE :search5',
							],
							'OR'
						)
						->bind(':search1', $search)
						->bind(':search2', $search)
						->bind(':search3', $search)
						->bind(':search4', $search)
						->bind(':search5', $search);
				}
			}
		}

		// Filter by type
		$type = $this->getState('filter.type');

		if (!empty($type))
		{
			$query->where($db->quoteName('type') . ' = :type')
				->bind(':type', $type);
		}

		// Filter by user_id
		$user_id = $this->getState('filter.user_id');

		if (is_int($user_id))
		{
			$user_id = (int) $user_id;
			$query->where($db->quoteName('user_id') . ' = :user_id')
				->bind(':user_id', $user_id, ParameterType::INTEGER);
		}

		// Filter by username (for virtual users only)
		$username = $this->getState('filter.username');

		if (!empty($username))
		{
			$query->where($db->quoteName('username') . ' = :username')
				->bind(':username', $username);

		}

		return $query;
	}

	/**
	 * Method to get a store id based on the model configuration state.
	 *
	 * @param   string  $id  An identifier string to generate the store id.
	 *
	 * @return  string  A store id.
	 *
	 * @since   2.0.0
	 */
	protected function getStoreId($id = '')
	{
		$id .= ':' . $this->getState('filter.search') ?? '';
		$id .= ':' . $this->getState('filter.type') ?? '';
		$id .= ':' . $this->getState('filter.user_id') ?? '';
		$id .= ':' . $this->getState('filter.username') ?? '';

		return parent::getStoreId($id);
	}

	/**
	 * Method to populate the model state.
	 *
	 * @param   string  $ordering   An optional ordering field.
	 * @param   string  $direction  An optional direction (asc|desc).
	 *
	 * @return  void
	 *
	 * @since   2.0.0
	 */
	protected function populateState($ordering = 'a.name', $direction = 'asc')
	{
		// Load the parameters.
		$this->setState('params', ComponentHelper::getParams('com_activitypub'));

		// List state information.
		parent::populateState($ordering, $direction);
	}


}