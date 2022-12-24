<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Joomla\Plugin\WebFinger\ActivityPub\Extension;

defined('_JEXEC') || die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseDriver;
use Joomla\Event\SubscriberInterface;
use Joomla\Plugin\System\WebFinger\Event\GetResource;
use Joomla\Plugin\System\WebFinger\Event\LoadUserForm;
use Joomla\Plugin\System\WebFinger\Event\ResolveResource;
use Joomla\Plugin\System\WebFinger\Extension\UserFilterTrait;
use Joomla\Plugin\System\WebFinger\Extension\WebFingerTrait;
use Joomla\Utilities\ArrayHelper;

/**
 * Integrate ActivityPub with WebFinger
 *
 * @since 2.0.0
 */
class ActivityPub extends CMSPlugin implements SubscriberInterface, DatabaseAwareInterface
{
	use UserFilterTrait;
	use WebFingerTrait;
	use DatabaseAwareTrait;

	/**
	 * Returns an array of events this subscriber will listen to.
	 *
	 * @return  array
	 *
	 * @since   2.0.0
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onWebFingerLoadUserForm'    => 'loadUserForm',
			'onWebFingerGetResource'     => 'getResource',
			'onWebFingerResolveResource' => 'resolveResource',
		];
	}

	/**
	 * Adds fields to the WebFinger area of the user profile.
	 *
	 * @param   LoadUserForm  $event  The event we are handling
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	public function loadUserForm(LoadUserForm $event): void
	{
		// Only show the form if the user is allowed to have ActivityPub integration
		$user = $this->getApplication()->getIdentity() ?? new User();

		if (!$this->isAllowedActivityPubUser($user))
		{
			return;
		}

		// Add the preferences form
		/** @var Form $form */
		$form = $event->getArgument('form');

		$this->loadLanguage();
		$form->loadFile(__DIR__ . '/../../forms/webfinger.xml');
	}

	/**
	 * Updates the JRD (JSON Resource Definition) document returned by WebFinger.
	 *
	 * This method handles the onWebFingerGetResource plugin event.
	 *
	 * @param   GetResource  $event  The event we are handling
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	public function getResource(GetResource $event): void
	{
		// Get the arguments passed to the event
		/** @var array{subject: array, aliases: array, properties: array, links: array} $resource */
		$resource = $event->getArgument('resource');
		/** @var array $rel */
		$rel = $event->getArgument('rel');
		/** @var User $user */
		$user = $event->getArgument('user');

		if (!$this->isConsented($user->id))
		{
			return;
		}

		if ($this->isRel('self', $rel))
		{
			/**
			 * Remove any existing links with rel=self and type application/activity+json.
			 *
			 * This addresses the use case where both the Mastodon and ActivityPub plugins are published at the same
			 * time. It would result in a conflicting situation, with client-specific behaviour on which link would be
			 * selected.
			 */
			$resource['links'] = array_filter(
				$resource['links'],
				fn(array $link) => !(
					($link['rel'] ?? '') === 'self'
					&& ($link['type'] ?? '') === 'application/activity+json'
				)
			);

			$resource['links'][] = [
				'rel'  => 'self',
				'type' => 'application/activity+json',
				'href' => $this->getActorUri($user),
			];
		}

		$event->setArgument('resource', $resource);
	}

	/**
	 * Resolves a resource alias (e.g. `acct:user@example.com`) to a user object
	 *
	 * @param   ResolveResource  $event
	 *
	 * @return  void|null
	 * @since   2.0.0
	 */
	public function resolveResource(ResolveResource $event)
	{
		$resource = $event->getArgument('resource');

		if (empty($resource))
		{
			return;
		}

		// Support for acct:username@example.com
		if (str_starts_with($resource, 'acct:') && strpos($resource, '@', 5) !== false)
		{
			[$username, $domain] = explode('@', substr($resource, 5), 2);

			$user = $this->isOwnDomain($domain)
				? $this->getUserByUsername($username)
				: null;

			if ($user !== null && !$this->isConsented($user->id))
			{
				$user = null;
			}

			$event->addResult($user);
		}

		// The only other format I support is the actor URI. Therefore, I need a URL which starts with https://
		if (
			!filter_var($resource, FILTER_VALIDATE_URL) ||
			!str_starts_with($resource, 'https://')
		)
		{
			return;
		}

		$uri  = new Uri($resource);
		$path = trim($uri->getPath(), '/');

		// Make sure the domain name used is ours.
		if (!$this->isOwnDomain($uri->getHost()))
		{
			return;
		}

		// The URI must end in v1/activitypub/actor/username
		if (!preg_match('#v1/activitypub/actor/[^/]*$#', $path))
		{
			return;
		}

		// Assume the last bit is a username and get its user
		$bits = explode('/', $path);
		$username = array_pop($bits);
		$user = $this->getUserByUsername($username);

		if ($user === null)
		{
			return;
		}

		// Make sure the actor URI for the user and the $resource are identical
		$actorUri = $this->getActorUri($user);

		if ($actorUri !== $resource)
		{
			return;
		}

		// All good! We have a valid user!
		$event->addResult($user);
	}

	/**
	 * Is the user allowed to share their activity via ActivityPub?
	 *
	 * @param   User  $user  The user to check
	 *
	 * @return  bool
	 * @since   2.0.0
	 */
	private function isAllowedActivityPubUser(User $user): bool
	{
		$cParams       = ComponentHelper::getParams('com_activity');
		$anyUser       = (bool) $cParams->get('arbitrary_users', 0);
		$allowedGroups = $cParams->get('allowed_groups', [1]);

		// Is ActivityPub allowed for any user?
		if ($anyUser)
		{
			return true;
		}

		// Is ActivityPub allowed for the specific user group?
		if (is_array($allowedGroups))
		{
			$allowedGroups = ArrayHelper::toInteger($allowedGroups);

			if (!empty(array_intersect($allowedGroups, $user->getAuthorisedGroups())))
			{
				return true;
			}
		}

		if ($user->id <= 0)
		{
			return false;
		}

		// Hard mode. Check if there is a configured Actor for this user ID.
		$userId = $user->id;
		/** @var DatabaseDriver $db */
		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
			->select('COUNT(' . $db->quoteName('id') . ')')
			->from($db->quoteName('#__activitypub_actors'))
			->where($db->quoteName('user_id') . ' = :userId')
			->bind(':userId', $userId);

		return $db->setQuery($query)->loadResult() > 0;
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
		catch (\Exception $e)
		{
			$consent = null;
		}

		// A NULL value is considered de facto consent (the feature is opt-out, not opt-in)
		return $consent === null || $consent == 1;
	}

	/**
	 * Get a valid Actor user by their username (configured Actor username or Joomla username)
	 *
	 * @param   string  $username
	 *
	 * @return  User|null
	 * @since   2.0.0
	 */
	private function getUserByUsername(string $username): ?User
	{
		// Search for an already configured virtual Actor
		/** @var DatabaseDriver $db */
		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('username'),
				$db->quoteName('name'),
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
		$cParams       = ComponentHelper::getParams('com_activity');
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
	 * Get the actor URI for a user. Remember that the Actor URI is keyed by the _username_ of the user.
	 *
	 * @param   User  $user
	 *
	 * @return  string
	 * @since   2.0.0
	 */
	private function getActorUri(User $user): string
	{
		return Route::link(
			client: 'api',
			url: 'index.php?option=com_activitypub&controller=actor&username=' . urlencode($user->username),
			xhtml: false,
			tls: Route::TLS_FORCE,
			absolute: true
		);
	}
}