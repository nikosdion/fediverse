<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\Table;

\defined('_JEXEC') || die;

use Dionysopoulos\Component\ActivityPub\Administrator\Table\Mixin\AssertActorExistsTrait;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;

/**
 * Table class for `#__activitypub_queue`, activities to announce to remote servers' inboxes.
 *
 * @since  2.0.0
 */
class QueueTable extends Table
{
	use AssertActorExistsTrait;

	/**
	 * Auto-incrementing ID
	 *
	 * @var   int|null
	 * @since 2.0.0
	 */
	public ?int $id = null;

	/**
	 * The activity to post
	 *
	 * @var   string|null
	 * @since 2.0.0
	 */
	public ?string $activity = null;

	/**
	 * The inbox to post the activity to
	 *
	 * @var   string|null
	 * @since 2.0.0
	 */
	public ?string $inbox = null;

	/**
	 * The actor ID sending this queued action.
	 *
	 * This is a cascading foreign key. If our actor is removed before we deliver the activity, the queued activity
	 * will be automatically removed so the notification does not take place.
	 *
	 * @var   int|null
	 * @since 2.0.0
	 */
	public ?int $actor_id = null;

	/**
	 * The corresponding follower ID for this queued action.
	 *
	 * This is a cascading foreign key. If our actor is unfollowed before we deliver the activity, the queued activity
	 * will be automatically removed so the notification does not take place.
	 *
	 * @var   int|null
	 * @since 2.0.0
	 */
	public ?int $follower_id = null;

	/**
	 * Current retry count.
	 *
	 * The queued action can be retried another 10 times, each time increasing the next_try timestamp using an
	 * exponential back-off algorithm.
	 *
	 * @var   int|null
	 * @since 2.0.0
	 */
	public ?int $retry_count = 0;

	/**
	 * Date/time stamp when the activity should be posted next at the earliest
	 *
	 * @var   string|null
	 * @since 2.0.0
	 */
	public ?string $next_try = null;

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
		parent::__construct('#__activitypub_queue', 'id', $db);
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
		// The actor_id must be an existing actor; the follower_id must correspond to an existing Follower
		try
		{
			$this->assertActorExists($this->actor_id);
			$this->followerExists();
		}
		catch (Exception $e)
		{
			$this->setError($e->getMessage());

			return false;
		}

		foreach (['activity', 'inbox',] as $key
		)
		{
			if (empty($this->{$key}))
			{
				throw new \RuntimeException(Text::_('COM_ACTIVITYPUB_QUEUE_ERR_INVALID_' . $key));
			}
		}

		// Retry count must be an integer 0 to 10, inclusive
		if ($this->retry_count === null || $this->retry_count < 0 || $this->retry_count > 10)
		{
			$this->retry_count = min(10, max($this->retry_count, 0));
		}

		// Next try is calculated from the current date and time using the retry_count
		if (empty($this->next_try))
		{
			$this->next_try = Factory::getDate()
				->add(new \DateInterval('PT' . (3 ** $this->retry_count) . 'S'))
				->toSql();
		}

		return parent::check();
	}

	/**
	 * Bump the retry count.
	 *
	 * If the request has been tried 10 times it returns false. This should indicate that the request needs to be taken
	 * off the queue.
	 *
	 * @return  bool  Can the retry count be bumped?
	 * @throws  Exception
	 * @since   2.0.0
	 */
	public function bumpRetryCount(): bool
	{
		if ($this->retry_count >= 10)
		{
			return false;
		}

		$this->retry_count++;

		$this->next_try = Factory::getDate($this->next_try ?? 'now')
			->add(new \DateInterval('PT' . (3 ** $this->retry_count) . 'S'))
			->toSql();

		return true;
	}

	/**
	 * Make sure that the actor_id is non-empty and corresponds to an existing actor
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	private function followerExists(): void
	{
		if ($this->follower_id === null)
		{
			return;
		}

		$hasActor = $this->follower_id > 0;

		if ($hasActor)
		{
			$db       = $this->getDbo();
			$query    = $db->getQuery(true)
				->select('COUNT(*)')
				->from($db->quoteName('#__activitypub_followers'))
				->where($db->quoteName('id') . ' = :id')
				->bind(':id', $this->follower_id, ParameterType::INTEGER);
			$hasActor = ($db->setQuery($query)->loadResult() ?: 0) > 0;
		}

		if ($hasActor)
		{
			return;
		}

		throw new \RuntimeException(Text::_('COM_ACTIVITYPUB_QUEUE_ERR_INVALID_FOLLOWER'));
	}
}