<?php

/*
 * This file is part of the ActivityPhp package.
 *
 * Copyright (c) landrok at github.com/landrok
 *
 * For the full copyright and license information, please see
 * <https://github.com/landrok/activitypub/blob/master/LICENSE>.
 */

namespace ActivityPhp\Server\Http;

use ActivityPhp\Server\Cache\CacheHelper;
use Joomla\CMS\Http\HttpFactory;

/**
 * Request handler
 */
class Request
{
	const HTTP_HEADER_ACCEPT = 'application/activity+json,application/ld+json,application/json';

	/**
	 * @var string HTTP method
	 */
	protected $method = 'GET';

	/**
	 * Allowed HTTP methods
	 *
	 * @var array
	 */
	protected $allowedMethods = [
		'GET', 'POST',
	];

	protected $timeout = 10.0;

	/**
	 * HTTP client
	 *
	 * @var \Joomla\Http\Http
	 */
	protected $client;

	/**
	 * Set HTTP client
	 *
	 * @param   float|int  $timeout
	 * @param   string     $agent
	 */
	public function __construct($timeout = 10.0, $agent = '')
	{
		$this->timeout = $timeout;
		$options       = [];

		if (defined('JDEBUG') && JDEBUG)
		{
			/**
			 * When debug mode is enabled we don't check SSL/TLS certificates.
			 *
			 * This allows me to test against a local Mastodon installation with a self-signed certificate.
			 */
			$options['transport.curl'] = [
				CURLOPT_SSL_VERIFYHOST   => 0,
				CURLOPT_SSL_VERIFYPEER   => 0,
				CURLOPT_SSL_VERIFYSTATUS => 0,
			];
		}

		if ($agent)
		{
			$options['userAgent'] = $agent;
		}

		$this->client = HttpFactory::getHttp($options, ['curl']);
	}

	/**
	 * Execute a GET request
	 *
	 * @param   string  $url
	 *
	 * @return string
	 */
	public function get(string $url)
	{
		if (CacheHelper::has($url))
		{
			return CacheHelper::get($url);
		}

		$headers = ['Accept' => self::HTTP_HEADER_ACCEPT];
		$content = $this->client->get($url, $headers, $this->timeout)->body;

		CacheHelper::set($url, $content);

		return $content;
	}

	/**
	 * Get HTTP methods
	 *
	 * @return string
	 */
	protected function getMethod()
	{
		return $this->method;
	}

	/**
	 * Set HTTP methods
	 *
	 * @param   string  $method
	 */
	protected function setMethod(string $method)
	{
		if (in_array($method, $this->allowedMethods))
		{
			$this->method = $method;
		}
	}
}
