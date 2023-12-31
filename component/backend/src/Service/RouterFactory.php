<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2024 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\Service;

\defined('_JEXEC') || die;

use Dionysopoulos\Component\ActivityPub\Site\Service\Router;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Component\Router\RouterFactory as BaseRouterFactory;
use Joomla\CMS\Component\Router\RouterInterface;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\CMS\MVC\Factory\MVCFactoryAwareTrait;

class RouterFactory extends BaseRouterFactory
{
	use MVCFactoryAwareTrait;

	public function createRouter(CMSApplicationInterface $application, AbstractMenu $menu): RouterInterface
	{
		/** @var Router $router */
		$router = parent::createRouter($application, $menu);

		$router->setMVCFactory($this->getMVCFactory());

		return $router;
	}
}