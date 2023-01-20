<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

/**
 * @package     Dionysopoulos\Component\ActivityPub\Administrator\Service\Provider
 * @subpackage
 *
 * @copyright   A copyright
 * @license     A "Slug" license name e.g. GPL2
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\Service\Provider;

defined('_JEXEC') || die;

use Joomla\CMS\Component\Router\RouterFactoryInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;

/**
 * Router Factory Service Provider
 *
 * @since       2.0.0
 */
class RouterFactory implements \Joomla\DI\ServiceProviderInterface
{
	/**
	 * Constructor.
	 *
	 * @param   string  $namespace  The namespace of the component
	 *
	 * @since   2.0.0
	 */
	public function __construct(private string $namespace)
	{
	}

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
			RouterFactoryInterface::class,
			function (Container $container) {
				return new \Dionysopoulos\Component\ActivityPub\Administrator\Service\RouterFactory(
					namespace: $this->namespace,
					db: $container->get(DatabaseInterface::class),
					factory: $container->get(MVCFactoryInterface::class)
				);
			}
		);
	}
}