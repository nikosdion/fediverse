<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Plugin\Task\ActivityPub\Library;

use Composer\CaBundle\CaBundle;
use CurlMultiHandle;
use Dionysopoulos\Plugin\Task\ActivityPub\Library\DataShape\Request;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Version;
use RuntimeException;
use SplFixedArray;

/**
 * cURL Multi-request abstraction.
 *
 * Uses the cURL Multi option to execute a number of cURL requests in parallel.
 *
 * The callbacks have the following signature:
 * ```php
 * function (string $response, array $requestInfo): void
 * ```
 * The `$response` contains the body of the response, if one was returned
 *
 * The `$requestInfo` array is an associative array with the output of curl_getinfo plus the following keys:
 * * **result** `int`  The cURL request result.
 * * **handle** `resource|\CurlHandle` The cURL handle of the just-completed request.
 * * **request** `\Dionysopoulos\Plugin\Task\ActivityPub\Library\DataShape\Request` The request which was executed.
 * * **responseHeader** `string` The response header, if CURLOPT_HEADER was set in the options
 *
 * @since 2.0.0
 */
class MultiRequest
{
	/**
	 * The requests being queued.
	 *
	 * @var   SplFixedArray
	 * @since 2.0.0
	 */
	public SplFixedArray $requests;

	/**
	 * Shared cURL options.
	 *
	 * @var   array
	 * @since 2.0.0
	 */
	private array $options = [];

	/**
	 * Shared cURL request headers.
	 *
	 * @var   array
	 * @since 2.0.0
	 */
	private array $headers = [];

	/**
	 * Shared callback, used unless it's overridden in each request.
	 *
	 * @var   null|callable
	 * @since 2.0.0
	 */
	private $callback = null;

	/**
	 * Public constructor
	 *
	 * @param   int            $maxRequests     Maximum number of requests allowed in the queue.
	 * @param   int            $timeout         Request timeout, in milliseconds.
	 * @param   int|null       $connectTimeout  Connection timeout, in milliseconds. NULL to use $timeout
	 * @param   array          $headers         Additional default headers
	 * @param   array          $options         Additional default options
	 * @param   callable|null  $callback        Default callback
	 *
	 * @since   2.0.0
	 */
	public function __construct(
		int           $maxRequests = 10,
		int           $timeout = 5000,
		?int          $connectTimeout = null,
		array         $headers = [],
		array         $options = [],
		callable|null $callback = null
	)
	{
		$this->requests = new SplFixedArray($maxRequests);
		$this->callback = $callback;

		$this->setDefaultHeaders($headers);
		$this->setDefaultOptions($options, $timeout, $connectTimeout);
	}

	/**
	 * Enqueue a request
	 *
	 * @param   string             $url       The URL to access
	 * @param   array|string|null  $postData  POST/PUT data, if applicable.
	 * @param   callable|null      $callback  The callback for this request; null to use the shared callback.
	 * @param   mixed|null         $userData  Custom data to be passed to the callback, if applicable.
	 * @param   array|null         $options   cURL options to override the default (shared) ones, if applicable.
	 * @param   array|null         $headers   Custom headers to override the default (shared) ones, if applicable.
	 *                                        Provide as an associative array.
	 *
	 * @return  void
	 * @throws  RuntimeException  If you try to add more requests than the queue can handle.
	 * @since   2.0.0
	 */
	public function enqueue(
		string            $url,
		array|string|null $postData = null,
		?callable         $callback = null,
		mixed             $userData = null,
		?array            $options = null,
		?array            $headers = null
	): void
	{
		$this->requests[] = new Request(
			url: $url,
			postData: $postData,
			callback: $callback ?? $this->callback,
			userData: $userData,
			options: $options,
			headers: $headers
		);
	}

	/**
	 * Executes the enqueued requests
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	public function execute(): void
	{
		// The request map that maps the request queue to request curl handles
		$requestsMap = [];
		$multiHandle = curl_multi_init();

		/** @var Request|null $request */
		foreach ($this->requests as $i => $request)
		{
			if ($request !== null)
			{
				$this->enqueueCurlRequest($i, $multiHandle, $requestsMap);
			}
		}

		do
		{
			do
			{
				$mhStatus = curl_multi_exec($multiHandle, $active);
			} while ($mhStatus == CURLM_CALL_MULTI_PERFORM);

			if ($mhStatus != CURLM_OK)
			{
				break;
			}

			// A request is just completed, find out which one
			while ($completed = curl_multi_info_read($multiHandle))
			{
				$this->processRequest($completed, $multiHandle, $requestsMap);
			}

			// Save CPU cycles, prevent continuous checking
			usleep(500);
		} while ($active || count($requestsMap));

