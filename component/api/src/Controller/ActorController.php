<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2024 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Api\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\View\JsonApiView;

/**
 * Controller for ActivityPub Actor interactions
 *
 * @since  2.0.0
 */
class ActorController extends BaseController
{
	protected string $contentType = 'actor';

	protected string $stateKey = 'actor.username';

	/**
	 * Displays a single item
	 *
	 * @param   string|null  $username  The username
	 *
	 * @return  $this
	 * @since   2.0.0
	 */
	public function displayItem(?string $username = null)
	{
		$username   = $username ?? $this->input->getRaw('username', '');
		$document   = $this->app->getDocument();
		$viewType   = $document->getType();
		$viewName   = $this->input->get('view', $this->default_view);
		$viewLayout = $this->input->get('layout', 'default', 'string');

		try
		{
			/** @var JsonApiView $view */
			$view = $this->getView(
				$viewName,
				$viewType,
				'',
				['base_path' => $this->basePath, 'layout' => $viewLayout, 'contentType' => $this->contentType]
			);
		}
		catch (\Exception $e)
		{
			throw new \RuntimeException($e->getMessage());
		}

		// Create the model, ignoring request data so we can safely set the state in the request from the controller
		$model = $this->getModel('Actor', '', ['ignore_request' => true]);

		if (!$model)
		{
			throw new \RuntimeException(Text::_('JLIB_APPLICATION_ERROR_MODEL_CREATE'));
		}

		$model->setState($this->stateKey, $username);

		// Push the model into the view (as default)
		$view->setModel($model, true);

		$view->document = $document;
		$view->displayItem();

		return $this;
	}
}