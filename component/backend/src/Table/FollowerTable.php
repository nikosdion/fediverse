<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2024 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\Table;

\defined('_JEXEC') || die;

use Dionysopoulos\Component\ActivityPub\Administrator\Table\Mixin\AssertActorExistsTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

/**
 * Table class for `#__activitypub_followers`, which remote Actors follow which local Actors.
 *
 * @since 2.0.0
 */
class FollowerTable extends Table
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
	 * Foreign key to the local Actor which is being followed
	 *
	 * @var   int|null
	 * @since 2.0.0
	 */
	public ?int $actor_id = null;

	/**
	 * ID (and URL) of the remove actor who's following the local actor
	 *
	 * @var   string|null
	 * @since 2.0.0
	 */
	public ?string $follower_actor = '';

	/**
	 * Remote actor's username
	 *
	 * @var   string|null
	 * @since 2.0.0
	 */
	public ?string $username = '';

	/**
	 * Remote actor's domain name
	 *
	 * @var   string|null
	 * @since 2.0.0
	 */
	public ?string $domain = '';

	/**
	 * Follow activity ID.
	 *
	 * This is used to locate the follow when an unfollow (Undo Follow) request is received.
	 *
	 * @var   string|null
	 * @since 2.0.0
	 */
	public ?string $follow_id = null;

	/**
	 * URL to the remote actor's inbox
	 *
	 * @var   string|null
	 * @since 2.0.0
	 */
	public ?string $inbox = null;

	/**
	 * URL to the remote actor's shared inbox, if one exists.
	 *
	 * @var   string|null
	 * @since 2.0.0
	 */
	public ?string $shared_inbox = null;

	/**
	 * Date/time stamp when the follow request was accepted
	 *
	 * @var   string|null
	 * @since 2.0.0
	 */
	public ?string $created = null;

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
		parent::__construct('#__activitypub_followers', 'id', $db);
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

		foreach (['follower_actor', 'username', 'domain', 'follow_id', 'inbox',] as $key
		)
		{
			if (empty($this->{$key}))
			{
				$this->setError(Text::_('COM_ACTIVITYPUB_FOLLOWERS_ERR_INVALID_' . $key));

				return false;
			}
		}

		// The created column must be populated
		if (empty($this->created))
		{
			$this->created = Factory::getDate()->toSql();
		}

		return parent::check();
	}
}