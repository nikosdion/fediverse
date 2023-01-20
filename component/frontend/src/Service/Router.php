<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

/**
 * @package     Dionysopoulos\Component\ActivityPub\Site\Service
 * @subpackage
 *
 * @copyright   A copyright
 * @license     A "Slug" license name e.g. GPL2
 */

namespace Dionysopoulos\Component\ActivityPub\Site\Service;

defined('_JEXEC') || die;

use Dionysopoulos\Component\ActivityPub\Administrator\Mixin\GetActorTrait;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\ActorTable;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Factory;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\CMS\MVC\Factory\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryAwareTrait;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseInterface;

/**
 * Frontend URL Router
 *
 * @since       2.0.0
 */
class Router extends RouterView
{
	use MVCFactoryAwareTrait;
	use DatabaseAwareTrait;
	use GetActorTrait;

	public function __construct(SiteApplication $app = null, AbstractMenu $menu = null, DatabaseInterface $db, MVCFactory $factory)
	{
		$this->setDatabase($db);
		$this->setMVCFactory($factory);

		$profiles = new RouterViewConfiguration('profiles');
		$this->registerView($profiles);

		$profile = (new RouterViewConfiguration('profile'))
			->setKey('id');
		$this->registerView($profile);

		parent::__construct($app, $menu);

		$this->attachRule(new MenuRules($this));
		$this->attachRule(new StandardRules($this));
		$this->attachRule(new NomenuRules($this));
	}

	/**
	 * Return the segment (username) for a profile given its (actor) ID.
	 *
	 * @param   string  $id     The actor ID
	 * @param   array   $query  The other query parameters already determined
	 *
	 * @return  array
	 *
	 * @throws  \Exception
	 * @since   2.0.0
	 */
	public function getProfileSegment(string $id, array $query): array
	{
		if (str_contains($id, ':'))
		{
			[$id,] = explode(':', $id, 2);
		}

		/** @var ActorTable $actorTable */
		$actorTable = $this->getMVCFactory()->createTable('Actor', 'Administrator');
		$id         = (int) $id;
		$loaded     = $actorTable->load([
			'id' => $id,
		]);

		if (!$loaded)
		{
			return [(int) $id => sprintf('%d:', $id)];
		}

		if ($actorTable->user_id)
		{
			$user     = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($actorTable->user_id);
			$username = $user->username;
		}
		else
		{
			$username = $actorTable->username;
		}

		return [$id => $username];
	}

	/**
	 * Returns the (actor) ID given the profile segment (username)
	 *
	 * @param   string  $segment  The segment (username)
	 * @param   array   $query    The other query parameters already determined
	 *
	 * @return  int|false  The actor ID, false if it cannot be determined
	 *
	 * @since   2.0.0
	 */
	public function getProfileId(string $segment, array $query): int|false
	{
		if (str_contains($segment, ':'))
		{
			[$id,] = explode(':', $segment, 2);

			return (int) $id;
		}

		$user = $this->getUserFromUsername($segment);

		if (empty($user))
		{
			return false;
		}

		$actorTable = $this->getActorRecordForUser($user);

		if (empty($actorTable))
		{
			return false;
		}

		return $actorTable->id;
	}
}