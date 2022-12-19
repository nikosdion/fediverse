<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Joomla\Plugin\System\WebFinger\Extension;

defined('_JEXEC') || die;

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
	 * @since   2.0.0
	 */
	private function getCache(): CallbackController
	{
		$app = $this->getApplication();

		return Factory::getContainer()
			->get(CacheControllerFactoryInterface::class)
			->createCacheController('callback', [
				'defaultgroup' => 'plg_' . $this->_type . '_' . $this->_name,
				'cachebase'    => $app->get('cache_path', JPATH_CACHE),
				'lifetime'     => $app->get('cachetime', 15),
				'language'     => $app->get('language', 'en-GB'),
				'storage'      => $app->get('cache_handler', 'file'),
				'locking'      => true,
				'locktime'     => 15,
				'checkTime'    => true,
				'caching'      => true,
			]);
	}

}