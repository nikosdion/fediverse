<?php
/*
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\Traits;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Uri\Uri;
use OutOfBoundsException;

/**
 * Trait to return the actor URL for a specific user.
 *
 * Requires the object to have the following properties:
 * - CMSApplication $application The Joomla application we are running under
 * - UserFactoryInterface $userFactory The Joomla User Factory
 *
 * @since  2.0.0
 */
trait ActorUrlTrait
{
	/**
	 * Get the ActivityPub actor URL for the user.
	 *
	 * Returns a URL similar to https://www.example.com/api/v1/activitypub/actor/myuser
	 *
	 * @param   int  $userId  The user ID to get the actor URL for
	 *
	 * @return  string
	 * @since   2.0.0
	 */
	protected function getActorUrl(int $userId): string
	{
		$user = $this->userFactory->loadUserById($userId);

		if ($user->id != $userId)
		{
			throw new OutOfBoundsException(
				sprintf('User ID %u not found.', $userId)
			);
		}

		$path = '/v1/activitypub/actor/' . ApplicationHelper::stringURLSafe($user->username);

		if (!$this->application->get('sef', false))
		{
			$path = '/index.php' . $path;
		}

		$url = rtrim(Uri::base(), '/');

		if ($this->application->isClient('administrator'))
		{
			$url = substr($url, 0, -13);
		}
		elseif ($this->application->isClient('api'))
		{
			$url = substr($url, 0, -3);
		}

		return rtrim($url . '/') . $path;
	}

}