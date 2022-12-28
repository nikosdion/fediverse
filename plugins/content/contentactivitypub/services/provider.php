<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

defined('_JEXEC') || die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseDriver;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\Content\ContentActivityPub\Extension\ContentActivityPub;

return new class implements ServiceProviderInterface {
	/**
	 * Registers the service provider with a DI container.
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	public function register(Container $container)
	{
		$container->set(
			PluginInterface::class,
			function (Container $container) {
				$params     = (array) PluginHelper::getPlugin('content', 'fediverse');
				$dispatcher = $container->get(DispatcherInterface::class);
				$plugin     = new ContentActivityPub($dispatcher, $params);

				$plugin->setApplication(Factory::getApplication());
				$plugin->setDatabase($container->get(DatabaseDriver::class));

				return $plugin;
			}
		);
	}
};
