<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\Service;

use ActivityPhp\Type\Extended\AbstractActor;
use Dionysopoulos\Component\ActivityPub\Administrator\DataShape\KeyPair;
use Dionysopoulos\Component\ActivityPub\Administrator\Mixin\GetActorTrait;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\ActorTable;
use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

/**
 * ActivityPub signature service
 *
 * @since  2.0.0
 */
class Signature implements DatabaseAwareInterface
{
	use DatabaseAwareTrait;
	use GetActorTrait;

	/**
	 * The #__user_profiles key name for the JSON-serialised ActivityPub key pair
	 *
	 * @since 2.0.0
	 * @var   string
	 */
	private const PROFILE_KEY = 'activitypub.keypair';

	/**
	 * Internal memory cache of key pairs, indexed by user ID
	 *
	 * @since 2.0.0
	 * @var   KeyPair[]
	 */
	private static array $cache = [];

	/**
	 * Constructor
	 *
	 * @param   DatabaseInterface  $db  The Joomla database driver object
	 *
	 * @since   2.0.0
	 */
	public function __construct(
		DatabaseInterface            $db,
		private UserFactoryInterface $userFactory,
		private CMSApplication       $application,
	)
	{
		$this->setDatabase($db);
	}

	/**
	 * Generate the base64-encoded binary SHA-256 digest for some content
	 *
	 * @param   string  $body  The
	 *
	 * @return  string
	 * @since   2.0.0
	 * @see     self::sign()
	 */
	public function digest(string $body): string
	{
		return base64_encode(hash('sha256', $body, true));
	}

	/**
	 * Creates a Signature header for an ActivityPub POST request
	 *
	 * @param   ActorTable   $actorTable    The Actor signing this request
	 * @param   string       $url           The remote URL where we will be POSTing data
	 * @param   Date|null    $date          The current date and time
	 * @param   string|null  $sha256Digest  (Optional) The base64-encoded SHA-256 binary digest of the POST content
	 *
	 * @return  string
	 * @since   2.0.0
	 * @see     self::digest()
	 */
	public function sign(ActorTable $actorTable, string $url, ?Date $date = null, string $sha256Digest = null): ?string
	{
		$actorParams = new Registry($actorTable->params);
		$keyPairJSON = $actorParams->get('core.keyPair', null) ?? '{}';
		try
		{
			$keyPair = KeyPair::fromJson($keyPairJSON);
		}
		catch (Exception $e)
		{
			return null;
		}

		$privateKey = $keyPair->getPrivateKey();
		$uri        = new Uri($url);
		$host       = $uri->getHost();
		$path       = $uri->getPath();
		$date       = $date ?? clone Factory::getDate();

		$plaintext = sprintf(
			"(request-target): post %s\nhost: %s\ndate: %s",
			$path,
			$host,
			$date->format(\DateTimeInterface::RFC7231, false, false)
		);

		if ($sha256Digest !== null)
		{
			$plaintext .= sprintf("\ndigest: SHA-256=%s", $sha256Digest);
		}

		$signature = null;

		openssl_sign($plaintext, $signature, $privateKey, OPENSSL_ALGO_SHA256);

		$signature = base64_encode($signature);

		$user = ($actorTable->user_id > 0)
			? $this->userFactory->loadUserById($actorTable->user_id)
			: $this->getUserFromUsername($actorTable->username);
		$key_id = $this->getApiUriForUser($user) . '#main-key';

		if ($sha256Digest !== null)
		{
			return \sprintf('keyId="%s",algorithm="rsa-sha256",headers="(request-target) host date digest",signature="%s"', $key_id, $signature);
		}

		return \sprintf('keyId="%s",algorithm="rsa-sha256",headers="(request-target) host date",signature="%s"', $key_id, $signature);
	}

