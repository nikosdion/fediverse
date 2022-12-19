<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

defined('_JEXEC') || die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\Content\Fediverse\Extension\Fediverse;
use Joomla\Plugin\Content\Fediverse\Service\TootLoader;
use Joomla\Registry\Registry;

return new class implements ServiceProviderInterface {
	/**
	 * Registers the service provider with a DI container.
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function register(Container $container)
	{
		$container->set(
			PluginInterface::class,
			function (Container $container) {
				$pluginsParams = (array) PluginHelper::getPlugin('content', 'fediverse');
				$dispatcher    = $container->get(DispatcherInterface::class);
				$params        = new Joomla\Registry\Registry($pluginsParams['params']);

				/** @var \Joomla\CMS\Application\CMSApplication $app */
				$app = Factory::getApplication();

				$optionsSource = function (Registry $params): Registry {
					$optionsSource = [
						'userAgent' => 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:41.0) Gecko/20100101 Firefox/41.0',
					];

					if (!empty($customCertificate = $params->get('custom_certificate', null)))
					{
						$optionsSource['curl']   = [
							'certpath' => $customCertificate,
						];
						$optionsSource['stream'] = [
							'certpath' => $customCertificate,
						];
					}

					return new Registry($optionsSource);
				};

				$plugin = new Fediverse(
					$dispatcher,
					$pluginsParams,
					tootLoader: new TootLoader(
						http          : (new HttpFactory())->getHttp($optionsSource($params)),
						app           : $app,
						cacheLifetime : $params->get('toot_cache_lifetime', 120),
						requestTimeout: (int) $params->get('get_timeout', 5),
						useCaching    : $params->get('cache_toot', 1) == 1,
					)
				);

				$plugin->setApplication($app);

				return $plugin;
			}
		);
	}
};
