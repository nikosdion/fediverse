<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Joomla\Plugin\System\WebFinger\Extension;

defined('_JEXEC') || die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;

/**
 * WebFinger protocol (RFC 7033) for Joomla!
 *
 * @since  2.0.0
 */
class WebFinger extends CMSPlugin implements SubscriberInterface, DatabaseAwareInterface
{
	use DatabaseAwareTrait;
	use WebFingerTrait;
	use UserFieldTrait;

	/**
	 * Returns an array of events this subscriber will listen to.
	 *
	 * @return  array
	 *
	 * @since   2.0.0
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onAfterInitialise'    => 'routeWebFingerRequest',
			'onContentPrepareForm' => 'loadUserForm',
			'onContentPrepareData' => 'loadFieldData',
			'onUserAfterSave'      => 'saveUserFields',
			'onUserAfterDelete'    => 'deleteFieldData',
		];
	}
}