<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Plugin\System\WebFinger\Event;

defined('_JEXEC') || die;

use Joomla\CMS\Event\AbstractImmutableEvent;
use Joomla\CMS\Event\ReshapeArgumentsAware;
use Joomla\CMS\Event\Result\ResultAware;
use Joomla\CMS\Event\Result\ResultAwareInterface;
use Joomla\CMS\Event\Result\ResultTypeObjectAware;
use Joomla\CMS\User\User;

class ResolveResource extends AbstractImmutableEvent implements ResultAwareInterface
{
	use ReshapeArgumentsAware;
	use ResultAware;
	use ResultTypeObjectAware;

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
			['resource']
		);

		$this->resultIsNullable = true;
		$this->resultAcceptableClasses = [
			User::class
		];

		parent::__construct('onWebFingerResolveResource', $arguments);
	}

	/**
	 * Validator for the 'resource' argument.
	 *
	 * @param   string  $value  The value being set
	 *
	 * @return  string
	 * @since   2.0.0
	 */
	public function setResource(string $value): string
	{
		if (empty($value))
		{
			throw new \DomainException(
				sprintf(
					'Argument \'resource\' of %s must be a non-empty string.',
					$this->name
				)
			);
		}

		return $value;
	}
}