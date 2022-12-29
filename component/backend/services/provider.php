<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

use Dionysopoulos\Component\ActivityPub\Administrator\Extension\ActivityPubComponent;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

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
		$this->activityPubAutoloader();

		$container->registerServiceProvider(new MVCFactory('Dionysopoulos\\Component\\ActivityPub'));
		$container->registerServiceProvider(new ComponentDispatcherFactory('Dionysopoulos\\Component\\ActivityPub'));

		$container->set(
			ComponentInterface::class,
			function (Container $container) {
				$component = new ActivityPubComponent($container->get(ComponentDispatcherFactoryInterface::class));

				$component->setMVCFactory($container->get(MVCFactoryInterface::class));

				return $component;
			}
		);
	}

	/**
	 * Register a PSR-4 autoloader for our ActivityPhp fork.
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	private function activityPubAutoloader()
	{
		static $isLoaded = false;

		if ($isLoaded)
		{
			return;
		}

		$isLoaded = true;

		if (class_exists(\ActivityPhp\Server\Actor::class))
		{
			return;
		}

		JLoader::registerNamespace('ActivityPhp', realpath(__DIR__ . '/../src/ActivityPhp'));

		require_once __DIR__ . '/dialect.php';
	}
};
