<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\Model;

defined('_JEXEC');

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\AdminModel;

class ActorModel extends AdminModel
{
	/**
	 * The prefix to use with controller messages.
	 *
	 * @var    string
	 * @since  2.0.0
	 */
	protected $text_prefix = 'COM_ACTIVITYPUB_ACTOR';

	public function getForm($data = [], $loadData = true)
	{
		// Get the form.
		$form = $this->loadForm(
			'com_activitypub.actor',
			'actor',
			[
				'control'   => 'jform',
				'load_data' => $loadData,
			]
		);

		if (empty($form))
		{
			return false;
		}

		// Don't allow to change the user_id user if not allowed to access com_users.
		if (!Factory::getApplication()->getIdentity()->authorise('core.manage', 'com_users'))
		{
			$form->setFieldAttribute('user_id', 'filter', 'unset');
		}

		// TODO Allow plugins to modify the form

		return $form;
	}

	protected function loadFormData()
	{
		// Check the session for previously entered form data.
		$app  = Factory::getApplication();
		$data = $app->getUserState('com_activitypub.edit.actor.data', []);

		if (empty($data))
		{
			$data = $this->getItem();
		}

		$this->preprocessData('com_activitypub.actor', $data);

		// TODO Allow plugins to modify the form data

		return $data;
	}

	protected function prepareTable($table)
	{
		// TODO Allow plugins to modify the table data (basically, the params) on save
	}

	protected function preprocessForm(Form $form, $data, $group = 'content')
	{
		// TODO Load plugins so that their onContentPrepareForm event fires

		parent::preprocessForm($form, $data, $group);
	}


}