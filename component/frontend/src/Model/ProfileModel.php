<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Site\Model;

\defined('_JEXEC') || die;

use ActivityPhp\Type\Extended\AbstractActor;
use Dionysopoulos\Component\ActivityPub\Administrator\Mixin\GetActivityPubParamsTrait;
use Dionysopoulos\Component\ActivityPub\Administrator\Mixin\GetActorTrait;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\ActorTable;
use Dionysopoulos\Component\ActivityPub\Api\Model\ActorModel;
use Dionysopoulos\Component\ActivityPub\Api\Model\OutboxModel;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;

class ProfileModel extends BaseDatabaseModel
{
	use GetActorTrait;

	private ?ActorTable $actor;

	private ?User $user = null;

	public function getActor(): ?ActorTable
	{
		$id         = (int) $this->getState('id');
		$username   = $this->getState('username');
		$this->user = null;

		if (empty($username))
		{
			/** @noinspection PhpFieldAssignmentTypeMismatchInspection */
			$this->actor = $this->getTable('Actor', 'Administrator');

			if (!$this->actor->load($id))
			{
				$this->actor = null;
			}

			$username = $this->actor->username
				?: Factory::getContainer()->get(UserFactoryInterface::class)->loadUserByUsername($this->actor->user_id)->username;

			$this->user = $this->getUserFromUsername($username);

			return $this->actor;
		}

		$this->user  = $this->getUserFromUsername($username);
		$this->actor = $this->getActorRecordForUser($this->user);

		return $this->actor;
	}

	public function getActivities(): array
	{
		$this->actor ??= $this->getActor();
		$username    = $this->getState('username') ?: $this->user->username;

		/** @var OutboxModel $model */
		$model = $this->getMVCFactory()->createModel('Outbox', 'Api');
		$model->setState('filter.username', $username);

		return $model->getItems();
	}

	public function getUser(): ?User
	{
		if (empty($this->user))
		{
			$this->getActor();
		}

		return $this->user;
	}

	public function getActorObject(): ?AbstractActor
	{
		$user = $this->user ?? $this->getUser();

		/** @var ActorModel $model */
		$model = $this->getMVCFactory()->createModel('Actor', 'Api', ['ignore_request' => true]);

		return $model->getItem($user->username);
	}
}