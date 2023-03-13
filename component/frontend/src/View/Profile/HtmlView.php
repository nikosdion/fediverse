<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Site\View\Profile;

\defined('_JEXEC') || die;

use ActivityPhp\Type\Extended\AbstractActor;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\ActorTable;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\User\User;

class HtmlView extends BaseHtmlView
{
	/**
	 * The current actor
	 *
	 * @var   ActorTable|null
	 * @since 2.0.0
	 */
	protected ?ActorTable $actor;

	/**
	 * The actor's latest activities
	 *
	 * @var   array
	 * @since 2.0.0
	 */
	protected array $activities;

	/**
	 * The (real or fake) user record corresponding to the Actor
	 *
	 * @var   User|null
	 * @since 2.0.0
	 */
	protected ?User $user;

	/**
	 * The actor object
	 *
	 * @var   AbstractActor|null
	 * @since 2.0.0
	 */
	protected ?AbstractActor $actorObject;

	public function display($tpl = null)
	{
		/** @var ActorTable|null $actor */
		$this->actor       = $this->get('Actor');
		$this->activities  = $this->get('Activities');
		$this->user        = $this->get('User');
		$this->actorObject = $this->get('ActorObject');

		parent::display($tpl);
	}

}