		$this->reset();

		curl_multi_close($multiHandle);
	}

	/**
	 * Resets the request queue
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	private function reset(): void
	{
		$length         = count($this->requests);
		$this->requests = new SplFixedArray($length);
	}

	/**
	 * Normalise headers the way cURL expects them.
	 *
	 * @param   array  $headers  The raw headers; associative array (Joomla! way), or array of strings (cURL way)
	 *
	 * @return  array  The normalised headers (array of strings)
	 * @since   2.0.0
	 */
	private function normalizeHeaders(array $headers): array
	{
		return array_map(
			fn($key, $value) => is_string($key) ? sprintf('%s: %s', strtolower($key), $value) : $value,
			array_keys($headers),
			array_values($headers)
		);
	}

	/**
	 * Returns the cURL options based on the request descriptor
	 *
	 * @param   Request  $request  The request descriptor
	 *
	 * @return  array  An array of cURL options
	 * @since   2.0.0
	 */
	private function getCurlOptions(Request $request): array
	{
		$url             = $request->url;
		$postData        = $request->postData;
		$overrideOptions = $request->options ?? [];
		$overrideHeaders = $request->headers ?? [];

		$options = array_merge_recursive($this->options, $overrideOptions);
		$headers = array_merge_recursive($this->headers, $overrideHeaders);

		$headers = array_combine(
			array_map('strtolower', array_keys($headers)),
			array_values($headers)
		);

		// Mandatory option overrides
		$options[CURLOPT_URL] = $url;

		// Allow timeout options to be overridden with values measured in seconds instead of milliseconds
		if (isset($options[CURLOPT_CONNECTTIMEOUT_MS]) && isset($options[CURLOPT_CONNECTTIMEOUT]))
		{
			unset($options[CURLOPT_CONNECTTIMEOUT_MS]);
		}

		if (isset($options[CURLOPT_TIMEOUT_MS]) && isset($options[CURLOPT_TIMEOUT]))
		{
			unset($options[CURLOPT_TIMEOUT_MS]);
		}

		// If we have POST data, enable POST method and set POST parameters
		if ($postData !== null)
		{
			// If PUT is not explicitly set, enable POST
			if (!isset($options[CURLOPT_PUT]) && !isset($options[CURLOPT_CUSTOMREQUEST]))
			{
				$options[CURLOPT_POST] = 1;
			}

			if (is_scalar($postData) || (isset($headers['content-type']) && str_starts_with($headers['content-type'], 'multipart/form-data')))
			{
				$options[CURLOPT_POSTFIELDS] = $postData;
			}
			else
			{
				$options[CURLOPT_POSTFIELDS] = http_build_query($postData);
			}

			$headers['content-type'] ??= 'application/x-www-form-urlencoded; charset=utf-8';

			// Add the relevant headers.
			if (is_scalar($options[CURLOPT_POSTFIELDS]))
			{
				$headers['content-length'] = strlen($options[CURLOPT_POSTFIELDS]);
			}
		}

		// Curl needs the Accept-Encoding header as an option
		if (isset($headers['accept-encoding']))
		{
			$options[CURLOPT_ENCODING] ??= $headers['accept-encoding'];

			unset($headers['accept-encoding']);
		}

		if (!empty($headers))
		{
			$options[CURLOPT_HTTPHEADER] = $this->normalizeHeaders($headers);
		}

		return $options;
	}

	/**
	 * Initialise a request and add it to the cURL Multi handle.
	 *
	 * @param   int                       $requestNumber  The numeric ID of the request in $this->requests
	 * @param   resource|CurlMultiHandle  $multiHandle    The cURL Multi handle
	 * @param   array                     $requestsMap    The map of cURL handles to numeric request IDs
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	private function enqueueCurlRequest(int $requestNumber, mixed $multiHandle, array &$requestsMap): void
	{
		/** @var Request|null $request */
		$request = $this->requests[$requestNumber];
		$request->startTimer();

		$ch                  = curl_init();
		$options             = $this->getCurlOptions($request);
		$request->optionsSet = $options;
		$hasSetOptions       = curl_setopt_array($ch, $options);

		if (!$hasSetOptions)
		{
			throw new RuntimeException('Could not set cURL options');
		}

		curl_multi_add_handle($multiHandle, $ch);

		// Add curl handle of a new request to the request map
		$ch_hash               = (string) $ch;
		$requestsMap[$ch_hash] = $requestNumber;
	}

	/**
	 * Handle a completed request.
	 *
	 * @param   array                     $completed    An array describing the completed request
	 * @param   resource|CurlMultiHandle  $multiHandle  The cURL Multi handle
	 * @param   array                     $requestsMap  The map of cURL handles to numeric request IDs
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	private function processRequest(array $completed, mixed $multiHandle, array &$requestsMap): void
	{
		/** @var Request|null $request */
		$ch      = $completed['handle'];
		$chHash  = (string) $ch;
		$request = $this->requests[$requestsMap[$chHash]];

		$request->stopTimer();

		$requestInfo            = curl_getinfo($ch);
		$requestInfo['request'] = $request;
		$requestInfo['result']  = $completed['result'];
		$requestInfo['handle']  = $ch;

		if (curl_errno($ch) !== 0 || intval($requestInfo['http_code']) !== 200)
		{
			$response = false;
		}
		else
		{
			$response = curl_multi_getcontent($ch);
		}

		// Get the request info
		$callback = $request->callback;
		$options  = $request->optionsSet;

		if ($response && isset($options[CURLOPT_HEADER]) && !empty($options[CURLOPT_HEADER]))
		{
			$k                             = intval($requestInfo['header_size']);
			$requestInfo['responseHeader'] = substr($response, 0, $k);
			$response                      = substr($response, $k);
		}

		// Remove the completed request and its cURL handle
		unset($requestsMap[$chHash]);

		curl_multi_remove_handle($multiHandle, $ch);

		// Call the callback function and pass request info and user data to it
		if ($callback)
		{
			call_user_func($callback, $response, $requestInfo);
		}

		// Mark memory for garbage collection
		$request = null;
	}

	/**
	 * Set the default headers.
	 *
	 * @param   array  $headers  The headers provided by the user
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	private function setDefaultHeaders(array $headers): void
	{
		/**
		 * The Expect header is set to an empty string, otherwise we might get an extra HTTP 100 header.
		 *
		 * @see https://web.archive.org/web/20141112193700/http://the-stickman.com/web-development/php-and-curl-disabling-100-continue-header/
		 */
		$headers['Expect'] ??= '';

		$this->headers = $headers;
	}

	/**
	 * Set the default cURL options
	 *
	 * @param   array     $options         The options provided by the user
	 * @param   int       $timeout         The request timeout, in milliseconds
	 * @param   int|null  $connectTimeout  The connection timeout, in milliseconds
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	private function setDefaultOptions(array $options, int $timeout = 5000, ?int $connectTimeout = null): void
	{
		$options = array_merge_recursive(
			[
				CURLOPT_USERAGENT         => (new Version())->getUserAgent('Joomla', true, false),
				CURLOPT_CONNECTTIMEOUT_MS => $connectTimeout ?? $timeout,
				CURLOPT_TIMEOUT_MS        => $timeout,
			],
			$options
		);

		// Allow timeout options to be overridden with values measured in seconds instead of milliseconds
		if (isset($options[CURLOPT_CONNECTTIMEOUT_MS]) && isset($options[CURLOPT_CONNECTTIMEOUT]))
		{
			unset($options[CURLOPT_CONNECTTIMEOUT_MS]);
		}

		if (isset($options[CURLOPT_TIMEOUT_MS]) && isset($options[CURLOPT_TIMEOUT]))
		{
			unset($options[CURLOPT_TIMEOUT_MS]);
		}

		// Use the Joomla! proxy configuration, if enabled
		try
		{
			$app = Factory::getApplication();

			if ($app->get('proxy_enable'))
			{
				$options[CURLOPT_PROXY] ??= $app->get('proxy_host') . ':' . $app->get('proxy_port');

				if ($user = $app->get('proxy_user'))
				{
					$options[CURLOPT_PROXYUSERPWD] ??= $user . ':' . $app->get('proxy_pass');
				}
			}
		}
		catch (Exception $e)
		{
			// Well, we can't get the application, therefore we can't figure out the proxy information.
		}

		/**
		 * Set the CA path or CA file. Preference: system CA path, system CA file, bundled CA file.
		 *
		 * You can override by passing this in your options:
		 * $options[CURLOPT_CAINFO] = CaBundle::getBundledCaBundlePath();
		 * $options[CURLOPT_CAPATH] = null;
		 */
		$options[CURLOPT_CAINFO] ??= CaBundle::getBundledCaBundlePath();
		$caPathOrFile            = CaBundle::getSystemCaRootBundlePath();

		if (is_dir($caPathOrFile) || (is_link($caPathOrFile) && is_dir(readlink($caPathOrFile))))
		{
			$options[CURLOPT_CAPATH] ??= $caPathOrFile;
		}
		elseif (is_file($caPathOrFile) && is_readable($caPathOrFile))
		{
			$options[CURLOPT_CAINFO] ??= $caPathOrFile;
		}

		$this->options = $options;
	}
}
