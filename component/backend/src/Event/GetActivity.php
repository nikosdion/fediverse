<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\Event;

\defined('_JEXEC') || die;

use ActivityPhp\Server\Actor;
use ActivityPhp\Type\Core\Activity;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\ActorTable;
use InvalidArgumentException;
use Joomla\CMS\Event\AbstractImmutableEvent;
use Joomla\CMS\Event\Result\ResultAware;
use Joomla\CMS\Event\Result\ResultAwareInterface;

class GetActivity extends AbstractImmutableEvent implements ResultAwareInterface
{
	use ResultAware;

	/**
	 * Public constructor.
	 *
	 * @param   ActorTable  $actor    The ActorTable object for which Activities are returned
	 * @param   string      $context  The context, format extension.subType, e.g. com_content.article
	 * @param   array       $ids      The list of IDs of activities to return
	 *
	 * @since   2.0.0
	 */
	public function __construct(ActorTable $actor, string $context, array $ids)
	{
		$arguments = [
			'actor'   => $actor,
			'context' => $context,
			'ids'     => $ids,
		];

		parent::__construct('onActivityPubGetActivity', $arguments);
	}

	/**
	 * Validator for the `actor` argument.
	 *
	 * @param   Actor  $actor  The value to validate
	 *
	 * @return  Actor
	 * @since   2.0.0
	 */
	public function setActor(ActorTable $actor)
	{
		return $actor;
	}

	/**
	 * Validator for the `context` argument.
	 *
	 * @param   string  $context  The value to validate
	 *
	 * @return  string
	 * @since   2.0.0
	 */
	public function setContext(string $context)
	{
		$parts = explode('.', $context);

		if (count($parts) !== 2)
		{
			throw new InvalidArgumentException(
				sprintf(
					'Event %s only accepts a context parameter which is in the format extensionName.subtype.',
					$this->getName()
				)
			);
		}

		[$extension, $subType] = $parts;

		if ($extension !== strtolower($extension))
		{
			throw new InvalidArgumentException(
				sprintf(
					'Event %s only accepts a context parameter which is in the format extensionName.subtype; the extensionName must be all lowercase.',
					$this->getName()
				)
			);
		}

		if (
			!str_starts_with($extension, 'com_')
			&& !str_starts_with($extension, 'plg_')
			&& !str_starts_with($extension, 'mod_')
			&& !str_starts_with($extension, 'lib_')
			&& !str_starts_with($extension, 'pkg_')
			&& !str_starts_with($extension, 'tpl_')
			&& !str_starts_with($extension, 'files_')
		)
		{
			throw new InvalidArgumentException(
				sprintf(
					'Event %s only accepts a context parameter which is in the format extensionName.subtype; the extensionName must be a valid Joomla! extension name (it does not have to be installed, though).',
					$this->getName()
				)
			);
		}

		return $context;
	}

	/**
	 * Validator for the `ids` argument
	 *
	 * @param   array  $ids  The value to validate
	 *
	 * @return  array
	 * @since   2.0.0
	 */
	public function setIds(array $ids)
	{
		return $ids;
	}

	/**
	 * Validator for the `result` argument (event return).
	 *
	 * @param   mixed  $data  The value of the result argument.
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	public function typeCheckResult($data): void
	{
		if (!is_array($data))
		{
			throw new InvalidArgumentException(sprintf('Event %s only accepts Array results whose members are objects of the %s type or null, and its keys are in the format “CONTEXT.ID” where ID is one if the identifiers given in the ids argument.', $this->getName(), Activity::class));
		}

		// Check keys
		$context = $this->getArgument('context');
		$keys    = array_keys($data);
		$isValid = array_reduce(
			$keys,
			fn($carry, $item) => $carry && str_starts_with($item, $context . '.') && in_array(substr($item, strlen($context) + 1), $this->getArgument('ids')),
			true
		);

		// Check value types
		$isValid = $isValid && array_reduce(
				$data,
				fn($carry, $item) => $carry && ($item instanceof Activity || $item === null),
				true
			);

		if (!$isValid)
		{
			throw new InvalidArgumentException(sprintf('Event %s only accepts Array results whose members are objects of the %s type or null, and its keys are in the format “CONTEXT.ID” where ID is one if the identifiers given in the ids argument.', $this->getName(), Activity::class));
		}
	}


}