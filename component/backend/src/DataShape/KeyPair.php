<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2024 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\DataShape;

/**
 * RSA Key Pair abstraction
 *
 * @since 2.0.0
 */
class KeyPair implements \JsonSerializable
{
	/**
	 * Constructor.
	 *
	 * @param   string  $privateKey  The private key
	 * @param   string  $publicKey   The public key
	 *
	 * @since   2.0.0
	 */
	public function __construct(
		private string $privateKey,
		private string $publicKey,
	) {}

	/**
	 * Returns a new key pair
	 *
	 * @return  static
	 * @since   2.0.0
	 */
	public static function create(): self
	{
		$key = openssl_pkey_new([
			'digest_alg'       => 'sha512',
			'private_key_bits' => 2048,
			'private_key_type' => \OPENSSL_KEYTYPE_RSA,
		]);

		$privateKey = null;
		$details    = openssl_pkey_get_details($key);

		openssl_pkey_export($key, $privateKey);
		$publicKey = $details['key'];

		return new self(
			privateKey: $privateKey,
			publicKey : $publicKey
		);
	}

	/**
	 * Returns a key pair from its JSON-serialised representation
	 *
	 * @param   string  $json  The JSON-serialised representation of the key pair
	 *
	 * @return  static
	 * @since   2.0.0
	 */
	public static function fromJson(string $json): self
	{
		$data = @json_decode($json);

		if (!is_object($data) || !isset($data->privateKey) || !isset($data->publicKey))
		{
			throw new \InvalidArgumentException('JSON data does not describe a valid key pair');
		}

		return new self(
			privateKey: $data->privateKey,
			publicKey : $data->publicKey
		);
	}

	/**
	 * Get the private key
	 *
	 * @return  string
	 * @since   2.0.0
	 */
	public function getPrivateKey(): string
	{
		return $this->privateKey;
	}

	/**
	 * Get the public key
	 *
	 * @return  string
	 * @since   2.0.0
	 */
	public function getPublicKey(): string
	{
		return $this->publicKey;
	}

	/**
	 * Specify data which should be serialized to JSON
	 *
	 * @return  mixed data which can be serialized by <b>json_encode</b>
	 * @since   2.0.0
	 */
	public function jsonSerialize(): mixed
	{
		return [
			'privateKey' => $this->privateKey,
			'publicKey'  => $this->publicKey,
		];
	}
}