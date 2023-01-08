<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\Event;

\defined('_JEXEC') || die;

use ActivityPhp\Type\AbstractObject;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\ActorTable;
use InvalidArgumentException;
use Joomla\CMS\Event\AbstractImmutableEvent;
use Joomla\CMS\Event\Result\ResultAware;
use Joomla\CMS\Event\Result\ResultAwareInterface;
use Joomla\CMS\Event\Result\ResultTypeObjectAware;

class GetObject extends AbstractImmutableEvent implements ResultAwareInterface
{
	use ResultAware;
	use ResultTypeObjectAware;

	/**
	 * Public constructor.
	 *
	 * @param   ActorTable  $actor    The ActorTable object for which Activities are returned
	 * @param   string      $context  The context, format extension.subType, e.g. com_content.article
	 * @param   int         $id       The ID of the object to get (the `#__activitypub_objects` PK)
	 *
	 * @since   2.0.0
	 */
	public function __construct(ActorTable $actor, string $context, int $id)
	{
		$arguments = [
			'actor'   => $actor,
			'context' => $context,
			'id'      => $id,
		];

		$this->resultAcceptableClasses = [
			AbstractObject::class,
		];

		parent::__construct('onActivityPubGetObject', $arguments);
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
	 * Validator for the `context` argument.
	 *
	 * @param   string  $context  The value to validate
	 *
	 * @return  string
	 * @since   2.0.0
	 */
	public function setContext(string $context): string
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
	 * Validator for the `id` argument
	 *
	 * @param   int  $id
	 *
	 * @return  int
	 * @since   2.0.0
	 */
	public function setId(int $id): int
	{
		return $id;
	}
}