<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\Event;

defined('_JEXEC') || die;

use Dionysopoulos\Component\ActivityPub\Administrator\Table\ActorTable;
use Joomla\CMS\Event\AbstractImmutableEvent;
use Joomla\CMS\Event\Result\ResultAware;
use Joomla\CMS\Event\Result\ResultAwareInterface;
use Joomla\CMS\Event\Result\ResultTypeObjectAware;
use Joomla\Database\DatabaseQuery;
use Joomla\Database\QueryInterface;

/**
 * Concrete event class for the `onActivityPubGetActivityListQuery` event.
 *
 * Plugins return a query which is used by the API application to retrieve the activity stream for a given actor.
 *
 * @since  2.0.0
 */
class GetActivityListQuery extends AbstractImmutableEvent implements ResultAwareInterface
{
	use ResultAware;
	use ResultTypeObjectAware;

	/**
	 * Public constructor.
	 *
	 * @param   ActorTable  $actor  The Actor table object for which an activity list query will be returned.
	 *
	 * @since   2.0.0
	 */
	public function __construct(ActorTable $actor)
	{
		$arguments = [
			'actor' => $actor,
		];

		$this->resultAcceptableClasses = [
			QueryInterface::class,
			DatabaseQuery::class
		];

		parent::__construct('onActivityPubGetActivityListQuery', $arguments);
	}

	/**
	 * Type validation for the `actor` argument.
	 *
	 * @param   ActorTable  $actor
	 *
	 * @return  ActorTable
	 * @since   2.0.0
	 */
	public function setActor(ActorTable $actor)
	{
		return $actor;
	}
}