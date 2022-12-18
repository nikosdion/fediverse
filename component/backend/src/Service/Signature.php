<?php
/*
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   GNU General Public License, version 3
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\Service;

use Dionysopoulos\Component\ActivityPub\Administrator\DataShape\KeyPair;
use Dionysopoulos\Component\ActivityPub\Administrator\Traits\ActorUrlTrait;
use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * ActivityPub signature service
 *
 * @since  2.0.0
 */
class Signature implements DatabaseAwareInterface
{
	use DatabaseAwareTrait;
	use ActorUrlTrait;

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
	 * Get the private key for a specific user
	 *
	 * @param   int   $userId  The ID of the user to get the private key for
	 * @param   bool  $force   When true, a new key pair will be created even if one already exists
	 *
	 * @return  string
	 * @since   2.0.0
	 */
	public function getPrivateKey(int $userId = 0, bool $force = false): string
	{
		return ($force ? $this->generateKeyPair($userId) : $this->loadKeyPair($userId))
			->getPrivateKey();
	}

	/**
	 * Get the public key for a specific user
	 *
	 * @param   int   $userId  The ID of the user to get the public key for
	 * @param   bool  $force   When true, a new key pair will be created even if one already exists
	 *
	 * @return  string
	 * @since   2.0.0
	 */
	public function getPublicKey(int $userId = 0, bool $force = false): string
	{
		return ($force ? $this->generateKeyPair($userId) : $this->loadKeyPair($userId))
			->getPublicKey();
	}

	/**
	 * Creates a Signature header for an ActivityPub POST request
	 *
	 * @param   int          $userId        The userID who is sending the request
	 * @param   string       $url           The remote URL where we will be POSTing data
	 * @param   Date|null    $date          The current date and time
	 * @param   string|null  $sha256Digest  (Optional) The base64-encoded SHA-256 binary digest of the POST content
	 *
	 * @return  string
	 * @since   2.0.0
	 * @see     self::digest()
	 */
	public function sign(int $userId, string $url, ?Date $date = null, string $sha256Digest = null): string
	{
		$privateKey = $this->getPrivateKey($userId);
		$uri        = new Uri($url);
		$host       = $uri->getHost();
		$path       = $uri->getPath();
		$date       = $date ?? Date::getInstance();

		$signedString = sprintf(
			"(request-target): post %s\nhost: %s\ndate: %s",
			$path,
			$host,
			$date->format(\DateTimeInterface::RFC7231, false, false)
		);

		if ($sha256Digest !== null)
		{
			$signedString .= sprintf("\ndigest: SHA-256=%s", $sha256Digest);
		}

		$signature = null;

		openssl_sign($signedString, $signature, $privateKey, OPENSSL_ALGO_SHA256);

		$signature = base64_encode($signature);

		// TODO We need to implement a method to get the actor URL for a user ID
		$key_id = $this->getActorUrl($userId) . '#main-key';

		if ($sha256Digest !== null)
		{
			return \sprintf('keyId="%s",algorithm="rsa-sha256",headers="(request-target) host date digest",signature="%s"', $key_id, $signature);
		}

		return \sprintf('keyId="%s",algorithm="rsa-sha256",headers="(request-target) host date",signature="%s"', $key_id, $signature);
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
}