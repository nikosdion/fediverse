<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2024 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\Table\Mixin;

use Joomla\CMS\Language\Text;
use Joomla\Database\ParameterType;

trait AssertActorExistsTrait
{
	/**
	 * Make sure that the $actorId is non-empty and corresponds to an existing actor
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	private function assertActorExists(?int $actorId): void
	{
		$hasActor = $actorId !== null && $actorId > 0;

		if ($hasActor)
		{
			$db       = $this->getDbo();
			$query    = $db->getQuery(true)
				->select('COUNT(*)')
				->from($db->quoteName('#__activitypub_actors'))
				->where($db->quoteName('id') . ' = :id')
				->bind(':id', $actorId, ParameterType::INTEGER);
			$hasActor = ($db->setQuery($query)->loadResult() ?: 0) > 0;
		}

		if ($hasActor)
		{
			return;
		}

		throw new \RuntimeException(Text::_('COM_ACTIVITYPUB_FOLLOWERS_ERR_INVALID_ACTOR'));
	}
}