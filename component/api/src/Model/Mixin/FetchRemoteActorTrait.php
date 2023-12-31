<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2024 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Api\Model\Mixin;

\defined('_JEXEC') || die;

use ActivityPhp\Type;
use ActivityPhp\Type\Extended\AbstractActor;
use ActivityPhp\Type\TypeConfiguration as Config;
use Exception;
use Joomla\CMS\Http\Http;
use Joomla\CMS\Http\HttpFactory;

/**
 * A trait for fetching the remote actor.
 *
 * @since 2.0.0
 */
trait FetchRemoteActorTrait
{
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

		$json = $response->body;

		$temp = Config::get('undefined_properties');
		Config::set('undefined_properties', 'include');

		try
		{
			/** @noinspection PhpIncompatibleReturnTypeInspection */
			return Type::fromJson($json);
		}
		finally
		{
			Config::set('undefined_properties', $temp);
		}
	}
}