<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

use Akeeba\Component\ATS\Administrator\Helper\Debug;
use Dionysopoulos\Component\ActivityPub\Administrator\Extension\ActivityPubComponent;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Log\Log;
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
		// Register the autoloader for the included ActivityPhp library
		$this->activityPubAutoloader();

		// Register the autoloader for Composer dependencies
		require_once __DIR__ . '/../vendor/autoload.php';

		// Register log files for the API application
		$this->registerFileLogger('activitypub.api');

		// Finally, get on with instantiating this extension
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

	/**
	 * Register a file logger for the given context if we have not already done so.
	 *
	 * If no file is specified a log file will be created, named after the context. For example, the context 'foo.bar'
	 * is logged to the file 'foo_bar.php' in Joomla's configured `logs` directory.
	 *
	 * The minimum log level to write to the file is determined by Joomla's debug flag. If you have enabled Site Debug
	 * the log level is JLog::All which log everything, including debug information. If Site Debug is disabled the
	 * log level is JLog::INFO which logs everything *except* debug information.
	 *
	 * @param   string       $context  The context to register
	 * @param   string|null  $file     The file to use for this context
	 *
	 * @return  void
	 *
	 * @since   2.0.0
	 */
	private function registerFileLogger(string $context, ?string $file = null): void
	{
		static $registeredLoggers = [];

		// Make sure we are not double-registering a logger
		$sig = md5($context . '.file');

		if (in_array($sig, $registeredLoggers))
		{
			return;
		}

		$registeredLoggers[] = $sig;

		/**
		 * If no file is specified we will create a filename based on the context.
		 *
		 * For example the context 'ats.cron' results in the log filename 'ats_cron.php'
		 */
		if (is_null($file))
		{
			$filter          = InputFilter::getInstance();
			$filteredContext = $filter->clean($context, 'cmd');
			$file            = str_replace('.', '_', $filteredContext) . '.php';
		}

		// Register the file logger
		$logLevel = $this->getJoomlaDebug() ? Log::ALL : Log::INFO;

		Log::addLogger(['text_file' => $file], $logLevel, [$context]);
	}

	/**
	 * Get Joomla's debug flag
	 *
	 * @return  bool
	 *
	 * @since   2.0.0
	 */
	private function getJoomlaDebug(): bool
	{
		// If the JDEBUG constant is defined return its value cast as a boolean
		if (defined('JDEBUG'))
		{
			return (bool) JDEBUG;
		}

		// Joomla 3 & 4 – go through the application object to get the application configuration value
		try
		{
			return (bool) (Factory::getApplication()->get('debug', 0));
		}
		catch (Throwable $e)
		{
			return false;
		}
	}

};
