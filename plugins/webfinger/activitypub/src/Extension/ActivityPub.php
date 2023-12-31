<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2024 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Plugin\WebFinger\ActivityPub\Extension;

defined('_JEXEC') || die;

use Dionysopoulos\Component\ActivityPub\Administrator\Mixin\GetActorTrait;
use Dionysopoulos\Plugin\System\WebFinger\Event\GetResource;
use Dionysopoulos\Plugin\System\WebFinger\Event\LoadUserForm;
use Dionysopoulos\Plugin\System\WebFinger\Event\ResolveResource;
use Dionysopoulos\Plugin\System\WebFinger\Extension\UserFilterTrait;
use Dionysopoulos\Plugin\System\WebFinger\Extension\WebFingerTrait;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseDriver;
use Joomla\Event\SubscriberInterface;
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
	use GetActorTrait;
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
		$form->loadFile(__DIR__ . '/../../forms/activitypub.xml');
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
				'href' => $this->getApiUriForUser($user),
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
				? $this->getUserFromUsername($username)
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
		$bits     = explode('/', $path);
		$username = array_pop($bits);
		$user     = $this->getUserFromUsername($username);

		if ($user === null)
		{
			return;
		}

		// Make sure the actor URI for the user and the $resource are identical
		$actorUri = $this->getApiUriForUser($user);

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
		$cParams       = ComponentHelper::getParams('com_activitypub');
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
}