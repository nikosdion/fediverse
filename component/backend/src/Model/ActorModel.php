<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\Model;

defined('_JEXEC');

use Dionysopoulos\Component\ActivityPub\Administrator\Event\SaveActorEvent;
use Dionysopoulos\Component\ActivityPub\Administrator\Traits\IntegrationParamsMappingTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;

/**
 * Model for the Actor
 *
 * You can create custom plugins in the `activitypub` or `content` group which handle integration options for each
 * actor.
 *
 * You will need to implement the following event handlers:
 *
 * onContentPrepareForm(Form $form, array $data)
 * Load an additional XML form. Ideally, your fields should be under the `params` field group. The form name will be
 * `activitypub.actor`.
 *
 * onContentPrepareData(string $context, object $data)
 * Handle data loading. $data['params'] contains a JSON string of all integration parameters. Get a Registry object from
 * it and populate your form fields' data. The context will be `activitypub.actor`.
 *
 * onActivityPubSaveActor(array $data, Registry $params)
 * Handle data saving. Translate your field values in the $data array into $params registry values for persisting to the
 * database.
 *
 * You can use the \Dionysopoulos\Component\ActivityPub\Administrator\Traits\IntegrationParamsMappingTrait trait to
 * handle the onContentPrepareData and onActivityPubSaveActor event handlers more easily. See this model's
 * `__construct`, `save`, and `preprocessData` methods to see how these mappings become little more than one-liners.
 *
 * @since  2.0.0
 */
class ActorModel extends AdminModel
{
	use IntegrationParamsMappingTrait;

	/**
	 * The prefix to use with controller messages.
	 *
	 * @var    string
	 * @since  2.0.0
	 */
	protected $text_prefix = 'COM_ACTIVITYPUB_ACTOR';

	public function __construct($config = [], MVCFactoryInterface $factory = null, FormFactoryInterface $formFactory = null)
	{
		parent::__construct($config, $factory, $formFactory);

		$this->addIntegrationParamsMapping('content_enable', 'content.enable', 1);
		$this->addIntegrationParamsMapping('content_categories', 'content.categories', []);
		$this->addIntegrationParamsMapping('content_language', 'content.language', []);
		$this->addIntegrationParamsMapping('content_accesslevel', 'content.accesslevel', [1, 5]);
		$this->addIntegrationParamsMapping('activitypub_summary', 'activitypub.summary', '');
		$this->addIntegrationParamsMapping('activitypub_icon_source', 'activitypub.icon_source', 'gravatar');
		$this->addIntegrationParamsMapping('activitypub_url', 'activitypub.url', '');
		$this->addIntegrationParamsMapping('activitypub_media', 'activitypub.media', '');
	}

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

		return $form;
	}

	/**
	 * Method to save the form data.
	 *
	 * @param   array  $data  The form data.
	 *
	 * @return  boolean  True on success, False on error.
	 *
	 * @since   1.6
	 */
	public function save($data)
	{
		// Load the record, if editing an existing one
		$table = $this->getTable();
		$key   = $table->getKeyName();
		$pk    = (isset($data[$key])) ? $data[$key] : (int) $this->getState($this->getName() . '.id');

		if ($pk > 0)
		{
			try
			{
				$table->load($pk);
			}
			catch (\Exception $e)
			{
				$this->setError($e->getMessage());

				return false;
			}
		}

		// Get a params registry and call the onActivityPubSaveActor event
		$params = new Registry($table->params ?? '');
		$event  = new SaveActorEvent($data, $params);
		/** @var Registry $params */
		$params = Factory::getApplication()
			->getDispatcher()
			->dispatch(
				$event->getName(),
				$event
			)->getArgument('params');

		// Handle the parameters which are built into the component
		$this->setParamsFromFormData($params, $data);

		// Convert params registry to JSON string and put it into the $data array
		$data['params'] = $params->toString();

		return parent::save($data);
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

		return $data;
	}

	protected function preprocessForm(Form $form, $data, $group = 'content')
	{
		// Required for onContentPrepareForm in activitypub plugins
		PluginHelper::importPlugin('activitypub');

		parent::preprocessForm($form, $data, $group);
	}

	protected function preprocessData($context, &$data, $group = 'content')
	{
		// Required for onContentPrepareData in activitypub plugins
		PluginHelper::importPlugin('activitypub');

		// Handle the content parameters, built into the component
		$params                    = new Registry($data->params ?? []);

		$this->setFormDataFromParams($params, $data);

		// Call the parent method which goes through the plugins
		parent::preprocessData($context, $data, $group);
	}
}