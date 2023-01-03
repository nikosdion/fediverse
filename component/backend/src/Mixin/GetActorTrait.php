<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\Mixin;

use Dionysopoulos\Component\ActivityPub\Administrator\Table\ActorTable;
use Exception;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseDriver;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

trait GetActorTrait
{
	/**
	 * Retrieve the actor record for the specified user.
	 *
	 * If you set $createActor to true and this is a concrete Joomla user without an existing actor record, a new Actor
	 * record will be created for that user.
	 *
	 * Note that no new actor will be created for non-concrete users (ID <= 0), i.e. those used internally for "virtual"
	 * actors.
	 *
	 * @return  object|null
	 * @since   2.0.0
	 */
	public function getActorRecordForUser(?User $user, bool $createActor = false): ?ActorTable
	{
		static $table = null;

		/** @var ActorTable $table */
		$table = $table ?? call_user_func(
			function () {
				$app = method_exists($this, 'getApplication')
					? $this->getApplication()
					: (
					property_exists($this, 'app')
						? $this->app
						: Factory::getApplication()
					);

				return ($this instanceof BaseDatabaseModel)
					? $this->getTable('Actor', 'Administrator')
					: $app->bootComponent('com_activitypub')->getMVCFactory()->createTable('Actor', 'Administrator');
			}
		);

		if ($user === null)
		{
			return null;
		}

		$loaded = $table->load($user->id <= 0 ? ['username' => $user->username] : ['user_id' => $user->id]);

		// If we are not going to create a new Actor, or the user is not a concrete CMS user, return early.
		if (!$createActor || $user->id <= 0)
		{
			return $loaded ? $table : null;
		}

		if (!$loaded)
		{
			$table->reset();

			$saved = $table->save([
				'id'      => null,
				'user_id' => $user->id,
				'type'    => 'Person',
			]);

			return $saved ? $table : null;
		}

		return $table;
	}

