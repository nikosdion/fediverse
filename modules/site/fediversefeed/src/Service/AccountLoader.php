<?php
/*
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   GNU General Public License, version 3
 */

namespace Joomla\Module\FediverseFeed\Site\Service;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Http\Http;
use JsonException;
use RuntimeException;
use Throwable;

class AccountLoader
{
	use CacheTrait;

	public function __construct(
		private Http           $http,
		private CMSApplication $app,
		private int            $cacheLifetime = 86400,
		private int            $requestTimeout = 5,
		private bool           $useCaching = true,
	) {}

	/**
	 * Get the account information from the username
	 *
	 * @param   string  $username  The Mastodon username, e.g. `@nikosdion@fosstodon.org`
	 *
	 * @return  object|null  The raw Mastodon API object of the type `Account`
	 *
	 * @since   1.0.0
	 * @see     https://docs.joinmastodon.org/entities/account/
	 */
	public function getInformationFromUsername(string $username): ?object
	{
		$username = trim($username, "@\ \t\n\r\0\x0B");

		if (!str_contains($username, '@'))
		{
			return null;
		}

		[$username, $server] = explode('@', $username, 2);

		return $this->getInformation($server, $username);
	}

	/**
	 * Get the account information given a server name and a username
	 *
	 * @param   string  $server    The domain name of the server, e.g. `fosstodon.org`
	 * @param   string  $username  The username on the server, e.g. `nikosdion`
	 *
	 * @return  object|null  The raw Mastodon API object of the type `Account`
	 *
	 * @since   1.0.0
	 * @see     https://docs.joinmastodon.org/entities/account/
	 */
	public function getInformation(string $server, string $username): ?object
	{
		if (!$this->useCaching)
		{
			try
			{
				return $this->loadAccount($server, $username);
			}
			catch (Throwable $e)
			{
				return null;
			}
		}

		return $this->getCache()
		            ->get(
			            function ($server, $username) {
				            try
				            {
					            return $this->loadAccount($server, $username);
				            }
				            catch (\Exception $e)
				            {
					            return null;
				            }
			            },
			            [$server, $username]
		            );
	}

	/**
	 * Loads the account information from the Mastodon server
	 *
	 * @param   string  $server    The server domain name, e.g. `fosstodon.org`
	 * @param   string  $username  The username on the server, e.g. `nikosdion`
	 *
	 * @return  object  The raw Mastodon API object of the type `Account`
	 * @throws  JsonException
	 *
	 * @since   1.0.0
	 * @see     https://docs.joinmastodon.org/entities/account/
	 */
	private function loadAccount(string $server, string $username): object
	{
		$account  = sprintf('%s@%s', $username, $server);
		$apiUrl   = sprintf("https://%s/api/v1/accounts/lookup?acct=%s", $server, urlencode($account));
		$response = $this->http->get($apiUrl, [], $this->requestTimeout);

		if ($response->getStatusCode() !== 200)
		{
			throw new RuntimeException(
				sprintf(
					'Mastodon server returned HTTP status %d',
					$response->getStatusCode()
				)
			);
		}

		if (!str_starts_with(
			$contentType = implode('', $response->getHeader('content-type')),
			'application/json'
		))
		{
			throw new RuntimeException(
				sprintf(
					'Invalid content type %s in Mastodon server response',
					$contentType
				)
			);
		}

		$ret = @json_decode($response->body, flags: JSON_THROW_ON_ERROR);

		if ($ret?->username != $username)
		{
			throw new RuntimeException('This is not the Mastodon account response we were looking for.');
		}

		return $ret;
	}
}