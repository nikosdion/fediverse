<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2024 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\Event;

\defined('_JEXEC') || die;

use ActivityPhp\Type\AbstractObject;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\ActorTable;
use Joomla\CMS\Event\AbstractImmutableEvent;
use Joomla\CMS\Event\Result\ResultAware;
use Joomla\CMS\Event\Result\ResultAwareInterface;
use Joomla\CMS\Event\Result\ResultTypeBooleanAware;

/**
 * Concrete event class for the `onActivityPubHandleActivity` event.
 *
 * Handles an activity POSTed to the inbox.
 *
 * @since  2.0.0
 */
class HandleActivity extends AbstractImmutableEvent implements ResultAwareInterface
{
	use ResultAware;
	use ResultTypeBooleanAware;

	public const TARGET_INBOX = 'inbox';

	public const TARGET_OUTBOX = 'outbox';

	/**
	 * Public constructor.
	 *
	 * @param   AbstractObject  $activity  The Activity posted to the user's inbox or outbox
	 * @param   ActorTable      $actor     The ActorTable object for which the Activity is handled
	 * @param   string          $target    The POST target: inbox or outbox
	 *
	 * @since   2.0.0
	 */
	public function __construct(AbstractObject $activity, ActorTable $actor, string $target = self::TARGET_OUTBOX)
	{
		$arguments = [
			'activity' => $activity,
			'actor'    => $actor,
			'target'   => $target,
		];

		parent::__construct('onActivityPubHandleActivity', $arguments);
	}

	/**
	 * Validator for the `actor` argument.
	 *
	 * @param   ActorTable  $actor  The value to validate
	 *
	 * @return  ActorTable
	 * @since   2.0.0
	 */
	public function setActor(ActorTable $actor): ActorTable
	{
		return $actor;
	}

	/**
	 * Validator for the `activity` argument.
	 *
	 * @param   AbstractObject  $activity
	 *
	 * @return  AbstractObject
	 * @since   2.0.0
	 */
	public function setActivity(AbstractObject $activity): AbstractObject
	{
		return $activity;
	}

	/**
	 * Validator for the `target` argument.
	 *
	 * @param   string  $target
	 *
	 * @return  string
	 * @since   2.0.0
	 */
	public function setTarget(string $target): string
	{
		if (!in_array($target, [self::TARGET_INBOX, self::TARGET_OUTBOX]))
		{
			throw new \RuntimeException(
				sprintf("Invalid target in %s event", $this->getName())
			);
		}

		return $target;
	}
}