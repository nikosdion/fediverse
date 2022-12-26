<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Joomla\Plugin\WebServices\ActivityPub\Extension;

defined('_JEXEC') || die;

use Joomla\CMS\Application\ApiApplication;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\ApiRouter;
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

		// Finally, add the routes to the router.
		$router->addRoutes($routes);
	}

	private function translateFormat(string $format): string
	{
		return match ($format)
		{
			'application/activity+json',
			'applicationactivityjson',
			'application/ld+json',
			'applicationldjson',
			'application/json',
			'applicationjson',
			'application/vnd.api+json',
			'applicationvnd.apijson' => 'json',
			default => $format,
		};
	}
}