<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Plugin\WebServices\ActivityPub\Extension;

defined('_JEXEC') || die;

use Joomla\CMS\Application\ApiApplication;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\ApiRouter;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Router\Route;

class ActivityPub extends CMSPlugin implements SubscriberInterface
{
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
			'onBeforeApiRoute' => 'registerRoutes',
			'onAfterApiRoute'  => 'applyCustomJoomlaFormat',
		];
	}

	/**
	 * I need to always use the `json` format in my component. This method makes it so.
	 *
	 * @param   Event  $e
	 *
	 * @return  void
	 * @since   2.0.0
	 * @see     https://github.com/joomla/joomla-cms/issues/39495
	 */
	public function applyCustomJoomlaFormat(Event $e): void
	{
		/** @var ApiApplication $app */
		[$app] = $e->getArguments();

		if ($app->input->getCmd('option') !== 'com_activitypub')
		{
			return;
		}

		// We always use the plain old JSON format as we're handling the internals ourselves.
		if ($app->input->getMethod() === 'POST')
		{
			$app->input->post->set('format', 'json');
		}
		else
		{
			$app->input->set('format', 'json');
		}
	}

	/**
	 * Register the Joomla API application routes for the ActivityPub component
	 *
	 * @param   Event  $e
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	public function registerRoutes(Event $e): void
	{
		/** @var ApiRouter $router */
		[$router] = $e->getArguments();

		$defaults = [
			'component' => 'com_activitypub',
			// Allow public access (do not require Joomla API authentication)
			'public'    => true,
			// Custom accept headers
			'format'    => [
				'application/ld+json; profile="https://www.w3.org/ns/activitystreams"',
				'application/activity+json',
				'application/ld+json',
				'application/vnd.api+json',
				'application/json',
			],
		];

		$routes = [];

		// Actor -- only supports GET
		$routes[] = new Route(
			['GET'],
			'v1/activitypub/actor/:username',
			'actor.displayItem',
			[
				'username' => '[^/]+',
			],
			$defaults
		);

		// Outbox -- supports GET and POST.
		$routes[] = new Route(
			['GET'],
			'v1/activitypub/outbox/:username',
			'outbox.displayList',
			[
				'username' => '[^/]+',
			],
			$defaults
		);
		$routes[] = new Route(
			['POST'],
			'v1/activitypub/outbox/:username',
			'outbox.receivePost',
			[
				'username' => '[^/]+',
			],
			$defaults
		);

		// Inbox -- supports POST only.
		$routes[] = new Route(
			['GET'],
			'v1/activitypub/inbox/:username',
			'inbox.notImplemented',
			[
				'username' => '[^/]+',
			],
			$defaults
		);
		$routes[] = new Route(
			['POST'],
			'v1/activitypub/inbox/:username',
			'inbox.receivePost',
			[
				'username' => '[^/]+',
			],
			$defaults
		);

		// Object -- Supports GET only.
		$routes[] = new Route(
			['GET'],
			'v1/activitypub/object/:username/:id',
			'object.displayItem',
			[
				'username' => '[^/]+',
				'id'       => '(plg_|com_|mod_|tpl_|pkg_|lib_|files_|file_)[a-zA-Z0-9_\-.]+\.[a-zA-Z0-9_\-.]+\.[^/]+',
			],
			$defaults
		);
		$routes[] = new Route(
			['POST'],
			'v1/activitypub/object/:username/:id',
			'object.notImplemented',
			[
				'username' => '[^/]+',
				'id'       => '(plg_|com_|mod_|tpl_|pkg_|lib_|files_|file_)[a-zA-Z0-9_\-.]+\.[a-zA-Z0-9_\-.]+\.[^/]+',
			],
			$defaults
		);

		// Add the routes to the router.
		$router->addRoutes($routes);

		/**
		 * Conditionally fix missing Accept header.
		 *
		 * Mastodon and other ActivityPub clients don't set an Accept header on POST requests. However, the Joomla API
		 * application cannot accept a NULL value for the Accept header; it will throw an HTTP 406 error. As a result,
		 * I need to check if this is the case and set the Accept header manually.
		 */
		$path     = Uri::getInstance()->getPath();
		$basePath = Uri::base(true);

		if (str_starts_with($path, $basePath))
		{
			$path = substr($path, strlen($basePath));
		}

		$path         = trim($path, '/');
		$acceptHeader = $this->getApplication()->input->server->getString('HTTP_ACCEPT');

		if (str_starts_with($path, 'v1/activitypub/') && $acceptHeader === null && $this->getApplication()->input->getMethod() === 'POST')
		{
			$this->getApplication()->input->server->set('HTTP_ACCEPT', 'application/activity+json');
		}
	}
}