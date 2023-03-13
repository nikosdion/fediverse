<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\Table;

\defined('_JEXEC') || die;

use ActivityPhp\Type;
use ActivityPhp\Type\Core\AbstractActivity;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\Mixin\AssertActorExistsTrait;
use Joomla\CMS\Event\AbstractEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

class OutboxTable extends Table
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
	 * The actor ID corresponding to this activity
	 *
	 * @var   int|null
	 * @since 2.0.0
	 */
	public ?int $actor_id = null;

	/**
	 * The activity's JSON document
	 *
	 * @var   string|null
	 * @since 2.0.0
	 */
	public ?string $activity = null;

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
		parent::__construct('#__activitypub_outbox', 'id', $db);
	}

	/**
	 * Create an OutboxTable object from an Activity.
	 *
	 * @param   int               $actorId   The actor ID who owns the activity record
	 * @param   AbstractActivity  $activity  The activity to use
	 *
	 * @return  static
	 * @throws  \Exception
	 *
	 * @since  2.0.0
	 */
	public static function fromActivity(int $actorId, AbstractActivity $activity): self
	{
		$data = [
			'id'       => (new \DateTime('now', new \DateTimeZone('GMT')))->format('Uv'),
			'activity' => $activity->toJson(),
			'actor_id' => $actorId,
		];

		/** @var self $table */
		$table = Factory::getApplication()->bootComponent('com_activitypub')
			->getMVCFactory()
			->createTable('Outbox', 'Administrator');

		if (!$table->bind($data))
		{
			throw new \RuntimeException('Cannot create table from activity');
		}

		return $table;
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
		if (empty($this->id))
		{
			$this->id = (new \DateTime('now', new \DateTimeZone('GMT')))->format('Uv');
		}

		try
		{
			$this->assertActorExists($this->actor_id);
		}
		catch (\Exception $e)
		{
			$this->setError($e->getMessage());

			return false;
		}

		if (empty($this->activity))
		{
			$this->setError('You must provide the activity to save in the outbox');

			return false;
		}

		try
		{
			Type::fromJson($this->activity);
		}
		catch (\Exception $e)
		{
			$this->setError('Invalid activity JSON');

			return false;
		}

		return parent::check();
	}

	/**
	 * Method to store a row in the database from the Table instance properties.
	 *
	 * We have overridden this because we always have a primary key set, even for new records. As a result we cannot use
	 * Joomla's internal login for when to use an INSERT INTO or UPDATE SQL command; we will instead use REPLACE INTO
	 * which resolves to an INSERT or UPDATE depending on whether the PK already exists.
	 *
	 * @param   boolean  $updateNulls  True to update fields even if they are null.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   2.0.0
	 */
	public function store($updateNulls = true)
	{
		$k = $this->_tbl_keys;

		// Pre-processing by observers
		$event = AbstractEvent::create(
			'onTableBeforeStore',
			[
				'subject'     => $this,
				'updateNulls' => $updateNulls,
				'k'           => $k,
			]
		);
		$this->getDispatcher()->dispatch('onTableBeforeStore', $event);

		/**
		 * We will always use a `REPLACE INTO` statement. We cannot rely on the existence of a primary key to determine
		 * whether this is a new record.
		 */
		try
		{
			$db    = $this->getDbo();
			$query = $db->getQuery(true)
				->insert($db->quoteName($this->_tbl))
				->columns([
					$db->quoteName('id'),
					$db->quoteName('actor_id'),
					$db->quoteName('activity'),
				])
				->values(
					implode(
						',',
						[
							(int) $this->id,
							(int) $this->actor_id,
							$db->quote($this->activity),
						]
					)
				);

			$sql    = 'REPLACE' . substr((string) $query, 7);
			$result = $db->setQuery($sql)->execute();
		}
		catch (\Exception $e)
		{
			$this->setError($e->getMessage());
			$result = false;
		}

		// Post-processing by observers
		$event = AbstractEvent::create(
			'onTableAfterStore',
			[
				'subject' => $this,
				'result'  => &$result,
			]
		);
		$this->getDispatcher()->dispatch('onTableAfterStore', $event);

		return $result;
	}
}