	/**
	 * Get a valid Actor user by their username (configured Actor username or Joomla username)
	 *
	 * @param   string|null  $username
	 *
	 * @return  User|null
	 * @since   2.0.0
	 */
	protected function getUserFromUsername(?string $username): ?User
	{
		if ($username === null || trim($username) === '')
		{
			return null;
		}

		// Search for an already configured virtual Actor
		/** @var DatabaseDriver $db */
		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('username'),
				$db->quoteName('name'),
				$db->quoteName('type'),
			])
			->from($db->quoteName('#__activitypub_actors'))
			->where($db->quoteName('username') . ' = :username')
			->bind(':username', $username);
		$query->setLimit(1, 0);
		$row = $db->setQuery($query)->loadAssoc();

		// I found a virtual user! Return it.
		if (!empty($row))
		{
			$user           = new User();
			$user->guest    = 0;
			$user->username = $row['username'];
			$user->name     = $row['name'];

			$user->params = $user->params instanceof Registry ? $user->params : new Registry($user->params);
			$user->params->set('activitypub.type', $row['type']);

			return $user;
		}

		// Get a Joomla user by username
		/** @var User|null $user */
		$user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserByUsername($username);

		// If there is no such user I return null without belabouring the point any further.
		if (empty($user) || $user->guest || $user->id <= 0)
		{
			return null;
		}

		$user->params = $user->params instanceof Registry ? $user->params : new Registry($user->params);
		$user->params->set('activitypub.type', 'Person');

		// If I have a configured Actor for this user I can just return a quick result.
		$userId = $user->id;
		$query  = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__activitypub_actors'))
			->where($db->quoteName('user_id') . ' = :userId')
			->bind(':userId', $userId);

		if ($db->setQuery($query)->loadResult() >= 1)
		{
			return $this->isConsented($user->id) ? $user : null;
		}

		// No configured actor. I need the component parameters to decide what to do next.
		$cParams       = ComponentHelper::getParams('com_activitypub');
		$anyUser       = (bool) $cParams->get('arbitrary_users', 0);
		$allowedGroups = $cParams->get('allowed_groups', [1]);

		// Arbitrary users are not allowed, or I have no allowed groups (therefore nobody is implicitly allowed).
		if (!$anyUser || !is_array($allowedGroups) || empty($allowedGroups))
		{
			return null;
		}

		// The user does not belong to the allowed groups
		if (empty(array_intersect(ArrayHelper::toInteger($allowedGroups), $user->getAuthorisedGroups())))
		{
			return null;
		}

		return $this->isConsented($user->id) ? $user : null;
	}

	/**
	 * Get the base path of the site application.
	 *
	 * @return  string
	 * @since   2.0.0
	 * @throws  Exception
	 */
	protected function getFrontendBasePath(): string
	{
		static $basePath = null;

		if ($basePath === null)
		{
			$app = method_exists($this, 'getApplication')
				? $this->getApplication()
				: (
				property_exists($this, 'app')
					? $this->app
					: Factory::getApplication()
				);

			$basePath = rtrim(Uri::base(false), '/');

			// Note: this branch should NEVER execute!
			if ($app->isClient('administrator') && str_ends_with($basePath, '/administrator'))
			{
				$basePath = substr($basePath, 0, -14);
			}
			elseif ($app->isClient('api') && str_ends_with($basePath, '/api'))
			{
				$basePath = substr($basePath, 0, -4);
			}

			$basePath = rtrim($basePath, '/');
		}

		return $basePath;
	}

	/**
	 * Get the actor URI for a user. Remember that the Actor URI is keyed by the _username_ of the user.
	 *
	 * @param   User         $user  The user object
	 * @param   string|null  $path  The ActivityPub path (default: actor)
	 *
	 * @return  string
	 * @throws  Exception
	 * @since   2.0.0
	 */
	protected function getApiUriForUser(User $user, ?string $path = 'actor'): string
	{
		static $basePath = null;

		if ($basePath === null)
		{
			$app = method_exists($this, 'getApplication')
				? $this->getApplication()
				: (
				property_exists($this, 'app')
					? $this->app
					: Factory::getApplication()
				);

			/**
			 * We cannot use \Joomla\CMS\Router\Route::link directly because the \Joomla\CMS\Router\ApiRouter can only parse
			 * routes, not build them. This is an… odd choice on Joomla!'s part, but what can you do? We have to weasel our
			 * way around this limitation by building the API route ourselves.
			 */
			$useIndex = $app->get('sef_rewrite', 0) != 1;
			$basePath = rtrim(Uri::base(false), '/');

			// Note: this branch should NEVER execute!
			if ($app->isClient('administrator') && str_ends_with($basePath, '/administrator'))
			{
				$basePath = substr($basePath, 0, -14);
			}
			elseif ($app->isClient('api') && str_ends_with($basePath, '/api'))
			{
				$basePath = substr($basePath, 0, -4);
			}

			$basePath = rtrim($basePath, '/') . '/api';

			if ($useIndex)
			{
				$basePath .= '/index.php';
			}
		}


		return sprintf(
			'%s/v1/activitypub/%s/%s',
			$basePath,
			$path,
			urlencode($user->username)
		);
	}

	/**
	 * Has the user consented to using ActivityPub?
	 *
	 * This works as an opt-out check. The default state in absence of an explicit setting is implied consent.
	 *
	 * @param   int  $user_id  The user ID to check
	 *
	 * @return  bool
	 * @since   2.0.0
	 */
	private function isConsented(int $user_id): bool
	{
		// Virtual users are always consented by virtue of being created by an Administrator / Super User.
		if ($user_id === 0)
		{
			return true;
		}

		// Get the user profile setting
		/** @var DatabaseDriver $db */
		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
			->select($db->quoteName('user_id'))
			->from($db->quoteName('#__user_profiles'))
			->where(
				[
					$db->quoteName('profile_key') . ' = ' . $db->quote('webfinger.activitypub_enabled'),
					$db->quoteName('profile_value') . ' = :profile_value',
				]
			)
			->bind(':profile_value', $handle);

		try
		{
			$consent = $db->setQuery($query)->loadResult() ?: null;
		}
		catch (Exception $e)
		{
			$consent = null;
		}

		// A NULL value is considered de facto consent (the feature is opt-out, not opt-in)
		return $consent === null || $consent == 1;
	}

}