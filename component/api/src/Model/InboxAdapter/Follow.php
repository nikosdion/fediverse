<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Api\Model\InboxAdapter;

\defined('_JEXEC') || die;

use ActivityPhp\Type;
use ActivityPhp\Type\AbstractObject;
use ActivityPhp\Type\Extended\AbstractActor;
use ActivityPhp\Type\Extended\Activity\Follow as FollowActivity;
use ActivityPhp\Type\TypeConfiguration as Config;
use Dionysopoulos\Component\ActivityPub\Administrator\Mixin\GetActorTrait;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\ActorTable;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\FollowerTable;
use Dionysopoulos\Component\ActivityPub\Api\Model\AbstractPostHandlerAdapter;
use Dionysopoulos\Component\ActivityPub\Api\Model\ActorModel;
use Dionysopoulos\Component\ActivityPub\Api\Model\Mixin\IsBlockedFromFollowingTrait;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Http\Http;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\UserFactoryInterface;

/**
 * Handles the Follow activity POSTed to the inbox.
 *
 * @since  2.0.0
 */
class Follow extends AbstractPostHandlerAdapter
{
	use GetActorTrait;
	use IsBlockedFromFollowingTrait;

	/**
	 * Handles Follow activities POSTed to an Actor's Outbox
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
		if (!$activity instanceof FollowActivity)
		{
			return false;
		}

		// Make sure all required properties exist
		try
		{
			$followId      = $activity->id;
			$remoteActorId = $activity->actor;
			$localActorId  = $activity->object;
		}
		catch (Exception $e)
		{
			return false;
		}

		// Check that the local actor ID in the follow request matches the outbox
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
			return false;
		}

		// Get the remote actor and make sure the basic fields I need are defined.
		try
		{
			/**
			 * @var AbstractActor $remoteActor The AbstractActor instance of the remote actor
			 * @var string        $username    The remote actor's username
			 * @var string        $domain      The remote actor's domain name
			 * @var string        $inbox       The remote actor's inbox URL
			 */
			$remoteActor = $this->fetchActor($remoteActorId);
			$username    = $remoteActor->preferredUsername;
			$domain      = (new Uri($remoteActorId))->getHost();
			$inbox       = $remoteActor->inbox;
		}
		catch (Exception $e)
		{
			throw new \RuntimeException('The remote Actor cannot be found or has an invalid format', 415, $e);
		}

		// Try to get the optional sharedInbox field.
		try
		{
			$endpoints   = $remoteActor->endpoints ?? [];
			$sharedInbox = $endpoints['sharedInbox'] ?? null;
		}
		catch (\Throwable $e)
		{
			$sharedInbox = null;
		}

		// TODO Handle signature

		// Load a possibly existing record
		/** @var FollowerTable $follower */
		$follower = $this->getMVCFactory()->createTable('Follower', 'Administrator');
		$exists   = $follower->load([
			'actor_id'       => $actor->id,
			'follower_actor' => $remoteActorId,
		]);

		// Get the AbstractActor object for the local Actor
		/** @var ActorModel $actorModel */
		$actorModel = $this->getMVCFactory()->createModel('Actor', 'Api', ['ignore_request' => true]);
		$myActor    = $actorModel->getItem($username);
		$isBlocked  = $this->isBlockedFromFollowing($actor, $username, $domain);

		if ($isBlocked)
		{
			$this->sendRejectFollow($inbox, $activity, $myActor);

			// Delete an existing follower record
			if ($exists)
			{
				$follower->delete($follower->getId());
			}

			return true;
		}

		// Update the follower fields
		$follower->bind([
			'actor_id'       => $actor->id,
			'follower_actor' => $remoteActorId,
			'username'       => $username,
			'domain'         => $domain,
			'follow_id'      => $followId,
			'inbox'          => $inbox,
			'shared_inbox'   => $sharedInbox,
		]);

		if (!$exists)
		{
			$follower->created_on = Factory::getDate()->toSql();
		}

		// Send an Accept activity to the remote server
		if (!$this->sendAcceptFollow($inbox, $activity, $myActor))
		{
			throw new \RuntimeException('The remote server did not acknowledge the follow request acceptance', 415);
		}

		if (!$follower->store())
		{
			throw new \RuntimeException('Internal error processing the follow request', 500);
		}

		return true;
	}

	/**
	 * Retrieves the remove actor's information
	 *
	 * @param   string  $actorUrl  The URL of the actor to retrieve
	 *
	 * @return  AbstractActor
	 * @throws  Exception
	 * @since   2.0.0
	 */
	private function fetchActor(string $actorUrl): AbstractActor
	{
		$http     = $this->getHttpClient();
		$response = $http->get($actorUrl, [
			'Accept' => 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"',
		], 5);

		if ($response->code < 200 || $response->code > 299)
		{
			throw new \RuntimeException("Cannot retrieve remote actor", $response->code);
		}

		/** @noinspection PhpIncompatibleReturnTypeInspection */
		$json = $response->body;

		$temp = Config::get('undefined_properties');
		Config::set('undefined_properties', 'include');

		try
		{
			return Type::fromJson($json);
		}
		finally
		{
			Config::set('undefined_properties', $temp);
		}
	}

	/**
	 * Send a follow request acceptance message to the remote server.
	 *
	 * @param   string          $remoteInbox    The inbox URL of the remote actor.
	 * @param   FollowActivity  $followRequest  The follow request sent by the remote server.
	 * @param   AbstractActor   $myActor        The local actor which was requested to be followed.
	 *
	 * @return bool
	 * @throws Exception
	 * @since  2.0.0
	 */
	private function sendAcceptFollow(string $remoteInbox, FollowActivity $followRequest, AbstractActor $myActor): bool
	{
		$acceptActivity = Type::create('Accept', [
			'@context' => 'https://www.w3.org/ns/activitystreams',
			'actor'    => $myActor,
			'object'   => $followRequest,
		]);

		// TODO Handle signature

		$http     = $this->getHttpClient();
		$response = $http->post(
			$remoteInbox,
			$acceptActivity->toJson(),
			[
				'Accept' => 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"',
			],
			5
		);

		return $response->code >= 200 && $response->code <= 299;
	}

	/**
	 * Send a follow request rejection message to the remote server.
	 *
	 * @param   string          $remoteInbox    The inbox URL of the remote actor.
	 * @param   FollowActivity  $followRequest  The follow request sent by the remote server.
	 * @param   AbstractActor   $myActor        The local actor which was requested to be followed.
	 *
	 * @return  bool
	 * @throws  Exception
	 * @since   2.0.0
	 */
	private function sendRejectFollow(string $remoteInbox, FollowActivity $followRequest, AbstractActor $myActor): bool
	{
		$acceptActivity = Type::create('Reject', [
			'@context' => 'https://www.w3.org/ns/activitystreams',
			'actor'    => $myActor,
			'object'   => $followRequest,
		]);

		// TODO Handle signature

		$http     = $this->getHttpClient();
		$response = $http->post(
			$remoteInbox,
			$acceptActivity->toJson(),
			[
				'Accept' => 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"',
			],
			5
		);

		return $response->code >= 200 && $response->code <= 299;
	}

	/**
	 * Get the Joomla HTTP client
	 *
	 * @return Http
	 * @since  2.0.0
	 */
	private function getHttpClient(): Http
	{
		return HttpFactory::getHttp([
			'transport.curl' => [
				CURLOPT_SSL_VERIFYHOST   => 0,
				CURLOPT_SSL_VERIFYPEER   => 0,
				CURLOPT_SSL_VERIFYSTATUS => 0,
			],
		]);
	}
}