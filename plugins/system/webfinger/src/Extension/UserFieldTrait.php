<?php
/*
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Joomla\Plugin\System\WebFinger\Extension;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use Joomla\Event\Event;
use Joomla\Utilities\ArrayHelper;

trait UserFieldTrait
{
	use UserFilterTrait;

	/**
	 * Load the WebFinger user profile form.
	 *
	 * This happens when editing or saving the user data.
	 *
	 * @param   Event  $e  The event we are handling (onContentPrepareForm)
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	public function loadUserForm(Event $e): void
	{
		/**
		 * @var Form   $form The user profile form
		 * @var object $data The user profile data
		 */
		[$form, $data] = $e->getArguments();

		if (!$form instanceof Form)
		{
			return;
		}

		if (!in_array($form->getName(), [
			'com_admin.profile', 'com_users.user', 'com_users.registration', 'com_users.profile',
		]))
		{
			return;
		}

		// Load the language files and the form
		$this->loadLanguage();
		$form->loadFile(JPATH_PLUGINS . '/system/webfinger/forms/webfinger.xml');

		// If we are showing the form (instead of just loading it to save data) check for forced consent.
		$userId = $data->id ?? null;

		if (!is_int($userId))
		{
			return;
		}

		$user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userId);

		if (!$user instanceof User || $user->id != $userId)
		{
			return;
		}

		$forcedConsent = $this->getForcedConsent($user);

		if ($forcedConsent === null)
		{
			return;
		}

		// Hide consent and if forcibly set
		$form->setFieldAttribute('consent', 'type', 'hidden', 'webfinger');
		$form->setFieldAttribute('consent', 'value', $forcedConsent ? 1 : 0, 'webfinger');
	}

	/**
	 * Saves the WebFinger user profile fields into the database.
	 *
	 * @param   Event  $e  The event we are handling (onUserAfterSave)
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	public function saveUserFields(Event $e): void
	{
		/**
		 * @var array  $data   The user data coming from the request
		 * @var bool   $isNew  Is this a new user...?
		 * @var bool   $result True if saving the user information succeeded
		 * @var string $error  Error message
		 */
		[$data, $isNew, $result, $error] = $e->getArguments();

		if ($isNew || !$result)
		{
			return;
		}

		$userId = $data['id'] ?? null;

		if (!is_int($userId))
		{
			return;
		}

		$user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userId);

		// Delete existing WebFinger profile fields
		if (!$this->deleteWebFingerUserFields($user->id))
		{
			return;
		}

		// If we do not have any WebFinger fields there's nothing else to do.
		$myFields = $data['webfinger'] ?? [];

		if (!is_array($myFields) || empty($myFields))
		{
			return;
		}

		// Insert the field values
		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
			->insert('#__user_profiles')
			->columns([
				$db->quoteName('user_id'),
				$db->quoteName('profile_key'),
				$db->quoteName('profile_value'),
			]);

		foreach ($myFields as $k => $v)
		{
			$query->values(
				sprintf(
					'%s,%s,%s',
					$db->quote((int) $user->id),
					$db->quote('webfinger.' . $k),
					$db->quote($v)
				)
			);
		}

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (\Exception $e)
		{
			// If it dies, it dies.
		}
	}

	/**
	 * Load the WebFinger profile field values into the user profile form.
	 *
	 * @param   Event  $e  The event we are handling (onContentPrepareData)
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	public function loadFieldData(Event $e): void
	{
		/**
		 * @var string $context
		 * @var object $data
		 */
		[$context, $data] = $e->getArguments();

		// Check we are manipulating a valid form.
		if (!in_array($context, ['com_users.profile', 'com_users.user', 'com_users.registration', 'com_admin.profile']))
		{
			return;
		}

		if (!is_object($data) || !isset($data->id) || isset($data->webfinger))
		{
			return;
		}

		$userId = (int) ($data->id ?? 0);

		if ($userId <= 0)
		{
			return;
		}

		/** @var DatabaseDriver $db */
		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('profile_key'),
				$db->quoteName('profile_value'),
			])
			->from($db->quoteName('#__user_profiles'))
			->where([
				$db->quoteName('user_id') . ' = :user_id',
				$db->quoteName('profile_key') . ' LIKE ' . $db->quote('webfinger.%'),
			])
			->bind(':user_id', $userId, ParameterType::INTEGER);

		try
		{
			$results = $db->setQuery($query)->loadAssocList('profile_key', 'profile_value');
		}
		catch (\Exception $e)
		{
			return;
		}

		$data->webfinger = [];

		if (empty($results))
		{
			return;
		}

		foreach ($results as $k => $v)
		{
			$k                   = substr($k, 10);
			$data->webfinger[$k] = $v;
		}
	}

	/**
	 * Delete the WebFinger user profile field data when a user account is deleted.
	 *
	 * @param   Event  $e  The even we are handling (onUserAfterDelete)
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	public function deleteFieldData(Event $e): void
	{
		/**
		 * @var array  $user    The user data
		 * @var bool   $success Did deleting the user succeed?
		 * @var string $msg     Error message
		 */
		[$user, $success, $msg] = $e->getArguments();

		if (!$success)
		{
			return;
		}

		$userId = ArrayHelper::getValue($user, 'id', 0, 'int');

		if ($userId <= 0)
		{
			return;
		}

		/** @var DatabaseDriver $db */
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->delete($db->quoteName('#__user_profiles'))
			->where([
				$db->quoteName('profile_key') . ' LIKE ' . $db->quote('webfinger.%'),
				$db->quoteName('user_id') . ' = :user_id'
			])
			->bind(':user_id', $userId, ParameterType::INTEGER);

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (\Exception $e)
		{
			// If it dies, it dies.
		}
	}

	/**
	 * Deletes the user fields for a specific user ID.
	 *
	 * @param   int|null  $userId  The user ID
	 *
	 * @return  bool  True on success
	 * @since   2.0.0
	 */
	private function deleteWebFingerUserFields(?int $userId): bool
	{
		if (!is_int($userId) || $userId <= 0)
		{
			return false;
		}

		/** @var DatabaseDriver $db */
		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
			->delete($db->quoteName('#__user_profiles'))
			->where([
				$db->quoteName('user_id') . ' = :user_id',
				$db->quoteName('profile_key') . ' LIKE ' . $db->quote('webfinger.%'),
			])
			->bind(':user_id', $userId, ParameterType::INTEGER);

		try
		{
			$db->setQuery($query)->execute();

			return true;
		}
		catch (\Exception $e)
		{
			return false;
		}
	}
}