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
use Dionysopoulos\Component\ActivityPub\Administrator\Mixin\GetActivityPubParamsTrait;
use Dionysopoulos\Component\ActivityPub\Administrator\Mixin\GetActorTrait;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\ActorTable;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

class ActorModel extends BaseDatabaseModel
{
	use GetActorTrait;
	use GetActivityPubParamsTrait;

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

		$profileLink = Route::link(
			'site',
			'index.php?option=com_activitypub&view=profile&id=' . $actorTable->id,
			false,
			Route::TLS_FORCE,
			true
		);

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
			'published'         => Factory::getDate($actorTable->created ?? 'now')->format(DATE_ATOM),
			'summary'           => $actorParams->get('activitypub.summary', '') ?? '',
			'url'               => $profileLink,
			'icon'              => [
				'type'      => 'Image',
				'mediaType' => $mediaType,
				'url'       => $profileIcon,
			],
			// TODO: Profile header image, same format as 'icon'
			// 'image' => null,
			// TODO: Profile fields, see https://docs.joinmastodon.org/spec/activitypub/#PropertyValue
			'attachment'        => [],
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
}