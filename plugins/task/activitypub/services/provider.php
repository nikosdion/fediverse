<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2010-2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

defined('_JEXEC') or die;

use Dionysopoulos\Plugin\Task\ActivityPub\Extension\ActivityPub;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;

return new class implements ServiceProviderInterface {
	/**
	 * Registers the service provider with a DI container.
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  void
	 *
	 * @since   2.0.0
	 */
	public function register(Container $container)
	{
		$container->set(
			PluginInterface::class,
			function (Container $container) {
				$params     = (array) PluginHelper::getPlugin('task', 'activitypub');
				$dispatcher = $container->get(DispatcherInterface::class);

				$plugin = new ActivityPub($dispatcher, $params);

				$plugin->setApplication(Factory::getApplication());
				$plugin->setDatabase($container->get('DatabaseDriver'));

				return $plugin;
			}
		);
	}
};
