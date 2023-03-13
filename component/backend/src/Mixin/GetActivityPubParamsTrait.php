<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\Mixin;

use Dionysopoulos\Component\ActivityPub\Administrator\Table\ActorTable;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

trait GetActivityPubParamsTrait
{
	/**
	 * Get the ActivityPub preferences for an actor
	 *
	 * @param   ActorTable  $actorTable  The actor
	 *
	 * @return  Registry
	 * @since   2.0.0
	 */
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