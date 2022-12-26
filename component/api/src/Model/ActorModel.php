<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Api\Model;

defined('_JEXEC') || die;

use ActivityPhp\Type;
use ActivityPhp\Type\Extended\AbstractActor;
use Dionysopoulos\Component\ActivityPub\Administrator\DataShape\KeyPair;
use Dionysopoulos\Component\ActivityPub\Administrator\Mixin\GetActorTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

class ActorModel extends BaseDatabaseModel
{
	use GetActorTrait;

	public function getItem(?string $username = null): ?AbstractActor
	{
		$username ??= $this->getState('actor.username', null);
		$user     = $this->getUserFromUsername($username);
		$actor    = $this->getActorRecordForUser($user, true);

		if ($user === null || $actor === null)
		{
			return null;
		}


		$type     = $user->params->get('activitypub.type', 'Person') ?: 'Person';
		$language = $user->params->get('language', Factory::getApplication()->getLanguage()->getTag() ?: 'en-GB');

		$signatureUri = new Uri($this->getApiUriForUser($user));
		$signatureUri->setFragment('main-key');

		$actorParams = new Registry($actor->params);
		$keyPair     = KeyPair::fromJson($actorParams->get('core.keyPair'));

		/**
		 * @var AbstractActor $actor
		 * @see https://www.w3.org/TR/activitypub/#actors
		 */
		$actor = Type::create([
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
				'publicKeyPem' => $keyPair->getPublicKey(),
			],
			// TODO summary
			// TODO icon
		]);

		return $actor;
	}


}