	/**
	 * Verify the signature for the current request.
	 *
	 * @param   AbstractActor  $actor  The actor object which provides the signing key
	 *
	 * @return  bool
	 * @throws  Exception
	 *
	 * @since   2.0.0
	 */
	public function verify(AbstractActor $actor): bool
	{
		$allHeaders = $this->getAllHeaders();

		// Read the Signature header
		$signature = $allHeaders['signature'] ?? null;

		if (empty(trim($signature ?? '')))
		{
			return false;
		}

		// Split the signature header into its parts (keyId, headers and signature)
		$parts = $this->splitSignature($signature);

		if (!count($parts))
		{
			return false;
		}

		if ($parts['algorithm'] !== 'rsa-sha256')
		{
			return false;
		}

		try
		{
			$publicKeyPem = $actor->publicKey['publicKeyPem'] ?? null;
		}
		catch (\Throwable $e)
		{
			$publicKeyPem = null;
		}

		if (empty($publicKeyPem))
		{
			return false;
		}

		// Get a plaintext to sign using the headers specified in the Signature header, in the order specified.
		$data = $this->getPlainText(
			explode(' ', trim($parts['headers'])),
			$allHeaders
		);

		$signatureValidity = openssl_verify($data, base64_decode($parts['signature']), $publicKeyPem, OPENSSL_ALGO_SHA256);

		return $signatureValidity === 1;
	}

	/**
	 * Split HTTP signature into its parts (keyId, headers and signature)
	 *
	 * @since 2.0.0
	 */
	private function splitSignature(string $signature): array
	{
		$regex = '/^
        keyId="(?P<keyId>
            (https?:\/\/[\w\-\.]+[\w]+)
            (:[\d]+)?
            ([\w\-\.#\/@]+)
        )",
        (algorithm="(?P<algorithm>[\w\s-]+)",)?
        (headers="\(request-target\) (?P<headers>[\w\s-]+)",)?
        signature="(?P<signature>[\w+\/]+={0,2})"
    /x';

		$allowedKeys = [
			'keyId',
			'algorithm',
			'headers',
			'signature',
		];

		if (!preg_match($regex, $signature, $matches))
		{
			return [];
		}

		// Headers are optional
		$matches['headers'] ??= 'date';
		$matches['headers'] = $matches['headers'] ?: 'date';

		// Algorithm is optional
		$matches['algorithm'] ??= 'rsa-sha256';

		return array_filter($matches, function ($key) use ($allowedKeys) {
			return !is_int($key) && in_array($key, $allowedKeys);
		}, ARRAY_FILTER_USE_KEY);
	}

	/**
	 * Generate a new key pair for a user
	 *
	 * @param   int  $userId  The ID of the user we are creating a new public key for
	 *
	 * @return  KeyPair
	 * @since   2.0.0
	 */
	private function generateKeyPair(int $userId): KeyPair
	{
		self::$cache[$userId] = KeyPair::create();

		$this->saveKeyPair($userId, self::$cache[$userId]);

		return self::$cache[$userId];
	}

	/**
	 * Load a keypair from the database (or return it from the memory cache).
	 *
	 * @param   int  $userId
	 *
	 * @return  KeyPair
	 * @since   2.0.0
	 */
	private function loadKeyPair(int $userId): KeyPair
	{
		if (isset(self::$cache[$userId]))
		{
			return self::$cache[$userId];
		}

		/** @var DatabaseDriver $db */
		$db         = $this->getDatabase();
		$profileKey = self::PROFILE_KEY;

		$query = $db->getQuery(true)
			->select($db->quoteName('profile_value'))
			->from($db->quoteName('#__user_profiles'))
			->where([
				$db->quoteName('user_id') . ' = :userId',
				$db->quoteName('profile_key') . ' = :profileKey',
			])
			->bind(':userId', $userId, ParameterType::INTEGER)
			->bind(':profileKey', $profileKey, ParameterType::STRING);

		$json = $db->setQuery($query)->loadResult() ?: '';

		try
		{
			self::$cache[$userId] = KeyPair::fromJson($json);
		}
		catch (Exception $e)
		{
			self::$cache[$userId] = $this->generateKeyPair($userId);
		}

		return self::$cache[$userId];
	}

	/**
	 * Save a key pair to the database
	 *
	 * @param   int      $userId   The user ID for whom the key pair is for
	 * @param   KeyPair  $keyPair  The key pair to save
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	private function saveKeyPair(int $userId, KeyPair $keyPair): void
	{
		/** @var DatabaseDriver $db */
		$db         = $this->getDatabase();
		$profileKey = self::PROFILE_KEY;

		$query = $db->getQuery(true)
			->delete($db->quoteName('#__user_profiles'))
			->where([
				$db->quoteName('user_id') . ' = :userId',
				$db->quoteName('profile_key') . ' = :profileKey',
			])
			->bind(':userId', $userId, ParameterType::INTEGER)
			->bind(':profileKey', $profileKey, ParameterType::STRING);

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (Exception $e)
		{
			// Eventually we'll be getting an exception on failed DELETE queries.
		}

		$record = (object) [
			'user_id'       => $userId,
			'profile_key'   => $profileKey,
			'profile_value' => json_encode($keyPair),
			'ordering'      => 0,
		];

		$db->insertObject('#__user_profiles', $record);
	}

