<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Api\Controller;

\defined('_JEXEC') || die;

use ActivityPhp\Type;
use Dionysopoulos\Component\ActivityPub\Api\Model\OutboxModel;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Controller\Exception\ResourceNotFound;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\MVC\View\JsonApiView;
use Joomla\CMS\Object\CMSObject;

/**
 * Controller for ActivityPub Outbox interactions
 *
 * @since  2.0.0
 */
class OutboxController extends BaseController
{
	/**
	 * The content type returned by this API controller
	 *
	 * @var   string
	 * @since 2.0.0
	 */
	protected string $contentType = 'outbox';

	/**
	 * The prefix for the state variables set by this API controller
	 *
	 * @var   string
	 * @since 2.0.0
	 */
	protected string $statePrefix = 'outbox';

	/**
	 * Display a list of records upon a GET request
	 *
	 * @return $this
	 * @since  2.0.0
	 */
	public function displayList(): self
	{
		// Pass parameters from the request into a new model state object
		$username      = $this->input->getRaw('username', '');
		$hasPagination = in_array(strtolower($this->input->getCmd('page', '')), ['1', 'true', 'yes']);
		$reqLimit      = $this->input->getInt('limit', null);
		$reqOffset     = $this->input->getInt('offset', null);
		$limit         = max(1, min($reqLimit ?? null, 50));
		$offset        = max(0, $reqOffset ?? null);
		$hasPagination = $hasPagination || ($reqLimit !== null) || ($reqLimit !== null);

		$modelState = new CMSObject();
		$modelState->set('filter.username', $username);
		$modelState->set('list.paginate', $hasPagination);

		if ($hasPagination)
		{
			$modelState->set($this->statePrefix . '.limitstart', $offset);
			$modelState->set($this->statePrefix . '.limit', $limit);
		}

		// Create a View object
		$viewType   = $this->app->getDocument()->getType();
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

		// Create a Model object
		/** @var ListModel $model */
		$model = $this->getModel($this->contentType, '', ['ignore_request' => true, 'state' => $modelState]);

		if (!$model)
		{
			throw new \RuntimeException(Text::_('JLIB_APPLICATION_ERROR_MODEL_CREATE'));
		}

		$view->setModel($model, true);

		// Pass the pagination information (again) into the model
		if ($hasPagination)
		{
			$model->setState('list.limit', $limit);
			$model->setState('list.start', $offset);
		}

		if ($hasPagination && $offset > $model->getTotal())
		{
			throw new ResourceNotFound('Not Found', 404);
		}

		// Push the document and ask the view to display the list
		$view->document = $this->app->getDocument();

		$view->displayList();

		return $this;
	}

	/**
	 * Handle a POST request
	 *
	 * @return self
	 * @since  2.0.0
	 */
	public function receivePost(): self
	{
		// Make sure that we have a JSON document representing an activity posted to us
		$jsonDocument = file_get_contents('php://input');
		$username     = $this->input->post->getRaw('username', '');

		try
		{
			$activity = Type::fromJson($jsonDocument);
		}
		catch (\Exception $e)
		{
			return $this->returnError(415, 'Invalid payload format');
		}

		// Get the model and ask it to handle the activity posted
		/** @var OutboxModel $model */
		$model = $this->getModel($this->contentType, '', ['ignore_request' => true]);

		if (!$model)
		{
			return $this->returnError(500, Text::_('JLIB_APPLICATION_ERROR_MODEL_CREATE'));
		}

		try
		{
			$model->handlePost($username, $activity);
		}
		catch (\Exception $e)
		{
			$code = $e->getCode();

			if ($code < 100 || $code > 599)
			{
				$code = 500;
			}

			return $this->returnError($code, $e->getMessage());
		}

		$this->app->setHeader('Status', 200);
		$this->app->setHeader('Content-Type', 'application/json');

		return $this;
	}

	private function returnError(int $status, string $message)
	{
		$app = $this->app;
		$app->setHeader('Status', $status);
		$app->setHeader('Content-Type', 'application/json');

		$app->getDocument()->setBuffer(
			json_encode([
				'error'   => true,
				'code'    => $status,
				'message' => $message,
			])
		);

		return $this;
	}

}