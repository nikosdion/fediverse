<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

use Akeeba\Component\ATS\Administrator\Helper\Debug;
use Dionysopoulos\Component\ActivityPub\Administrator\Extension\ActivityPubComponent;
use Dionysopoulos\Component\ActivityPub\Administrator\Service\Provider\RouterFactoryProvider;
use Dionysopoulos\Component\ActivityPub\Administrator\Traits\RegisterFileLoggerTrait;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Component\Router\RouterFactoryInterface;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseDriver;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class implements ServiceProviderInterface {
	use RegisterFileLoggerTrait;

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
		// Register the autoloader for the included ActivityPhp library
		$this->activityPubAutoloader();

		// Register the autoloader for Composer dependencies
		require_once __DIR__ . '/../vendor/autoload.php';

		// Register log files for the API application
		$this->registerFileLogger('activitypub.api');

		// Finally, get on with instantiating this extension
		$container->registerServiceProvider(new MVCFactory('Dionysopoulos\\Component\\ActivityPub'));
		$container->registerServiceProvider(new ComponentDispatcherFactory('Dionysopoulos\\Component\\ActivityPub'));
		$container->registerServiceProvider(new RouterFactoryProvider('Dionysopoulos\\Component\\ActivityPub'));

		$container->set(
			ComponentInterface::class,
			function (Container $container) {
				$component = new ActivityPubComponent($container->get(ComponentDispatcherFactoryInterface::class));

				$component->setMVCFactory($container->get(MVCFactoryInterface::class));
				$component->setRouterFactory($container->get(RouterFactoryInterface::class));

				$this->updateMagicParameters($container);

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

	private function updateMagicParameters(Container $c)
	{
		if (Factory::getApplication()->isClient('cli'))
		{
			return;
		}

		$cParams = ComponentHelper::getParams('com_activitypub');
		$siteURL = $cParams->get('siteurl', null);

		if ($siteURL === Uri::root(false))
		{
			return;
		}

		$cParams->set('siteurl', Uri::root(false));

		/** @var DatabaseDriver $db */
		$db   = $c->get('DatabaseDriver');
		$data = $cParams->toString('JSON');

		$query = $db->getQuery(true)
			->update($db->qn('#__extensions'))
			->set($db->qn('params') . ' = ' . $db->q($data))
			->where($db->qn('element') . ' = ' . $db->quote('com_activitypub'))
			->where($db->qn('type') . ' = ' . $db->quote('component'));

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (\Exception $e)
		{
			// Don't sweat if it fails
		}

		try
		{
			$refClass = new ReflectionClass(ComponentHelper::class);
			$refProp  = $refClass->getProperty('components');
			$refProp->setAccessible(true);
			$components                            = $refProp->getValue();
			$components['com_activitypub']->params = $cParams;
			$refProp->setValue($components);
		}
		catch (Exception $e)
		{
			// If it fails, it fails.
		}
	}
};
