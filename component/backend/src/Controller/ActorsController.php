<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\Controller;

defined('_JEXEC') || die;

use Joomla\CMS\MVC\Controller\AdminController;

class ActorsController extends AdminController
{
	protected $text_prefix = 'COM_ACTIVITYPUB_ACTORS';

	public function getModel($name = 'Actor', $prefix = 'Administrator', $config = ['ignore_request' => true])
	{
		return parent::getModel($name, $prefix, $config);
	}
}