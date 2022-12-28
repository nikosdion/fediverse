<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Api\Controller;

defined('_JEXEC') or die;

use Dionysopoulos\Component\ActivityPub\Api\Controller\Mixin\DisplayItemTrait;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\View\JsonApiView;

/**
 * Controller for ActivityPub Actor interactions
 *
 * @since  2.0.0
 */
class ActorController extends BaseController
{
	protected string $contentType = 'actor';

	use DisplayItemTrait;
}