<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Joomla\Plugin\System\WebFinger\Event;

defined('_JEXEC') || die;

use Joomla\CMS\Event\AbstractImmutableEvent;
use Joomla\CMS\Event\ReshapeArgumentsAware;
use Joomla\CMS\User\User;

/**
 * Concrete Event for the onWebFingerGetResource event.
 *
 * @since  2.0.0
 */
class GetResource extends AbstractImmutableEvent
{
	use ReshapeArgumentsAware;

	/**
	 * Public constructor
	 *
	 * @param   array{resource:string, rel:array, user:User}  $arguments  The arguments to pass
	 *
	 * @since  2.0.0
	 */
	public function __construct(array $arguments = [])
	{
		$this->reshapeArguments(
			$arguments,
			['resource', 'rel', 'user']
		);

		parent::__construct('onWebFingerGetResource', $arguments);
	}

	/**
	 * Validator for the 'resource' argument.
	 *
	 * @param   array{subject: array, aliases: array, properties: array, links: array}  $value  The value being set
	 *
	 * @return  array{subject: array, aliases: array, properties: array, links: array}
	 * @since   2.0.0
	 */
	public function setResource(array $value): array
	{
		if (empty($value))
		{
			throw new \DomainException(
				sprintf(
					'Argument \'resource\' of %s must be a non-empty array.',
					$this->name
				)
			);
		}

		if (!array_key_exists('subject', $value))
		{
			throw new \DomainException(
				sprintf(
					'Argument \'resource\' of %s must be an array which has the key \'subject\'.',
					$this->name
				)
			);
		}

		if (!empty(array_diff(array_keys($value), ['subject', 'aliases', 'properties', 'links'])))
		{
			throw new \DomainException(
				sprintf(
					'Argument \'resource\' of %s must be an array with the following allowed keys: \'subject\', \'aliases\', \'properties\', and \'links\'.',
					$this->name
				)
			);
		}

		return $value;
	}

	/**
	 * Validator for the 'rel' argument.
	 *
	 * @param   array  $value  The value being set
	 *
	 * @return  array
	 * @since   2.0.0
	 */
	public function setRel(array $value): array
	{
		if (empty($value))
		{
			return $value;
		}

		$isValid = array_reduce(
			$value,
			fn($carry, $item) => $carry || is_string($item),
			false
		);

		if (!$isValid)
		{
			throw new \DomainException(
				sprintf(
					'Argument \'rel\' of %s must be an empty array, or an array of strings.',
					$this->name
				)
			);
		}

		return $value;
	}

	/**
	 * Validator for the 'user' argument.
	 *
	 * @param   User  $value  The value being set
	 *
	 * @return  User
	 * @since   2.0.0
	 */
	public function setUser(User $value): User
	{
		return $value;
	}
}