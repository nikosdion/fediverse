<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Site\Controller;

\defined('_JEXEC') || die;

use Joomla\CMS\Cache\Exception\CacheExceptionInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;

class DisplayController extends BaseController
{
	public function display($cachable = false, $urlparams = [])
	{
		$document   = $this->app->getDocument();
		$viewType   = $document->getType();
		$viewName   = $this->input->get('view', $this->default_view);
		$viewLayout = $this->input->get('layout', 'default', 'string');

		$view = $this->getView($viewName, $viewType, '', ['base_path' => $this->basePath, 'layout' => $viewLayout]);

		// Get/Create the model
		if ($model = $this->getModel($viewName, '', ['base_path' => $this->basePath]))
		{
			$id       = $this->app->input->getInt('id', null);
			$username = $this->app->input->getUsername('username', null);

			$model->setState('id', $id);
			$model->setState('username', $username);

			// Push the model into the view (as default)
			$view->setModel($model, true);
		}

		$view->document = $document;

		// Display the view
		if ($cachable && $viewType !== 'feed' && $this->app->get('caching') >= 1)
		{
			$option = $this->input->get('option');

			if (\is_array($urlparams))
			{
				if (!empty($this->app->registeredurlparams))
				{
					$registeredurlparams = $this->app->registeredurlparams;
				}
				else
				{
					$registeredurlparams = new \stdClass();
				}

				foreach ($urlparams as $key => $value)
				{
					// Add your safe URL parameters with variable type as value {@see InputFilter::clean()}.
					$registeredurlparams->$key = $value;
				}

				$this->app->registeredurlparams = $registeredurlparams;
			}

			try
			{
				/** @var \Joomla\CMS\Cache\Controller\ViewController $cache */
				$cache = Factory::getCache($option, 'view');
				$cache->get($view, 'display');
			}
			catch (CacheExceptionInterface $exception)
			{
				$view->display();
			}
		}
		else
		{
			$view->display();
		}

		return $this;
	}
}