<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Api\Model\Mixin;

\defined('_JEXEC') || die;

use Dionysopoulos\Component\ActivityPub\Administrator\Table\ActorTable;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\BlockTable;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\FediblockTable;
use Joomla\Registry\Registry;

trait IsBlockedFromFollowingTrait
{
	/**
	 * Is the remote Actor blocked from following the local Actor?
	 *
	 * The conditions checked are:
	 * * Has the local actor specified they don't want to be followed by anyone?
	 * * Has the local actor blocked this particular remote actor?
	 * * Does the server block the entire domain of the remote actor?
	 *
	 * @param   ActorTable  $actor     The local actor
	 * @param   string      $username  The remote actor's username
	 * @param   string      $domain    The remote actor's domain (host name)
	 *
	 * @return  bool
	 * @throws  \Exception
	 * @since   2.0.0
	 */
	private function isBlockedFromFollowing(ActorTable $actor, string $username, string $domain): bool
	{
		// Does the local actor indicate they don't want to be followed?
		$actorParams = $actor->params instanceof Registry ? $actor->params : new Registry($actor->params);

		if ($actorParams->get('core.allowFollow', 1) == 0)
		{
			return false;
		}

		// Has the local actor blocked this specific remote actor?
		/** @var BlockTable $blockTable */
		$blockTable = $this->getMVCFactory()->createTable('Block', 'Administrator');
		if ($blockTable->load([
			'actor_id' => $actor->id,
			'username' => $username,
			'domain'   => $domain,
		]))
		{
			return true;
		}

		// Has the site administrator blocked the entire remote server ("fediblock")?
		/** @var FediblockTable $fediblockTable */
		$fediblockTable = $this->getMVCFactory()->createTable('Fediblock', 'Administrator');
		if ($fediblockTable->load([
			'domain' => $domain,
		]))
		{
			return true;
		}

		return false;
	}
}