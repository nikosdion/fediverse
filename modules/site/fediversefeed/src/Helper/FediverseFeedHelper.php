<?php
/*
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   GNU General Public License, version 3
 */

namespace Joomla\Module\FediverseFeed\Site\Helper;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Cache\CacheControllerFactory;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Cache\Controller\CallbackController;
use Joomla\CMS\Factory;
use Joomla\CMS\Feed\Feed;
use Joomla\CMS\Feed\Parser\RssParser;
use Joomla\CMS\Http\HttpFactory;
use Joomla\Module\FediverseFeed\Site\RssParser\MediaRSSParser;
use Joomla\Module\FediverseFeed\Site\RssParser\WebfeedsRSSParser;
use Joomla\Registry\Registry;

class FediverseFeedHelper
{
	private SiteApplication $app;

	/**
	 * Set the application object
	 *
	 * @param   SiteApplication  $app
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function setApplication(SiteApplication $app): void
	{
		$this->app = $app;
	}

	/**
	 * Returns the RSS feed URL for a Mastodon username
	 *
	 * @param   string  $username  The Mastodon username, e.g. `@nikosdion@fosstodon.org`
	 *
	 * @return  string|null  The URL; NULL if we cannot figure it out.
	 * @since   1.0.0
	 */
	public function getFeedURL(string $username): ?string
	{
		$username = trim($username, "@\ \t\n\r\0\x0B");

		if (strpos($username, '@') === false)
		{
			return null;
		}

		[$username, $server] = explode('@', $username, 2);

		return 'https://' . $server . '/@' . $username . '.rss';
	}

	public function getFeed(string $url, Registry $params): ?Feed
	{
		$timeout           = (int) $params->get('get_timeout', 5);
		$customCertificate = $params->get('custom_certificate', '') ?: null;

		if ($params->get('cache_feed', 1) == 0)
		{
			try
			{
				return $this->loadAndParseFeed($url, $timeout, $customCertificate);
			}
			catch (\Exception $e)
			{
				return null;
			}
		}

		return $this->getCache($params->get('feed_cache_lifetime', 3600))
		            ->get(function (string $url) use ($timeout, $customCertificate) {
			            try
			            {
				            return $this->loadAndParseFeed($url, $timeout, $customCertificate);
			            }
			            catch (\Exception $e)
			            {
				            return null;
			            }
		            }, [$url]);
	}

	/**
	 * Loads and parses a Mastodon RSS feed from a URL
	 *
	 * @param   string       $uri                The URL to load the feed from
	 * @param   int          $timeout            Timeout (in seconds) for the HTTP request
	 * @param   string|null  $customCertificate  Path to custom TLS certificate file
	 *
	 * @return  Feed
	 * @since   1.0.0
	 */
	public function loadAndParseFeed(string $uri, int $timeout = 5, ?string $customCertificate = null): Feed
	{
		$reader = new \XMLReader();

		$optionsSource = [
			'userAgent' => 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:41.0) Gecko/20100101 Firefox/41.0',
		];

		if (!empty($customCertificate))
		{
			$optionsSource['curl'] = [
				'certpath' => $customCertificate
			];
			$optionsSource['stream'] = [
				'certpath' => $customCertificate
			];
		}

		$options = new Registry(json_encode($optionsSource));

		try
		{
			$response = HttpFactory::getHttp($options)->get($uri, [], $timeout);
		}
		catch (\RuntimeException $e)
		{
			throw new \RuntimeException('Unable to open the feed.', $e->getCode(), $e);
		}

		if ($response->code != 200)
		{
			throw new \RuntimeException('Unable to open the feed.');
		}

		// Set the value to the XMLReader parser
		if (!$reader->XML($response->body, null, LIBXML_NOERROR | LIBXML_ERR_NONE | LIBXML_NOWARNING))
		{
			throw new \RuntimeException('Unable to parse the feed.');
		}

		try
		{
			while ($reader->read())
			{
				if ($reader->nodeType == \XMLReader::ELEMENT)
				{
					break;
				}
			}
		}
		catch (\Exception $e)
		{
			throw new \RuntimeException('Error reading feed.', $e->getCode(), $e);
		}

		if ($reader->name !== 'rss')
		{
			throw new \RuntimeException('Invalid RSS feed');
		}

		$parser = new RssParser($reader);
		$parser->registerNamespace('media', new MediaRSSParser());
		$parser->registerNamespace('webfeeds', new WebfeedsRSSParser());

		return $parser->parse();
	}

	/**
	 * Get a callback cache controller for caching parsed feeds
	 *
	 * @param   int  $cacheLifetime
	 *
	 * @return  CallbackController
	 * @since   1.0.0
	 */
	private function getCache(int $cacheLifetime): CallbackController
	{
		$app = $this->app;

		$options = [
			'defaultgroup' => 'mod_fediversefeed',
			'cachebase'    => $app->get('cache_path', JPATH_CACHE),
			'lifetime'     => $cacheLifetime,
			'language'     => $app->get('language', 'en-GB'),
			'storage'      => $app->get('cache_handler', 'file'),
			'locking'      => true,
			'locktime'     => 15,
			'checkTime'    => true,
			'caching'      => true,

		];

		return Factory::getContainer()->get(CacheControllerFactoryInterface::class)->createCacheController('callback', $options);
	}
}