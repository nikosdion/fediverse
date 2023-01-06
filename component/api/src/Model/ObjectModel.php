<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Api\Model;

\defined('_JEXEC') || die;

use ActivityPhp\Type\AbstractObject;
use ActivityPhp\Type\Extended\Activity\Create;
use Dionysopoulos\Component\ActivityPub\Administrator\Event\GetActivity;
use Dionysopoulos\Component\ActivityPub\Administrator\Event\GetObject;
use Dionysopoulos\Component\ActivityPub\Administrator\Mixin\GetActorTrait;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\ActorTable;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\Exception\ResourceNotFound;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Plugin\PluginHelper;

class ObjectModel extends BaseDatabaseModel
{
	use GetActorTrait;

	/**
	 * Get an activity's object
	 *
	 * @param   string|null  $username  The Actor's username
	 * @param   string|null  $context   The context, e.g. com_example.item
	 * @param   string|null  $id        The ID of the object, in this context and for this Actor
	 *
	 * @return  AbstractObject
	 * @throws  Exception
	 * @since   2.0.0
	 */
	public function getItem(?string $username = null, ?string $context = null, ?string $id = null): AbstractObject
	{
		$username   ??= $this->getUsername();
		$context    ??= $this->getContext();
		$id         ??= $this->getId();
		$actorTable = $this->getActorTable($username);

		PluginHelper::importPlugin('activitypub');
		PluginHelper::importPlugin('content');

		$dispatcher = Factory::getApplication()->getDispatcher();

		$event = new GetObject($actorTable, $context, $id);
		$dispatcher->dispatch($event->getName(), $event);
		$objects = $event->getArgument('result');
		$objects = is_array($objects) ? $objects : [];

		if (empty($objects))
		{
			throw new ResourceNotFound(Text::_('COM_ACTIVITYPUB_OBJECT_ERR_NOT_FOUND'), 404);
		}

		return array_shift($objects);
	}

	/**
	 * Get the Actor username, or throw a 404
	 *
	 * @return  string
	 * @since   2.0.0
	 */
	private function getUsername(): string
	{
		$username = trim($this->getState('object.username') ?? '');

		if (empty($username))
		{
			throw new ResourceNotFound(Text::_('COM_ACTIVITYPUB_OBJECT_ERR_NOT_FOUND'), 404);
		}

		return $username;
	}

	/**
	 * Get the object's context, or throw a 404
	 *
	 * @return  string
	 * @since   2.0.0
	 */
	private function getContext(): string
	{
		$context = trim($this->getState('object.context') ?? '');

		if (empty($context))
		{
			throw new ResourceNotFound(Text::_('COM_ACTIVITYPUB_OBJECT_ERR_NOT_FOUND'), 404);
		}

		return $context;
	}

	/**
	 * Get the object ID, or throw a 404
	 *
	 * @return  string
	 * @since   2.0.0
	 */
	private function getId(): string
	{
		$id = trim($this->getState('object.id') ?? '');

		if (empty($id))
		{
			throw new ResourceNotFound(Text::_('COM_ACTIVITYPUB_OBJECT_ERR_NOT_FOUND'), 404);
		}

		return $id;
	}

	/**
	 * Get the ActorTable corresponding to a username
	 *
	 * @param   string  $username  The username to look up.
	 *
	 * @return  ActorTable
	 * @throws  Exception  If there is no such user, or there is an error.
	 * @since   2.0.0
	 */
	private function getActorTable(string $username): ActorTable
	{
		/** @var ActorModel $actorModel */
		$actorModel = $this->getMVCFactory()
			->createModel('Actor', 'Api', ['ignore_request' => true]);
		$user       = $this->getUserFromUsername($username);
		$actorTable = $actorModel->getActorRecordForUser($user, false);

		if ($actorTable === null)
		{
			throw new ResourceNotFound(Text::_('COM_ACTIVITYPUB_OBJECT_ERR_NOT_FOUND'), 404);
		}

		return $actorTable;
	}

}