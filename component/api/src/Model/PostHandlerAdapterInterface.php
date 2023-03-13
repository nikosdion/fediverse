<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Api\Model;

\defined('_JEXEC') || die;

use ActivityPhp\Type\AbstractObject;
use Dionysopoulos\Component\ActivityPub\Administrator\Table\ActorTable;
use Joomla\CMS\MVC\Factory\MVCFactory;
use Joomla\Database\DatabaseInterface;

interface PostHandlerAdapterInterface
{
	/**
	 * Public constructor.
	 *
	 * @param   DatabaseInterface  $db          The Joomla database object
	 * @param   MVCFactory         $mvcFactory  The MVC Factory of the ActivityPub component
	 *
	 * @since   2.0.0
	 */
	public function __construct(DatabaseInterface $db, MVCFactory $mvcFactory);

	/**
	 * Handle a POST request to an actor's Outbox.
	 *
	 * @param   AbstractObject  $activity  The received Activity
	 * @param   ActorTable      $actor     The ActorTable object of the reveiving Actor
	 *
	 * @return  bool
	 * @since   2.0.0
	 */
	public function handle(AbstractObject $activity, ActorTable $actor): bool;
}