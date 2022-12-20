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
use Joomla\CMS\Form\Form;

/**
 * Concrete Event for the onWebFingerLoadUserForm event.
 *
 * @since  2.0.0
 */
class LoadUserForm extends AbstractImmutableEvent
{
	use ReshapeArgumentsAware;

	/**
	 * Public constructor
	 *
	 * @param   array{form:Form}  $arguments  The arguments to pass
	 *
	 * @since  2.0.0
	 */
	public function __construct(array $arguments = [])
	{
		$arguments = $this->reshapeArguments(
			$arguments,
			[
				'form',
			]
		);

		parent::__construct('onWebFingerLoadUserForm', $arguments);
	}

	/**
	 * Validator for the `form` argument
	 *
	 * @param   Form  $value  The value being set to the argument
	 *
	 * @return  Form
	 * @since   2.0.0
	 */
	public function setForm(Form $value): Form
	{
		return $value;
	}
}