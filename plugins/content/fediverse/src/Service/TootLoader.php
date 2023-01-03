<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Plugin\Content\Fediverse\Service;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Cache\Controller\CallbackController;
use Joomla\CMS\Factory;
use Joomla\CMS\Http\Http;
use JsonException;
use Throwable;

class TootLoader
{
	/**
	 * Public constructor
	 *
	 * @param   Http            $http            The Joomla HTTP object
	 * @param   CMSApplication  $app             The current Joomla application
	 * @param   int             $cacheLifetime   Toot cache lifetime, in seconds
	 * @param   int             $requestTimeout  HTTP request timeout, in seconds
	 *
	 * @since   1.0.0
	 */
	public function __construct(
		private Http           $http,
		private CMSApplication $app,
		private int            $cacheLifetime = 120,
		private int            $requestTimeout = 5,
		private bool           $useCaching = true,
	) {}

	/**
	 * Get the object representation of a toot
	 *
	 * @param   string  $url  The toot's ID
	 *
	 * @return  object|null
	 * @since   1.0.0
	 */
	public function getTootInformation(string $url): ?object
	{
		if (!$this->useCaching)
		{
			try
			{
				return $this->loadToot($url);
			}
			catch (Throwable $e)
			{
				return null;
			}
		}

		return $this->getCache()
		            ->get(
			            function ($url) {
				            try
				            {
					            return $this->loadToot($url);
				            }
				            catch (\Exception $e)
				            {
					            return null;
				            }
			            },
			            [$url]
		            );
	}

	/**
	 * Returns a Joomla callback cache controller.
	 *
	 * Used to cache the fetched toot information.
	 *
	 * @return  CallbackController
	 * @since   1.0.0
	 */
	private function getCache(): CallbackController
	{
		$options = [
			'defaultgroup' => 'plg_content_fediverse',
			'cachebase'    => $this->app->get('cache_path', JPATH_CACHE),
			'lifetime'     => $this->cacheLifetime,
			'language'     => $this->app->get('language', 'en-GB'),
			'storage'      => $this->app->get('cache_handler', 'file'),
			'locking'      => true,
			'locktime'     => 15,
			'checkTime'    => true,
			'caching'      => true,

		];

		return Factory::getContainer()
		              ->get(CacheControllerFactoryInterface::class)
		              ->createCacheController('callback', $options);
	}

	/**
	 * Loads a toot object from the Mastodon server given its URL
	 *
	 * @param   string    $url         The URL of the toot, web or API
	 * @param   int|null  $tootId      Set this to fetch a different toot from the same server, using this ID
	 * @param   bool      $withParent  Should I include the parent toot if there's a non-empty in_reply_to_id field?
	 *
	 * @return  object|null
	 * @throws  JsonException
	 * @since   1.0.0
	 */
	private function loadToot(string $url, ?int $tootId = null, bool $withParent = true): ?object
	{
		$url          = trim($url);
		$separatorPos = strpos($url, '/web') ?: strpos($url, '/@') ?: strpos($url, '/api/v1/statuses/');

		if ($separatorPos === false)
		{
			return null;
		}

		$serverUrl = rtrim(substr($url, 0, $separatorPos), '/');

		if (empty($tootId))
		{
			$parts  = explode('/', $url);
			$tootId = (int) end($parts);
		}

		$apiUrl = $serverUrl . '/api/v1/statuses/' . $tootId;

		$response = $this->http->get($apiUrl, [], $this->requestTimeout);

		if ($response->getStatusCode() !== 200)
		{
			return null;
		}

		if (!str_starts_with(implode('', $response->getHeader('content-type')), 'application/json'))
		{
			return null;
		}

		$ret = @json_decode($response->body, flags: JSON_THROW_ON_ERROR);

		if ($ret?->id != $tootId)
		{
			return null;
		}

		if ($withParent && !empty($ret?->in_reply_to_id))
		{
			try
			{
				$parent       = $this->loadToot($url, tootId: $ret->in_reply_to_id, withParent: false);
				$ret->_parent = $parent;
			}
			catch (\Exception $e)
			{
				// Okay, you failed. No problem. We just don't show the parent. All good.
			}
		}

		return $ret;
	}


}