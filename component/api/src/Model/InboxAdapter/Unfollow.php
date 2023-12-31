<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2024 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Api\Model\InboxAdapter;

\defined('_JEXEC') || die;

use ActivityPhp\Type\AbstractObject;
use ActivityPhp\Type\Extended\AbstractActor;
use ActivityPhp\Type\Extended\Activity\Follow as FollowActivity;
use ActivityPhp\Type\Extended\Activity\Undo as UndoActivity;
use Dionysopoulos\Component\ActivityPub\Administrator\Mixin\GetActorTrait;
use Dionysopoulos\Component\ActivityPub\Administrator\Service\Signature;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\ActorTable;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\FollowerTable;
use Dionysopoulos\Component\ActivityPub\Api\Model\AbstractPostHandlerAdapter;
use Dionysopoulos\Component\ActivityPub\Api\Model\Mixin\FetchRemoteActorTrait;
use Dionysopoulos\Component\ActivityPub\Api\Model\Mixin\HttpClientTrait;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\UserFactoryInterface;

/**
 * Handles unfollow requests (Undo Follow) POSTed to the inbox
 *
 * @since 2.0.0
 */
class Unfollow extends AbstractPostHandlerAdapter
{
	use GetActorTrait;
	use HttpClientTrait;
	use FetchRemoteActorTrait;

	/**
	 * Handles Undo Follow activities POSTed to an Actor's Outbox
	 *
	 * @param   AbstractObject  $activity  The received Activity
	 * @param   ActorTable      $actor     The ActorTable object of the reveiving Actor
	 *
	 * @return  bool
	 * @throws  Exception
	 * @since   2.0.0
	 */
	public function handle(AbstractObject $activity, ActorTable $actor): bool
	{
		if (!$activity instanceof UndoActivity)
		{
			return false;
		}

		$object = $activity->object;

		if (!$object instanceof FollowActivity)
		{
			return false;
		}

		try
		{
			$followId      = $object->id;
			$remoteActorId = $object->actor;
			$localActorId  = $object->object;
		}
		catch (Exception $e)
		{
			Log::add('Malformed unfollow request: missing required properties', Log::ERROR, 'activitypub.api');

			return false;
		}

		// Check that the local actor ID in the follow request matches the inbox
		$user         = $actor->user_id > 0
			? Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($actor->user_id)
			: $this->getUserFromUsername($actor->username);
		$referenceUri = new Uri($this->getApiUriForUser($user, 'actor'));
		$referenceUri->setScheme('https');
		$referenceUri->setPort('443');
		$referenceActorId = $referenceUri->toString();

		$localUri = new Uri($localActorId);
		$localUri->setScheme('https');
		$localUri->setPort('443');

		if ($localUri->toString() !== $referenceActorId)
		{
			Log::add(sprintf('Unknown Actor %s', $localActorId), Log::ERROR, 'activitypub.api');

			return false;
		}

		// Get the remote actor and make sure the basic fields I need are defined.
		try
		{
			/**
			 * @var AbstractActor $remoteActor The AbstractActor instance of the remote actor
			 */
			$remoteActor = $this->fetchActor($remoteActorId);
		}
		catch (Exception $e)
		{
			Log::add(sprintf('Cannot fetch remote Actor %s', $remoteActorId), Log::ERROR, 'activitypub.api');

			throw new \RuntimeException('The remote Actor cannot be found or has an invalid format', 415, $e);
		}

		// Handle signature
		$signatureService = new Signature(
			$this->getDatabase(),
			Factory::getContainer()->get(UserFactoryInterface::class),
			Factory::getApplication()
		);

		if (!$signatureService->verify($remoteActor))
		{
			Log::add('Signature verification failed', Log::ERROR, 'activitypub.api');

			throw new \RuntimeException('Bad signature', 401);
		}

		// TODO We also need to validate the Date (+/- 30 seconds)

		// Load a possibly existing record
		/** @var FollowerTable $follower */
		$follower = $this->getMVCFactory()->createTable('Follower', 'Administrator');
		$exists = $follower->load([
			'actor_id'       => $actor->id,
			'follower_actor' => $remoteActorId,
			'follow_id'      => $followId,
		]);

		if (!$exists)
		{
			Log::add(
				sprintf(
					'Local actor %s does not have a follower with ID %s',
					$localActorId,
					$followId
				), Log::WARNING, 'activitypub.api'
			);

			// Pretend we handled this anyway.
			return true;
		}

		if (!$follower->delete())
		{
			Log::add('Could not remove the follower information from the database.', Log::ERROR, 'activitypub.api');

			throw new \RuntimeException('Internal error processing the unfollow request', 500);
		}

		Log::add('The Unfollow request was accepted.', Log::DEBUG, 'activitypub.api');

		return true;
	}
}