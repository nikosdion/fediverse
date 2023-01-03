<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Plugin\WebFinger\Mastodon\Extension;

defined('_JEXEC') || die;

use Dionysopoulos\Plugin\System\WebFinger\Event\GetResource;
use Dionysopoulos\Plugin\System\WebFinger\Event\LoadUserForm;
use Dionysopoulos\Plugin\System\WebFinger\Event\ResolveResource;
use Dionysopoulos\Plugin\System\WebFinger\Extension\UserFilterTrait;
use Dionysopoulos\Plugin\System\WebFinger\Extension\WebFingerTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseDriver;
use Joomla\Event\SubscriberInterface;

/**
 * Add links to your Mastodon profile in the WebFinger results.
 *
 * This plugin also serves as a demonstration of the extensibility of the WebFinger framework.
 *
 * @since 2.0.0
 */
class Mastodon extends CMSPlugin implements SubscriberInterface, DatabaseAwareInterface
{
	use UserFilterTrait;
	use WebFingerTrait;
	use DatabaseAwareTrait;

	/**
	 * Returns an array of events this subscriber will listen to.
	 *
	 * These are the events fired by the WebFinger system plugin.
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
	 * This method handles the onWebFingerLoadUserForm plugin event.
	 *
	 * @param   LoadUserForm  $event  The event we are handling
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	public function loadUserForm(LoadUserForm $event): void
	{
		/** @var Form $form */
		$form = $event->getArgument('form');

		/**
		 * Remember that when this plugin is called we have confirmed that the user either needs to provide their
		 * consent to have their information listed to WebFinger _or_ they are forcibly required to do so (by plugin
		 * options, or by belonging to a user group which forces this choice upon them).
		 *
		 * Moreover, the WebFinger system plugin has established that this is a profile **edit** page, i.e. it's not the
		 * frontend profile display page. Therefore, all you have to do is load the form file and, possibly, massage the
		 * field attributes if necessary.
		 */
		$this->loadLanguage();
		$form->loadFile(__DIR__ . '/../../forms/mastodon.xml');
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

		// Load the user preferences and check if there is a Mastodon handle
		$preferences = $this->getUserProfileWebFingerPreferences($user->id);
		$handle      = trim($preferences->get('webfinger.mastodon_handle') ?? '');

		// No handle? Nothing to do!
		if (empty($handle))
		{
			return;
		}

		// Get the resource information given a specific Mastodon handle
		$info = $this->getInformationFromUsername($handle);

		// If there was no information returned it means the Mastodon handle was invalid. Nothing to do.
		if (empty($info))
		{
			return;
		}

		// Add aliases to the resource
		$resource['aliases'] = array_unique(
			array_merge($resource['aliases'], $info['aliases'])
		);

		// Add links to the resource, conditionally. Remember that we MUST filter by the requested relations.
		foreach ($info['links'] as $link)
		{
			if (!$this->isRel($link['rel'] ?? 'self', $rel))
			{
				continue;
			}

			$resource['links'][] = $link;
		}

		// Finally, pass back the Resource argument. Yes, this works on "immutable" events!
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
		if (str_starts_with($resource, 'acct:'))
		{
			$handle = substr($resource, 5);
			$user   = $this->getUserByMastodonHandle($handle);

			// Does the user consent to being searched by their Mastodon handle?
			$preferences = $this->getUserProfileWebFingerPreferences($user->id);

			if ($preferences->get('webfinger.search_by_mastodon', 1) != 1)
			{
				return null;
			}

			if ($user !== null)
			{
				$event->addResult($user);
			}

			return;
		}

		/**
		 * The only other formats I support are the profile page and activity URIs of Mastodon. Therefore, I need a URL
		 * which starts with http:// or https://
		 */
		if (
			!filter_var($resource, FILTER_VALIDATE_URL) ||
			(!str_starts_with($resource, 'https://') && !str_starts_with($resource, 'http://'))
		)
		{
			return;
		}

