<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\View\Actor;

defined('_JEXEC') || die;

use Dionysopoulos\Component\ActivityPub\Administrator\Model\ActorModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
	/**
	 * The Form object
	 *
	 * @var    Form
	 * @since  1.5
	 */
	protected $form;

	/**
	 * The active item
	 *
	 * @var    object
	 * @since  1.5
	 */
	protected $item;

	/**
	 * The model state
	 *
	 * @var    object
	 * @since  1.5
	 */
	protected $state;

	/**
	 * Display the view
	 *
	 * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
	 *
	 * @return  void
	 *
	 * @throws  \Exception
	 * @since   1.5
	 *
	 */
	public function display($tpl = null): void
	{
		/** @var ActorModel $model */
		$model       = $this->getModel();
		$this->form  = $model->getForm();
		$this->item  = $model->getItem();
		$this->state = $model->getState();

		// Check for errors.
		if (\count($errors = $this->get('Errors')))
		{
			throw new GenericDataException(implode("\n", $errors), 500);
		}

		$this->addToolbar();

		parent::display($tpl);
	}

	/**
	 * Add the page title and toolbar.
	 *
	 * @return  void
	 *
	 * @throws  \Exception
	 * @since   1.6
	 */
	protected function addToolbar(): void
	{
		Factory::getApplication()->input->set('hidemainmenu', true);

		$isNew = ($this->item->id == 0);

		// Since we don't track these assets at the item level, use the category id.
		$canDo = ContentHelper::getActions('com_activitypub');

		ToolbarHelper::title($isNew ? Text::_('COM_ACTIVITYPUB_ACTOR_NEW') : Text::_('COM_ACTIVITYPUB_ACTOR_EDIT'), 'users');

		$toolbarButtons = [];

		// If not checked out, can save the item.
		if ($canDo->get('core.edit'))
		{
			ToolbarHelper::apply('actor.apply');
			$toolbarButtons[] = ['save', 'actor.save'];

			if ($canDo->get('core.create'))
			{
				$toolbarButtons[] = ['save2new', 'actor.save2new'];
			}
		}

		ToolbarHelper::saveGroup(
			$toolbarButtons,
			'btn-success'
		);

		if (empty($this->item->id))
		{
			ToolbarHelper::cancel('actor.cancel');
		}
		else
		{
			ToolbarHelper::cancel('actor.cancel', 'JTOOLBAR_CLOSE');
		}

		ToolbarHelper::inlinehelp();
	}

}