<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Api\Controller;

\defined('_JEXEC') || die;

use Dionysopoulos\Component\ActivityPub\Api\Controller\Mixin\NotImplementedTrait;
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
	use NotImplementedTrait;

	protected string $contentType = 'outbox';

	protected string $statePrefix = 'outbox';

	public function displayList()
	{
		// Pass parameters from the request into a new model state object
		$username      = $this->input->getRaw('username', '');
		$hasPagination = $this->input->getBool('page', false);

		$modelState = new CMSObject();
		$modelState->set('filter.username', $username);
		$modelState->set('list.paginate', $hasPagination);

		// Assemble pagination information
		$limit         = max(1, min($this->input->getInt('limit', 20), 50));
		$offset        = max(0, $this->input->getInt('offset', 0));

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

		if (!$hasPagination && $offset > $model->getTotal())
		{
			throw new ResourceNotFound();
		}

		// Push the document and ask the view to display the list
		$view->document = $this->app->getDocument();

		$view->displayList();

		return $this;
	}
}