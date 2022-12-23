<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\Event;

defined('_JEXEC') || die;

use Joomla\CMS\Event\ReshapeArgumentsAware;
use Joomla\Event\Event;
use Joomla\Registry\Registry;

/**
 * Concrete class for the `onActivityPubSaveActor` event
 *
 * @since  2.0.0
 */
class SaveActorEvent extends Event
{
	use ReshapeArgumentsAware;

	/**
	 * Public constructor
	 *
	 * @param   array     $data    The incoming form data
	 * @param   Registry  $params  The params registry of the actor table object
	 *
	 * @since   2.0.0
	 */
	public function __construct(array $data, Registry $params)
	{
		$arguments = $this->reshapeArguments(
			[
				'data'   => $data,
				'params' => $params,
			],
			['data', 'params']
		);

		parent::__construct('onActivityPubSaveActor', $arguments);
	}

	/**
	 * Validator for the `data` argument
	 *
	 * @param   array  $data
	 *
	 * @return  array
	 * @since   2.0.0
	 */
	public function setData(array $data): array
	{
		return $data;
	}

	/**
	 * Validator for the `params` argument
	 *
	 * @param   Registry  $params
	 *
	 * @return  Registry
	 * @since   2.0.0
	 */
	public function setParams(Registry $params): Registry
	{
		return $params;
	}
}