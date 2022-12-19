<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Joomla\Plugin\System\WebFinger\Extension;

defined('_JEXEC') || die;

use JetBrains\PhpStorm\NoReturn;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use Joomla\Event\Event;
use Joomla\Plugin\System\WebFinger\Exception\GenericWebFingerException;
use Joomla\Registry\Registry;
use Throwable;

/**
 * Implementation of the WebFinger protocol (RFC 7033) service
 *
 * @since  2.0.0
 */
trait WebFingerTrait
{
	use CacheTrait;
	use UserFilterTrait;

	/**
	 * Runs onAfterInitialise. Routes WebFinger requests.
	 *
	 * @param   Event  $event
	 *
	 * @return  void
	 * @throws  Throwable
	 *
	 * @since        2.0.0
	 * @noinspection PhpUnused
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function routeWebFingerRequest(Event $event): void
	{
		// Precondition: must be the Site application
		/** @var SiteApplication $app */
		$app = $this->getApplication();

		if (!$app->isClient('site'))
		{
			return;
		}

		// Get the request path relative to the site's root. It must be `.well-known/webfinger`
		$basePath     = Uri::base(true);
		$relativePath = Uri::getInstance()->getPath();

		if (!empty($basePath) && ($basePath != '/') && (str_starts_with($relativePath, $basePath)))
		{
			$relativePath = substr($relativePath, strlen($basePath));
		}

		$relativePath = trim($relativePath, '/');

		if ($relativePath !== '.well-known/webfinger')
		{
			return;
		}

		// Load the language files
		$this->loadLanguage();

		// Precondition check: Must have a resource request parameter
		$resource = $app->input->get->getRaw('resource');

		if (empty($resource))
		{
			$this->errorResponse(400, Text::_('PLG_SYSTEM_WEBFINGER_ERR_NO_RESOURCE_PARAM'));
		}

		// Precondition check: resource parameter format
		if (!str_contains($resource, ':'))
		{
			$this->errorResponse(400, Text::_('PLG_SYSTEM_WEBFINGER_ERR_MALFORMED_RESOURCE_PARAM'));
		}

		// Precondition check: must access over HTTPS
		if (Uri::getInstance()->getScheme() === 'http')
		{
			// Redirect HTTP to HTTPS
			$newUri = clone Uri::getInstance();
			$newUri->setScheme('https');
			$app->redirect($newUri, 308);
		}
		elseif (Uri::getInstance()->getScheme() !== 'https')
		{
			// This is neither HTTP nor HTTPS. Um, sorry, what?!
			$this->errorResponse(400, Text::_('PLG_SYSTEM_WEBFINGER_ERR_NOT_HTTPS'));
		}

		$rel = $app->input->get->getRaw('rel', []);
		$rel = is_array($rel) ? $rel : [$rel];

		// Handle WebFinger
		$useCaching = $app->get('caching', 0) == 1;

		try
		{
			if ($useCaching)
			{
				[$resource, $lastModified, $expires] = $this->getCache()->get(
					fn($resource, $rel) => [
						$this->getWebFingerResource($resource, $rel),
						time(),
						time() + 60 * $app->get('cachetime', 15),
					],
					[$resource, $rel]
				);
			}
			else
			{
				$resource = $this->getWebFingerResource($resource, $rel);
			}

		}
		catch (GenericWebFingerException $e)
		{
			$this->errorResponse($e->getCode(), $e->getMessage());
		}
		catch (Throwable $e)
		{
			// When under debug mode let the exception bubble up to Joomla's detailed error handler
			if (defined('JDEBUG') && JDEBUG)
			{
				throw $e;
			}

			$this->errorResponse(500, 'There was an error processing your request; please try later.');
		}

		// Return the JRD document
		@ob_end_clean();

		header('HTTP/1.1 200 OK');
		header('Content-Type: application/jrd+json; charset=' . $app->charSet);
		header('Access-Control-Allow-Origin: *');

		if ($useCaching)
		{
			header('Expires: ' . gmdate('D, d M Y H:i:s', $expires) . ' GMT');
			header('Last-Modified: ' . $lastModified . ' GMT');
		}
		else
		{
			header('Expires: Wed, 17 Aug 2005 00:00:00 GMT');
			header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
			header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
			header('Pragma: no-cache');
		}

		echo json_encode($resource, JSON_PRETTY_PRINT);