		// Initialisation
		$user = null;
		$uri  = new Uri($resource);
		$path = trim($uri->getPath(), '/');

		// Sub-case 1: http[s]://domain.tld/@username
		if (str_starts_with($path, '@') && strpos($path, '/') === false)
		{
			$username  = substr($path, 1);
			$host      = $uri->getHost();
			$altHost   = str_starts_with($host, 'www.') ? substr($host, 4) : ('www.' . $host);
			$handle    = $username . '@' . $host;
			$altHandle = $username . '@' . $altHost;

			$user = $this->getUserByMastodonHandle($handle) ?? $this->getUserByMastodonHandle($altHandle);
		}

		// Sub-case 2: http[s]://domain.tld/username
		if (!empty($path) && strpos($path, '/') === false)
		{
			$username  = $path;
			$host      = $uri->getHost();
			$altHost   = str_starts_with($host, 'www.') ? substr($host, 4) : ('www.' . $host);
			$handle    = $username . '@' . $host;
			$altHandle = $username . '@' . $altHost;

			$user = $this->getUserByMastodonHandle($handle) ?? $this->getUserByMastodonHandle($altHandle);
		}

		// Sub-case 3: http[s]://domain.tld/users/username
		if (str_starts_with($path, 'users/') && strpos($path, '/', 6) === false)
		{
			$username  = substr($path, 6);
			$host      = $uri->getHost();
			$altHost   = str_starts_with($host, 'www.') ? substr($host, 4) : ('www.' . $host);
			$handle    = $username . '@' . $host;
			$altHandle = $username . '@' . $altHost;

			$user = $this->getUserByMastodonHandle($handle) ?? $this->getUserByMastodonHandle($altHandle);
		}

		if (empty($user))
		{
			return;
		}

		// Does the user consent to being searched by their Mastodon handle?
		$preferences = $this->getUserProfileWebFingerPreferences($user->id);

		if ($preferences->get('webfinger.search_by_mastodon', 1) != 1)
		{
			return null;
		}

		$event->addResult($user);
	}

	/**
	 * Returns resource information given a Mastodon handle
	 *
	 * @param   string  $handle  The Mastodon handle, e.g. `user@example.com`.
	 *
	 * @return  array{aliases: array, links: array}|null
	 * @since   2.0.0
	 */
	private function getInformationFromUsername(string $handle): ?array
	{
		$handle = trim($handle, "@\ \t\n\r\0\x0B");

		if (!str_contains($handle, '@'))
		{
			return null;
		}

		[$handle, $server] = explode('@', $handle, 2);

		return [
			'aliases' => [
				'acct:' . $handle . '@' . $server,
			],
			'links'   => [
				[
					'rel'  => 'http://webfinger.net/rel/profile-page',
					'type' => 'text/html',
					'href' => 'https://' . $server . '/@' . $handle,
				],
				[
					'rel'  => 'self',
					'type' => 'application/activity+json',
					'href' => 'https://' . $server . '/' . $handle,
				],
			],
		];
	}

	private function getUserByMastodonHandle(string $handle): ?User
	{
		/** @var DatabaseDriver $db */
		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
			->select($db->quoteName('user_id'))
			->from($db->quoteName('#__user_profiles'))
			->where(
				[
					$db->quoteName('profile_key') . ' = ' . $db->quote('webfinger.mastodon_handle'),
					$db->quoteName('profile_value') . ' = :profile_value',
				]
			)
			->bind(':profile_value', $handle);

		try
		{
			$userId = $db->setQuery($query)->loadResult() ?: null;
		}
		catch (\Exception $e)
		{
			return null;
		}

		if (empty($userId))
		{
			return null;
		}

		$user = Factory::getUser($userId);

		if (empty($user) || ($user->id != $userId) || !$this->filterUser($user))
		{
			return null;
		}

		return $user;
	}

}