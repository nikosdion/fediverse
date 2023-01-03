<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Module\FediverseFeed\Site\Service;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Http\Http;
use JsonException;
use RuntimeException;
use stdClass;
use Throwable;

class TootStreamLoader
{
	use CacheTrait;

	public function __construct(
		private Http           $http,
		private CMSApplication $app,
		private AccountLoader  $accountLoader,
		private int            $maxToots = 20,
		private int            $cacheLifetime = 86400,
		private int            $requestTimeout = 5,
		private bool           $useCaching = true,
	) {}

	/**
	 * Returns the toots stream for an account given its username.
	 *
	 * @param   string         $username     The Mastodon username, e.g. `@nikosdion@fosstodon.org`.
	 * @param   stdClass|null  $accountInfo  The Mastodon account information, if known in advance.
	 *
	 * @return  array|null  An array of stdClass objects conforming to Mastodon's `Status` object spec.
	 *
	 * @since   1.0.0
	 * @see     https://docs.joinmastodon.org/entities/status/
	 */
	public function getStreamForUsername(string $username, ?stdClass $accountInfo): ?array
	{
		$accountInfo = $accountInfo ?? $this->accountLoader->getInformationFromUsername($username);

		if (empty($accountInfo))
		{
			return null;
		}

		$username = trim($username, "@\ \t\n\r\0\x0B");

		if (!str_contains($username, '@'))
		{
			return null;
		}

		[, $server] = explode('@', $username, 2);

		return $this->getStream($server, $accountInfo->id);
	}

	/**
	 * Returns the toots stream for an account given a server and user ID
	 *
	 * @param   string  $server  The server domain name, e.g. `fosstodon.org`
	 * @param   int     $userId  The user ID on the server, e.g. `109303798251349833`
	 *
	 * @return  array|null  An array of stdClass objects conforming to Mastodon's `Status` object spec.
	 *
	 * @since   1.0.0
	 * @see     https://docs.joinmastodon.org/entities/status/
	 */
	public function getStream(string $server, int $userId): ?array
	{
		if (!$this->useCaching)
		{
			try
			{
				return $this->loadStream($server, $userId);
			}
			catch (Throwable $e)
			{
				return null;
			}
		}

		return $this->getCache()
		            ->get(
			            function ($server, $userId) {
				            try
				            {
					            return $this->loadStream($server, $userId);
				            }
				            catch (\Exception $e)
				            {
					            return null;
				            }
			            },
			            [$server, $userId]
		            );
	}

	/**
	 * Loads the toots timeline stream from the Mastodon server using the public API
	 *
	 * @param   string  $server  The server domain name, e.g. `fosstodon.org`
	 * @param   int     $userId  The user ID on the server, e.g. `109303798251349833`
	 *
	 * @return  array  An array of stdClass objects conforming to Mastodon's `Status` object spec.
	 * @throws  JsonException
	 *
	 * @since   1.0.0
	 * @see     https://docs.joinmastodon.org/entities/status/
	 */
	private function loadStream(string $server, int $userId): array
	{
		$apiUrl   = sprintf("https://%s/api/v1/accounts/%d/statuses?limit=%u", $server, $userId, $this->maxToots);
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

		if (!is_array($ret))
		{
			throw new RuntimeException('This is not the Mastodon account response we were looking for.');
		}

		return $ret;
	}


}