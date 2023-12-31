<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2024 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\Dispatcher;

defined('_JEXEC') || die;

use Dionysopoulos\Component\ActivityPub\Administrator\Mixin\TriggerEventTrait;
use Joomla\CMS\Dispatcher\ComponentDispatcher;

class Dispatcher extends ComponentDispatcher
{
	use TriggerEventTrait;

	/**
	 * The default controller (and view), if none is specified in the request.
	 *
	 * @var   string
	 * @since 2.0.0
	 */
	protected string $defaultController = 'actors';

	/**
	 * Dispatch a controller task. Redirecting the user if appropriate.
	 *
	 * @return  void
	 *
	 * @since   2.0.0
	 */
	public function dispatch()
	{
		// Check the minimum supported PHP version
		$minPHPVersion = '8.0.0';
		$softwareName  = 'ActivityPub';

		if (version_compare(PHP_VERSION, $minPHPVersion, 'lt'))
		{
			die(sprintf('%s requires PHP %s or later', $softwareName, $minPHPVersion));
		}

		try
		{
			$this->triggerEvent('onBeforeDispatch');

			parent::dispatch();

			// This will only execute if there is no redirection set by the Controller
			$this->triggerEvent('onAfterDispatch');
		}
		catch (\Exception $e)
		{
			$title = 'ActivityPub';
			$isPro = false;

			// Frontend: forwards errors 401, 403 and 404 to Joomla
			if (in_array($e->getCode(), [401, 403, 404]) && $this->app->isClient('site'))
			{
				throw $e;
			}

			if (!(include_once JPATH_ADMINISTRATOR . '/components/com_activitypub/tmpl/common/errorhandler.php'))
			{
				throw $e;
			}
		}
	}

	/**
	 * Loads the language files for this component.
	 *
	 * Always loads the backend translation file. In the site, CLI and API applications it also loads the frontend
	 * language file and the current application's language file.
	 *
	 * @return  void
	 * @since   2.0.0
	 * @internal
	 */
	final protected function loadLanguage(): void
	{
		$jLang = $this->app->getLanguage();

		// Always load the admin language files
		$jLang->load($this->option, JPATH_ADMINISTRATOR);

		$isAdmin = $this->app->isClient('administrator');
		$isSite  = $this->app->isClient('site');

		// Load the language file specific to the current application. Only applies to site, CLI and API applications.
		if (!$isAdmin)
		{
			$jLang->load($this->option, JPATH_BASE);
		}

		// Load the frontend language files in the CLI and API applications.
		if (!$isAdmin && !$isSite)
		{
			$jLang->load($this->option, JPATH_SITE);
		}
	}

	/**
	 * Executes before dispatching a request made to this component
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	protected function onBeforeDispatch(): void
	{
		$this->loadLanguage();
	}
}