		exit();
	}

	/**
	 * Return an HTTP error to the client
	 *
	 * @param   int     $status   The HTTP status message (4xx or 5xx)
	 * @param   string  $message  The plain text message to include in the response
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	#[NoReturn] private function errorResponse(int $status, string $message): void
	{
		static $responseMap = [
			400 => 'HTTP/1.1 400 Bad Request',
			401 => 'HTTP/1.1 401 Unauthorized',
			402 => 'HTTP/1.1 402 Payment Required',
			403 => 'HTTP/1.1 403 Forbidden',
			404 => 'HTTP/1.1 404 Not Found',
			405 => 'HTTP/1.1 405 Method Not Allowed',
			406 => 'HTTP/1.1 406 Not Acceptable',
			407 => 'HTTP/1.1 407 Proxy Authentication Required',
			408 => 'HTTP/1.1 408 Request Timeout',
			409 => 'HTTP/1.1 409 Conflict',
			410 => 'HTTP/1.1 410 Gone',
			411 => 'HTTP/1.1 411 Length Required',
			412 => 'HTTP/1.1 412 Precondition Failed',
			413 => 'HTTP/1.1 413 Payload Too Large',
			414 => 'HTTP/1.1 414 URI Too Long',
			415 => 'HTTP/1.1 415 Unsupported Media Type',
			416 => 'HTTP/1.1 416 Range Not Satisfiable',
			417 => 'HTTP/1.1 417 Expectation Failed',
			418 => 'HTTP/1.1 418 I\'m a teapot',
			421 => 'HTTP/1.1 421 Misdirected Request',
			422 => 'HTTP/1.1 422 Unprocessable Entity',
			423 => 'HTTP/1.1 423 Locked',
			424 => 'HTTP/1.1 424 Failed Dependency',
			426 => 'HTTP/1.1 426 Upgrade Required',
			428 => 'HTTP/1.1 428 Precondition Required',
			429 => 'HTTP/1.1 429 Too Many Requests',
			431 => 'HTTP/1.1 431 Request Header Fields Too Large',
			451 => 'HTTP/1.1 451 Unavailable For Legal Reasons',
			500 => 'HTTP/1.1 500 Internal Server Error',
			501 => 'HTTP/1.1 501 Not Implemented',
			502 => 'HTTP/1.1 502 Bad Gateway',
			503 => 'HTTP/1.1 503 Service Unavailable',
			504 => 'HTTP/1.1 504 Gateway Timeout',
			505 => 'HTTP/1.1 505 HTTP Version Not Supported',
			506 => 'HTTP/1.1 506 Variant Also Negotiates',
			507 => 'HTTP/1.1 507 Insufficient Storage',
			508 => 'HTTP/1.1 508 Loop Detected',
			510 => 'HTTP/1.1 510 Not Extended',
			511 => 'HTTP/1.1 511 Network Authentication Required',
		];

		@ob_end_clean();

		/** @var SiteApplication $app */
		$app = $this->getApplication();

		header($responseMap[$status]);
		header('Content-Type: text/plain; charset=' . $app->charSet);
		header('Access-Control-Allow-Origin: *');
		header('Expires: Wed, 17 Aug 2005 00:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
		header('Pragma: no-cache');

		echo $message;

		exit();
	}

	/**
	 * Get the document to serialise as a JSON Resource Definition
	 *
	 * @param   string  $resourceKey
	 * @param   array   $rel  The relations requested; empty means all relations are accepted
	 *
	 * @return  array  A JRD represented as a PHP array
	 * @since   2.0.0
	 */
	private function getWebFingerResource(string $resourceKey, array $rel): array
	{
		// Resolve the $resource string into a user object
		$user = $this->resolveResource($resourceKey);

		if (empty($user))
		{
			throw new GenericWebFingerException(Text::_('PLG_SYSTEM_WEBFINGER_ERR_RESOURCE_NOT_FOUND'));
		}

		// Initialise the resource document
		$normalisedUserAccount = sprintf("acct:%s@%s", $user->username, $this->getNormalisedDomain());

		$resource = [
			'subject'    => $normalisedUserAccount,
			'aliases'    => [],
			'properties' => [],
			'links'      => [],
		];

		if ($resourceKey != $normalisedUserAccount)
		{
			$resource['aliases'][] = $resourceKey;
		}

		// Add the basic resource fields
		$resource = $this->addBasicResourceFields($user, $resource, $rel);

		// Go through plugins to retrieve additional resource information
		PluginHelper::importPlugin('webfinger');

		$event         = new Event('onWebFingerGetResource', [
			'resource' => $resource,
			'rel'      => $rel,
			'user'     => $user,
		]);
		$dispatcher    = $this->getApplication()->getDispatcher();
		$responseEvent = $dispatcher->dispatch($event->getName(), $event);
		$result        = $responseEvent->getArgument('resource', []) ?: $resource;

		/**
		 * Sanitise the subject.
		 *
		 * The subject MUST be present, and it MUST be a string. It MAY not be the same as the requested resource.
		 *
		 * If the condition is not met we revert to the default.
		 */
		if (empty($result['subject'] ?? '') || !is_string($result['subject']))
		{
			$result['subject'] = $normalisedUserAccount;
		}

		/**
		 * Sanitize aliases.
		 *
		 * The "aliases" array is an array of zero or more URI strings that identify the same entity as the "subject"
		 * URI.
		 */
		$resource['aliases'] = $resource['aliases'] ?? [];
		$resource['aliases'] = is_array($resource['aliases']) ? $resource['aliases'] : [];
		$resource['aliases'] = array_filter(
			array_values($resource['aliases']),
			fn($x) => is_string($x)
		);

		/**
		 * Sanitise the properties.
		 *
		 * The "properties" object comprises zero or more name/value pairs whose names are URIs (referred to as
		 * "property identifiers") and whose values are strings or null.
		 */
		$resource['properties'] = $resource['properties'] ?? [];
		/** @noinspection PhpConditionAlreadyCheckedInspection */
		$resource['properties'] = is_array($resource['properties']) ? $resource['properties'] : [];
		$resource['properties'] = array_filter(
			$resource['properties'],
			fn($x) => !is_numeric($x) && filter_var($x, FILTER_VALIDATE_URL),
			ARRAY_FILTER_USE_KEY
		);
		$resource['properties'] = array_filter(
			$resource['properties'],
			fn($x) => ($x === null) || is_string($x)
		);

		/**
		 * Sanitize the links.
		 *
		 * The "links" array has any number of member objects, each of which represents a link. Each of these link
		 * objects can have the following members:
		 *
		 * - rel (REQUIRED) either a URI or a registered relation type (see RFC 5988).
		 * - type (OPTIONAL) string
		 * - href (OPTIONAL) URI
		 * - titles (OPTIONAL) Array of string => string
		 * - properties (OPTIONAL) zero or more name/value pairs whose names are URIs (referred to as "property
		 *   identifiers") and whose values are strings or null.
		 */
		$resource['links'] = $resource['links'] ?? [];
		/** @noinspection PhpConditionAlreadyCheckedInspection */
		$resource['links'] = is_array($resource['links']) ? $resource['links'] : [];
		$resource['links'] = array_filter(
			array_values($resource['links']),
			function ($x) {
				if (!is_array($x))
				{
					return false;
				}

				if (!empty(array_diff(array_keys($x), ['rel', 'type', 'href', 'titles', 'properties'])))
				{
					return false;
				}

				if (!isset($x['rel']) || !is_string($x['rel']))
				{
					return false;
				}

				if (isset($x['type']) && !is_string($x['type']))
				{
					return false;
				}

				if (isset($x['href']) && !is_string($x['href']))
				{
					return false;
				}

				if (isset($x['titles']))
				{
					if (!is_array($x['titles']))
					{
						return false;
					}

					$validKeys = array_reduce(
						array_keys($x),
						fn($carry, $item) => $carry || (is_string($item) && !is_numeric($item) && !empty(trim($item))),
						false
					);

					$validValues = array_reduce(
						array_values($x),
						fn($carry, $item) => $carry || (is_string($item) && !empty(trim($item))),
						false
					);

					return $validKeys && $validValues;
				}

				if (isset($x['properties']))
				{
					if (!is_array($x['properties']))
					{
						return false;
					}

					$validKeys = array_reduce(
						array_keys($x),
						fn($carry, $item) => $carry || (is_string($item) && !empty(trim($item)) && filter_var($item, FILTER_VALIDATE_URL)),
						false
					);

					$validValues = array_reduce(
						array_values($x),
						fn($carry, $item) => $carry || (is_string($item) && !empty(trim($item)) || is_null($item)),
						false
					);

					return $validKeys && $validValues;
				}

				return true;
			}
		);

		// Keep unique items
		$resource['aliases']    = array_unique($resource['aliases']);
		$resource['properties'] = array_unique($resource['properties'], SORT_REGULAR);
		$resource['links']      = array_unique($resource['links'], SORT_REGULAR);

		// Remove empty optional arrays and objects
		if (empty($resource['aliases']))
		{
			unset($resource['aliases']);
		}

		if (empty($resource['properties']))
		{
			unset($resource['properties']);
		}

		if (empty($resource['links']))
		{
			unset($resource['links']);
		}

		return $resource;
	}

	/**
	 * Resolves the resource string to a user ID, or NULL if none is found.
	 *
	 * This event goes through the internal mappings of resources and falls back to the onWebFingerResolveResource
	 * plugin event if all else fails.
	 *
	 * @param   string  $resource  The resource string requested through WebFinger.
	 *
	 * @return  User|null The user ID or NULL if not found
	 * @since   2.0.0
	 */
	private function resolveResource(string $resource): ?User
	{
		// Do we have a simple `acct:username@mydomain` or an email address (`mailto:foobar@example.com`)?
		$user = $this->getUserIdFromAcctResource($resource)
			?? $this->getUserFromMailtoResource($resource);

		if (!empty($user))
		{
			return $user;
		}

		// Fallback to the plugin event
		PluginHelper::importPlugin('webfinger');

		$event         = new Event('onWebFingerResolveResource', [
			'resource' => $resource,
		]);
		$dispatcher    = $this->getApplication()->getDispatcher();
		$responseEvent = $dispatcher->dispatch($event->getName(), $event);
		$result        = $responseEvent->getArgument('result', []);

		if (!is_array($result))
		{
			return null;
		}

		// Only accept the results which are User objects and represent a virtual user OR a real user who has consented.
		$result = array_filter($result, fn($x) => $x instanceof User && $this->filterUser($user));

		// No results? Return null (no resource found).
		if (empty($result))
		{
			return null;
		}

		return array_shift($result);
	}

	/**
	 * Resolve an `acct:username@domain.tld` resource for the current site.
	 *
	 * @param   string  $resource  The resource string to check
	 *
	 * @return  User|null The user ID (if the user has consented), null otherwise
	 * @since   2.0.0
	 */
	private function getUserIdFromAcctResource(string $resource): ?User
	{
		// Resource must start with `acct:`
		if (!str_starts_with($resource, 'acct:'))
		{
			return null;
		}

		// The resource must have an at sign.
		if (!str_contains($resource, '@'))
		{
			return null;
		}

		// Get the bare identifier after the `acct:` literal
		$bareIdentifier = trim(substr($resource, 5));

		// The bare identifier must have a username AND a domain part.
		if (str_starts_with($bareIdentifier, '@') || str_ends_with($bareIdentifier, '@'))
		{
			return null;
		}

		// Extract the username and domain name
		[$username, $domain] = explode('@', $bareIdentifier);

		// The domain name must match the site's domain name
		if (!$this->isOwnDomain($domain))
		{
			return null;
		}

		// Try to get the user given the username
		/** @var User $user */
		$user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserByUsername($username);

		if (empty($user->id))
		{
			return null;
		}

		// Check if the user consents to their profile being available over WebFinger
		if (!$this->filterUser($user))
		{
			return null;
		}

		return $user;
	}

	/**
	 * Resolve a `mailto:foobar@example.com` resource for the current site.
	 *
	 * @param   string  $resource  The resource string to check
	 *
	 * @return  User|null The user ID (if the user has consented), null otherwise
	 * @since   2.0.0
	 */
	private function getUserFromMailtoResource(string $resource): ?User
	{
		// Resource must start with `mailto:`
		if (!str_starts_with($resource, 'mailto:'))
		{
			return null;
		}

		// The resource must have an at sign.
		if (!str_contains($resource, '@'))
		{
			return null;
		}

		// Get the bare identifier after the `mailto:` literal
		$bareIdentifier = trim(substr($resource, 7));

		// The bare identifier must have a username AND a domain part.
		if (str_starts_with($bareIdentifier, '@') || str_ends_with($bareIdentifier, '@'))
		{
			return null;
		}

		// Make sure this is a valid email address
		if (!filter_var($bareIdentifier, FILTER_VALIDATE_EMAIL, FILTER_FLAG_EMAIL_UNICODE))
		{
			return null;
		}

		/** @var DatabaseDriver $db */
		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
			->select($db->quoteName('id'))
			->from($db->quoteName('#__users'))
			->where($db->quoteName('email') . ' = :email')
			->bind(':email', $bareIdentifier)
			->setLimit(1);

		$userId = $db->setQuery($query)->loadResult() ?? 0;

		if (!is_int($userId) || $userId <= 0)
		{
			return null;
		}

		$user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userId);

		if (empty($user->id))
		{
			return null;
		}

		// Check if the user consents to their profile being available over WebFinger
		if (!$this->filterUser($user))
		{
			return null;
		}

		// Check if the user consents to their profile being searchable by email address
		$query         = $db->getQuery(true)
			->select($db->quoteName('profile_value'))
			->from($db->quoteName('#__user_profiles'))
			->where([
				$db->quoteName('user_id') . ' = :user_id',
				$db->quoteName('profile_key') . ' = ' . $db->quote('webfinger.search_by_email'),
			])
			->bind(':user_id', $userId, ParameterType::INTEGER);
		$consentSearch = $db->setQuery($query)->loadResult() ?: 0;

		if (!$consentSearch)
		{
			return null;
		}

		return $user;
	}

	/**
	 * Is the given domain the same as the site's configured domain name?
	 *
	 * @param   string  $domain  The domain name to check.
	 *
	 * @return  bool
	 * @since   2.0.0
	 */
	private function isOwnDomain(string $domain): bool
	{
		$app         = $this->getApplication();
		$live_site   = trim($app->get('live_site') ?: '');
		$uri         = empty($live_site) ? Uri::getInstance() : Uri::getInstance($live_site);
		$myDomain    = strtolower($uri->getHost() ?: '');
		$altMyDomain = str_starts_with($myDomain, 'www.') ? substr($myDomain, 4) : ('www.' . $myDomain);
		$domain      = strtolower($domain);
		$altDomain   = str_starts_with($domain, 'www.') ? substr($domain, 4) : ('www.' . $domain);

		$allowedDomains = [$myDomain, $altMyDomain];

		return in_array($domain, $allowedDomains) || in_array($altDomain, $allowedDomains);
	}

	/**
	 * Return the normalised domain name of the site (without www)
	 *
	 * @return  string
	 * @since   2.0.0
	 */
	private function getNormalisedDomain(): string
	{
		$app       = $this->getApplication();
		$live_site = trim($app->get('live_site') ?: '');
		$uri       = empty($live_site) ? Uri::getInstance() : Uri::getInstance($live_site);
		$myDomain  = strtolower($uri->getHost() ?: '');

		if (!str_starts_with($myDomain, 'www.'))
		{
			return $myDomain;
		}

		return substr($myDomain, 4);
	}

	/**
	 * Returns the WebFinger user profile options for the specified user ID.
	 *
	 * @param   int  $userId  The user ID to look up
	 *
	 * @return  Registry
	 * @since   2.0.0
	 */
	private function getUserProfileWebFingerPreferences(int $userId): Registry
	{
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

		$results = $db->setQuery($query)->loadAssocList('profile_key', 'profile_value') ?: [];
		$results = is_array($results) ? $results : [];

		$results = array_combine(
			array_map(
				fn($key) => substr($key, 10),
				array_keys($results)
			),
			array_values($results)
		);

		return new Registry(['webfinger' => $results]);
	}

	private function addBasicResourceFields(?User $user, array $resource, array $rel): array
	{
		if (empty($user) || $user->id <= 0)
		{
			return $resource;
		}

		$preferences = $this->getUserProfileWebFingerPreferences($user->id);

		if ($preferences->get('webfinger.show_email', 0))
		{
			$resource['aliases'][] = 'mailto:' . $user->email;
		}

		if ($preferences->get('webfinger.show_name', 0) && $this->isRel('author', $rel))
		{
			$resource['links'][] = [
				'rel'    => 'author',
				'titles' => [
					'und' => $user->name,
				],
			];
		}

		if ($preferences->get('webfinger.show_gravatar', 0) && $this->isRel('http://webfinger.net/rel/avatar', $rel))
		{
			$resource['links'][] = [
				'rel'  => 'http://webfinger.net/rel/avatar',
				'href' => sprintf('https://www.gravatar.com/avatar/%s?s=%s', md5(strtolower(trim($user->email))), 128),
			];
		}

		return $resource;
	}

}