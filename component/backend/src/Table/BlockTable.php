<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2024 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\Table;

\defined('_JEXEC') || die;

use Dionysopoulos\Component\ActivityPub\Administrator\Table\Mixin\AssertActorExistsTrait;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;

/**
 * Table class for `#__activitypub_block`, which remote Actors are blocked by local Actors.
 *
 * @since  2.0.0
 */
class BlockTable extends Table
{
	use AssertActorExistsTrait;

	/**
	 * Auto-incrementing ID
	 *
	 * @var int|null
	 * @since 2.0.0
	 */
	public ?int $id = null;

	/**
	 * Foreign key to the local Actor which expresses their block preference
	 *
	 * @var   int|null
	 * @since 2.0.0
	 */
	public ?int $actor_id = null;

	/**
	 * The username of the remote Actor being blocked
	 *
	 * @var   string
	 * @since 2.0.0
	 */
	public ?string $username = '';

	/**
	 * The domain name (host) of the remote Actor being blocked
	 *
	 * @var   string
	 * @since 2.0.0
	 */
	public ?string $domain = '';

	/**
	 * Indicates that columns fully support the NULL value in the database
	 *
	 * @var    boolean
	 * @since  2.0.0
	 */
	protected $_supportNullValue = true;

	/**
	 * Constructor
	 *
	 * @param   DatabaseDriver  $db  Database connector object
	 *
	 * @since   1.5
	 */
	public function __construct(DatabaseDriver $db)
	{
		parent::__construct('#__activitypub_block', 'id', $db);
	}

	/**
	 * Method to perform validation on the property values before storing them to the database.
	 *
	 * @return  boolean  True when the data is valid.
	 *
	 * @since   2.0.0
	 */
	public function check()
	{
		// The actor_id must correspond to an existing actor
		try
		{
			$this->assertActorExists($this->actor_id);
		}
		catch (\Exception $e)
		{
			$this->setError($e->getMessage());

			return false;
		}

		foreach (['username', 'domain'] as $key)
		{
			if (empty($this->{$key}))
			{
				$this->setError(
					sprintf(
						'Property %s cannot be empty in table %s',
						$key, __CLASS__
					)

				);

				return false;
			}
		}

		return parent::check();
	}

	public function store($updateNulls = false)
	{
		$isSaved = parent::store($updateNulls);

		// Remove matching followers
		if ($isSaved)
		{
			$db          = $this->getDbo();
			$query = $db->getQuery(true)
				->delete($db->quoteName('#__activitypub_followers'))
				->where([
					$db->quoteName('actor_id') . ' = :actorId',
					$db->quoteName('username') . ' = :username',
					$db->quoteName('domain') . ' = :domain',
				])
				->bind(':actor_id', $this->actor_id, ParameterType::INTEGER)
				->bind(':username', $this->username)
				->bind(':domain', $this->domain);

			try
			{
				// Deleting the followers will cascade-delete the relevant queued activity notifications as well
				$db->setQuery($query)->execute();
			}
			catch (\Exception $e)
			{
				// No problem if it fails
			}
		}

		return $isSaved;
	}


}