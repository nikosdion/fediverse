<?php

/*
 * This file is part of the ActivityPhp package.
 *
 * Copyright (c) landrok at github.com/landrok
 *
 * For the full copyright and license information, please see
 * <https://github.com/landrok/activitypub/blob/master/LICENSE>.
 */

namespace ActivityPhp\Server\Cache;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Cache\Cache;
use Joomla\CMS\Factory;

/**
 * \ActivityPhp\Server\CacheHelper provides global helper methods for
 * cache manipulation.
 */
abstract class CacheHelper
{
	private static ?Cache $cache = null;

	/**
	 * Set a cache item
	 *
	 * @param   string  $key
	 * @param   mixed   $value
	 */
	public static function set(string $key, $value)
	{
		self::getCache()->store($value, ApplicationHelper::getHash($key));
	}

	/**
	 * Get a cache item content
	 *
	 * @return mixed
	 */
	public static function get(string $key)
	{
		return self::getCache()->get(ApplicationHelper::getHash($key)) ?: null;
	}

	/**
	 * Check that a cache item exists
	 *
	 * @return bool
	 */
	public static function has(string $key)
	{
		return self::getCache()->contains(ApplicationHelper::getHash($key));
	}

	private static function getCache(): Cache
	{
		$app         = Factory::getApplication();
		self::$cache = self::$cache ?? new Cache(
			[
				'defaultgroup' => 'com_activitypub.requestCache',
				'cachebase'    => $app->get('cache_path', JPATH_CACHE),
				'lifetime'     => $app->get('cachetime', 15),
				'language'     => $app->get('language', 'en-GB'),
				'storage'      => $app->get('cache_handler', 'file'),
				'locking'      => true,
				'locktime'     => 15,
				'checkTime'    => true,
				'caching'      => true,
			]
		);

		return self::$cache;
	}
}
