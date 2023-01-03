<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Module\FediverseFeed\Site\Service;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Cache\Controller\CallbackController;
use Joomla\CMS\Factory;

trait CacheTrait
{
	/**
	 * Returns a Joomla callback cache controller.
	 *
	 * Used to cache the fetched toot information.
	 *
	 * @return  CallbackController
	 *
	 * @since   1.0.0
	 */
	private function getCache(): CallbackController
	{
		return Factory::getContainer()
		              ->get(CacheControllerFactoryInterface::class)
		              ->createCacheController('callback', [
			              'defaultgroup' => 'mod_fediverse',
			              'cachebase'    => $this->app->get('cache_path', JPATH_CACHE),
			              'lifetime'     => $this->cacheLifetime,
			              'language'     => $this->app->get('language', 'en-GB'),
			              'storage'      => $this->app->get('cache_handler', 'file'),
			              'locking'      => true,
			              'locktime'     => 15,
			              'checkTime'    => true,
			              'caching'      => true,

		              ]);
	}
}