<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\Service\Provider;

\defined('_JEXEC') || die;

use Dionysopoulos\Component\ActivityPub\Administrator\Service\RouterFactory;
use Joomla\CMS\Component\Router\RouterFactoryInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

class RouterFactoryProvider implements ServiceProviderInterface
{
	public function __construct(private string $namespace)
	{
	}

	public function register(Container $container)
	{
		$container->set(
			RouterFactoryInterface::class,
			function (Container $container) {
				$routerFactory = new RouterFactory(
					$this->namespace,
					null,
					$container->get(DatabaseInterface::class)
				);

				$routerFactory->setMVCFactory($container->get(MVCFactoryInterface::class));

				return $routerFactory;
			}
		);
	}


}