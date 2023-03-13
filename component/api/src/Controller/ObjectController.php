<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Api\Controller;

defined('_JEXEC') or die;

use Dionysopoulos\Component\ActivityPub\Administrator\Table\ObjectTable;
use Dionysopoulos\Component\ActivityPub\Api\Model\ObjectModel;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Controller\Exception\ResourceNotFound;
use Joomla\CMS\MVC\View\JsonApiView;

/**
 * Controller for ActivityPub Actor interactions
 *
 * @since  2.0.0
 */
class ObjectController extends BaseController
{
	protected string $contentType = 'object';

	/**
	 * Displays a single item
	 *
	 * @param   string|null  $username  The username
	 *
	 * @return  $this
	 * @since   2.0.0
	 */
	public function displayItem(?string $username = null, ?string $objectId = null)
	{
		$username = $username ?? $this->input->getRaw('username', '');
		$objectId = $objectId ?? $this->input->getRaw('id', '');

		if (empty($username) || empty($objectId))
		{
			throw new ResourceNotFound(Text::_('COM_ACTIVITYPUB_OBJECT_ERR_NOT_FOUND'), 404);
		}

		// Create the model
		/** @var ObjectModel $model */
		$model = $this->getModel('Object', '', ['ignore_request' => true]);

		if (!$model)
		{
			throw new \RuntimeException(Text::_('JLIB_APPLICATION_ERROR_MODEL_CREATE'));
		}

		/** @var ObjectTable $objectTable */
		$objectTable = $model->getTable();

		if (!$objectTable->load($objectId))
		{
			throw new ResourceNotFound(Text::_('COM_ACTIVITYPUB_OBJECT_ERR_NOT_FOUND'), 404);
		}

		[$extension, $contentType, $id] = explode('.', $objectTable->context_reference, 3);
		$context = $extension . '.' . $contentType;

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

		$model->setState('object.username', $username);
		$model->setState('object.context', $context);
		$model->setState('object.id', $objectId);

		// Push the model into the view (as default)
		$view->setModel($model, true);

		$view->document = $document;
		$view->displayItem();

		return $this;
	}
}