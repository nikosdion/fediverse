<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\Table;

defined('_JEXEC') || die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

/**
 * Table object for ActivityPub Actors
 *
 * @since  2.0.0
 */
class ActorTable extends Table
{
	/**
	 * Indicates that columns fully support the NULL value in the database
	 *
	 * @var    boolean
	 * @since  2.0.0
	 */
	protected $_supportNullValue = true;

	/**
	 * Auto-incrementing ID
	 *
	 * @var int|null
	 * @since 2.0.0
	 */
	public ?int $id = null;

	/**
	 * Corresponding concrete Joomla user ID
	 *
	 * @var int
	 * @since 2.0.0
	 */
	public int $user_id = 0;

	/**
	 * Actor type: Person, Organization, Service
	 *
	 * @var string
	 * @since 2.0.0
	 */
	public string $type = 'Person';

	/**
	 * Display name
	 *
	 * @var string
	 * @since 2.0.0
	 */
	public string $name = '';

	/**
	 * Display username
	 *
	 * @var string
	 * @since 2.0.0
	 */
	public string $username = '';

	/**
	 * Parameters to determine what will be exposed through ActivityPub. JSON-encoded object.
	 *
	 * @var string|null
	 * @since 2.0.0
	 */
	public ?string $params = '';

	/**
	 * Constructor
	 *
	 * @param   DatabaseDriver  $db  Database connector object
	 *
	 * @since   1.5
	 */
	public function __construct(DatabaseDriver $db)
	{
		parent::__construct('#__activitypub_actors', 'id', $db);
	}

	/**
	 * Method to perform validation on the property values before storing them to the database.
	 *
	 * @return  boolean  True when the data is valid.
	 *
	 * @since   2.0.0
	 */
	public function check(): bool
	{
		// The user_id must be 0 or correspond to an existing user
		if (!$this->checkUserId($this->user_id))
		{
			$this->setError(Text::_('COM_ACTIVITYPUB_ACTORS_ERR_INVALID_USER_ID'));

			return false;
		}

		// The type must be one of the allowed values
		if (!in_array($this->type, ['Person', 'Organization', 'Service']))
		{
			$this->setError(Text::_('COM_ACTIVITYPUB_ACTORS_ERR_INVALID_TYPE'));

			return false;
		}

		// If this is a concrete user we cannot have a name and username
		if ($this->user_id > 0)
		{
			$this->name = '';
			$this->username = '';
		}
		else
		{
			if ($this->name === '')
			{
				$this->setError(Text::_('COM_ACTIVITYPUB_ACTORS_ERR_INVALID_NAME'));

				return false;
			}

			if (!$this->isUniqueUsername($this->username))
			{
				$this->setError(Text::_('COM_ACTIVITYPUB_ACTORS_ERR_INVALID_USERNAME'));

				return false;
			}
		}

		// The params must be empty, or a JSON-encoded string
		$registry = new Registry($this->params);
		$this->params = $registry->toString();

		return parent::check();
	}

	/**
	 * Method to bind an associative array or object to the Table instance.
	 *
	 * @param   array|object  $src     An associative array or object to bind to the Table instance.
	 * @param   array|string  $ignore  An optional array or space separated list of properties to ignore while binding.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   2.0.0
	 * @throws  \InvalidArgumentException
	 */
	public function bind($src, $ignore = []): bool
	{
		if (isset($src['params']) && is_array($src['params']))
		{
			$registry = new Registry($src['params']);
			$src['params'] = $registry->toString();
		}

		return parent::bind($src, $ignore);
	}


	/**
	 * Is the user ID valid?
	 *
	 * @param   int  $user_id
	 *
	 * @return  bool
	 * @since   2.0.0
	 */
	private function checkUserId(int $user_id): bool
	{
		if ($user_id === 0) {
			return true;
		}

		$db = $this->getDbo();
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__users'))
			->where($db->quoteName('id') . ' = :id')
			->bind(':id', $user_id, ParameterType::INTEGER);

		$numberOfUsers = $db->setQuery($query)->loadResult();

		return $numberOfUsers !== 0;
	}

	/**
	 * Is the username unique among all users?
	 *
	 * @param   string  $username
	 *
	 * @return  bool
	 * @since   2.0.0
	 */
	private function isUniqueUsername(string $username): bool
	{
		$username = trim($username);

		if (empty($username))
		{
			return false;
		}

		$db = $this->getDbo();
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__users'))
			->where($db->quoteName('username') . ' = :username')
			->bind(':username', $username);

		$numberOfUsers = $db->setQuery($query)->loadResult();

		return $numberOfUsers === 0;
	}
}