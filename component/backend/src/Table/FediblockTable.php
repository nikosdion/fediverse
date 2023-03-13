<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\Table;

\defined('_JEXEC') || die;

use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

/**
 * Table class for `#__activitypub_fediblock`, which servers are completely blocked from interacting with this server.
 *
 * @since  2.0.0
 */
class FediblockTable extends Table
{
	/**
	 * Auto-incrementing ID
	 *
	 * @var int|null
	 * @since 2.0.0
	 */
	public ?int $id = null;

	/**
	 * The domain name (host) of the remote Actor being blocked
	 *
	 * @var   string
	 * @since 2.0.0
	 */
	public ?string $domain = '';

	/**
	 * Note on why this domain is blocked
	 *
	 * @var   string|null
	 * @since 2.0.0
	 */
	public ?string $note = '';

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
		parent::__construct('#__activitypub_fediblock', 'id', $db);
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
		if (empty($this->domain))
		{
			$this->setError(sprintf('Property domain cannot be empty in table %s', __CLASS__));

			return false;
		}

		return parent::check();
	}

	public function store($updateNulls = false)
	{
		$isSaved = parent::store($updateNulls);

		// Remove followers and queued activities against the given domain
		if ($isSaved)
		{
			$db    = $this->getDbo();

			// Delete followers
			$query = $db->getQuery(true)
				->delete($db->quoteName('#__activitypub_followers'))
				->where($db->quoteName('domain') . ' = :domain')
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

			// Delete queued activity notifications
			$query       = $db->getQuery(true)
				->delete($db->quoteName('#__activitypub_queue', 'q'))
				->where([
					$db->quoteName('q.inbox') . ' LIKE ' . $db->quote('https://' . $this->domain . '/%'),
					$db->quoteName('q.inbox') . ' LIKE ' . $db->quote('http://' . $this->domain . '/%'),
				], 'OR');

			try
			{
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