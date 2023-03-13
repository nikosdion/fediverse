<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Api\Model;

\defined('_JEXEC') || die;

use Joomla\CMS\MVC\Factory\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryAwareTrait;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseInterface;

abstract class AbstractPostHandlerAdapter implements PostHandlerAdapterInterface, DatabaseAwareInterface
{
	use DatabaseAwareTrait;
	use MVCFactoryAwareTrait;

	/**
	 * Public constructor.
	 *
	 * @param   DatabaseInterface  $db          The Joomla database object
	 * @param   MVCFactory         $mvcFactory  The MVC Factory of the ActivityPub component
	 *
	 * @since   2.0.0
	 */
	public function __construct(DatabaseInterface $db, MVCFactory $mvcFactory)
	{
		$this->setDatabase($db);
		$this->setMVCFactory($mvcFactory);
	}
}