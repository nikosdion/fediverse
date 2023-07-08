<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

defined('_JEXEC') || die;

use Dionysopoulos\Plugin\System\WebFinger\Extension\WebFinger;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;

return new class implements ServiceProviderInterface {
	public function register(Container $container)
	{
		$container->set(
			PluginInterface::class,
			function (Container $container) {
				$pluginsParams = (array) PluginHelper::getPlugin(type: 'system', plugin: 'webfinger');
				$dispatcher    = $container->get(DispatcherInterface::class);
				$plugin        = new WebFinger($dispatcher, $pluginsParams);

				$plugin->setApplication(application: Factory::getApplication());
				$plugin->setDatabase($container->get(\Joomla\Database\DatabaseDriver::class));

				return $plugin;
			}
		);
	}
};
