<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Api\Model;

defined('_JEXEC') || die;

use ActivityPhp\Type;
use ActivityPhp\Type\Extended\AbstractActor;
use Dionysopoulos\Component\ActivityPub\Administrator\DataShape\KeyPair;
use Dionysopoulos\Component\ActivityPub\Administrator\Mixin\GetActorTrait;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\ActorTable;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

class ActorModel extends BaseDatabaseModel
{
	use GetActorTrait;

	/**
	 * Get an Actor object given a username
	 *
	 * @param   string|null  $username  The username. If missing, it will look in the `actor.username` state variable.
	 *
	 * @return  AbstractActor|null  The Actor object. NULL if not found.
	 * @throws  \Exception
	 * @since   2.0.0
	 */
	public function getItem(?string $username = null): ?AbstractActor
	{
		$username   ??= $this->getState('actor.username', null);
		$user       = $this->getUserFromUsername($username);
		$actorTable = $this->getActorRecordForUser($user, true);

		if ($user === null || $actorTable === null)
		{
			return null;
		}


		$type     = $user->params->get('activitypub.type', 'Person') ?: 'Person';
		$language = $user->params->get('language', Factory::getApplication()->getLanguage()->getTag() ?: 'en-GB');

		$signatureUri = new Uri($this->getApiUriForUser($user));
		$signatureUri->setFragment('main-key');

		$actorParams = $this->getUserActivityPubParams($actorTable);

		try
		{
			$keyPair      = KeyPair::fromJson($actorParams->get('core.keyPair'));
			$publicKeyPem = $keyPair->getPublicKey();
		}
		catch (\Exception $e)
		{
			$publicKeyPem = null;
		}

		$iconSource  = $actorParams->get('activitypub.icon_source');
		$profileIcon = match ($iconSource)
		{
			default => null,
			'gravatar' => $user->email ? sprintf('https://www.gravatar.com/avatar/%s?s=%s', md5(strtolower(trim($user->email))), 256) : null,
			'url' => $actorParams->get('activitypub.url') ?: null,
			'media' => $this->getFrontendBasePath() . '/' . HTMLHelper::cleanImageURL(
					$actorParams->get('activitypub.media')
				)->url
		};

		$mediaType = $iconSource === 'gravatar' ? 'image/jpeg' : '';

		if (str_ends_with(strtolower($profileIcon ?? ''), '.jpg'))
		{
			$mediaType = 'image/jpeg';
		}
		elseif (str_ends_with(strtolower($profileIcon ?? ''), '.jpeg'))
		{
			$mediaType = 'image/jpeg';
		}
		elseif (str_ends_with(strtolower($profileIcon ?? ''), '.png'))
		{
			$mediaType = 'image/png';
		}
		elseif (str_ends_with(strtolower($profileIcon ?? ''), '.gif'))
		{
			$mediaType = 'image/gif';
		}
		elseif (str_ends_with(strtolower($profileIcon ?? ''), '.webp'))
		{
			$mediaType = 'image/webp';
		}
		elseif (str_ends_with(strtolower($profileIcon ?? ''), '.svg'))
		{
			$mediaType = 'image/svg+xml';
		}
		elseif (str_ends_with(strtolower($profileIcon ?? ''), '.bmp'))
		{
			$mediaType = 'image/bmp';
		}
		elseif (str_ends_with(strtolower($profileIcon ?? ''), '.ico'))
		{
			$mediaType = 'image/vnd.microsoft.icon';
		}
		elseif (str_ends_with(strtolower($profileIcon ?? ''), '.tif'))
		{
			$mediaType = 'image/tiff';
		}
		elseif (str_ends_with(strtolower($profileIcon ?? ''), '.tiff'))
		{
			$mediaType = 'image/tiff';
		}

		/**
		 * @see https://www.w3.org/TR/activitypub/#actors
		 */
		$actorConfiguration = [
			'@context'          => [
				'https://www.w3.org/ns/activitystreams',
				'https://w3id.org/security/v1',
				[
					'@language' => $language,
				],
			],
			'id'                => $this->getApiUriForUser($user),
			'type'              => $type,
			'preferredUsername' => $user->username,
			'name'              => $user->name,
			// See https://www.w3.org/TR/activitypub/#inbox
			'inbox'             => $this->getApiUriForUser($user, 'inbox'),
			// See https://www.w3.org/TR/activitypub/#outbox
			'outbox'            => $this->getApiUriForUser($user, 'outbox'),
			'endpoints'         => [
				// See https://www.w3.org/TR/activitypub/#shared-inbox-delivery
				'sharedInbox' => $this->getApiUriForUser($user, 'sharedInbox'),
			],
			// See https://w3c-ccg.github.io/security-vocab/#publicKeyPem and https://blog.joinmastodon.org/2018/06/how-to-implement-a-basic-activitypub-server/
			'publicKey'         => [
				'id'           => $signatureUri->toString(),
				'owner'        => $this->getApiUriForUser($user),
				'publicKeyPem' => $publicKeyPem,
			],
			'summary'           => $actorParams->get('activitypub.summary', '') ?? '',
			'icon'              => [
				'type'      => 'Image',
				'mediaType' => $mediaType,
				'url'       => $profileIcon,
			],
		];

		if (empty($publicKeyPem))
		{
			unset($actorConfiguration['publicKey']);
		}

		if (empty($mediaType) || empty($profileIcon))
		{
			unset($actorConfiguration['icon']);
		}

		if (empty(trim(strip_tags($actorConfiguration['summary']))))
		{
			unset($actorConfiguration['summary']);
		}

		// Remove any other empty values
		$actorConfiguration = array_filter($actorConfiguration);

		/** @noinspection PhpIncompatibleReturnTypeInspection */
		return Type::create($actorConfiguration);
	}

	public function getUserActivityPubParams(ActorTable $actorTable): Registry
	{
		$actorParams = new Registry($actorTable->params);

		if ($actorTable->user_id > 0)
		{
			/** @var DatabaseDriver $db */
			$db            = $this->getDatabase();
			$userId        = $actorTable->user_id;
			$query         = $db->getQuery(true)
				->select([
					$db->quoteName('profile_key'),
					$db->quoteName('profile_value'),
				])
				->from($db->quoteName('#__user_profiles'))
				->where(
					[
						$db->quoteName('profile_key') . ' LIKE ' . $db->quote('webfinger.activitypub_%'),
						$db->quoteName('user_id') . ' = :userId',
					]
				)
				->bind(':userId', $userId, ParameterType::INTEGER);
			$profileParams = $db->setQuery($query)->loadAssocList('profile_key', 'profile_value') ?: [];

			foreach ($profileParams as $k => $v)
			{
				if (!str_starts_with($k, 'webfinger.activitypub_'))
				{
					continue;
				}

				if (empty($v))
				{
					continue;
				}

				$actorParams->set('activitypub.' . substr($k, 22), $v);
			}
		}

		return $actorParams;
	}
}