	/**
	 * Returns all headers of the current request
	 *
	 * @return  array
	 * @throws  Exception
	 *
	 * @since   2.0.0
	 */
	private function getAllHeaders(): array
	{
		$serverInput      = Factory::getApplication()->input->server;
		$allHeaders = array_filter(
			$serverInput->getArray(),
			fn($x) => str_starts_with($x, 'HTTP_'),
			ARRAY_FILTER_USE_KEY
		);
		$allHeaders = array_combine(
			array_map(
				fn($x) => str_starts_with($x, 'HTTP_')
					? str_replace('_', '-', substr($x, 5))
					: $x,
				array_keys($allHeaders)
			),
			array_values($allHeaders)
		);

		$headers = array_change_key_case($allHeaders, CASE_LOWER);

		/**
		 * If we are behind a load balancer Uri::getInstance has the external hostname we were contacted on, whereas the
		 * Host HTTP header has the internal hostname the load balancer contacted us on.
		 */
		//$headers['host'] = Uri::getInstance()->getHost() . ':' . Uri::getInstance()->getPort();
		$headers['host'] = Uri::getInstance()->getHost();

		/**
		 * PHP returns the Content-Type and Content-Length header as CONTENT_TYPE and CONTENT_LENGTH because f**k you,
		 * that's why.
		 */
		if ($serverInput->getRaw('CONTENT_TYPE'))
		{
			$headers['content-type'] = $serverInput->getRaw('CONTENT_TYPE');
		}

		if ($serverInput->getRaw('CONTENT_LENGTH'))
		{
			$headers['content-length'] = $serverInput->getRaw('CONTENT_LENGTH');
		}

		return $headers;
	}

	/**
	 * Get the plaintext signed based on the request headers
	 *
	 * @param   array  $headers         The header keys participating in the plaintext to be signed
	 * @param   array  $requestHeaders  The headers defined in the request
	 *
	 * @return  string
	 * @since   2.0.0
	 */
	private function getPlainText(array $headers, array $requestHeaders): string
	{
		$uri = Uri::getInstance();

		$strings   = [];
		$strings[] = sprintf(
			'(request-target): %s %s%s',
			strtolower($this->application->input->getMethod()),
			$uri->getPath(),
			$uri->getQuery() ? ('?' . $uri->getQuery()) : ''
		);

		foreach ($headers as $key)
		{
			if (!isset($requestHeaders[$key]))
			{
				continue;
			}

			$strings[] = $key . ': ' . $requestHeaders[$key];
		}

		return implode("\n", $strings);
	}

}