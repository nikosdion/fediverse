<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

/**
 * @package     Dionysopoulos\Component\ActivityPub\Administrator\Service
 * @subpackage
 *
 * @copyright   A copyright
 * @license     A "Slug" license name e.g. GPL2
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\Service;

\defined('_JEXEC') || die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Component\Router\RouterFactoryInterface;
use Joomla\CMS\Component\Router\RouterInterface;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseInterface;

class RouterFactory implements RouterFactoryInterface, DatabaseAwareInterface
{
	use DatabaseAwareTrait;

	/**
	 * Constructor.
	 *
	 * @param   string               $namespace  The component's namespace
	 * @param   DatabaseInterface    $db         The database driver object
	 * @param   MVCFactoryInterface  $factory    The MVC Factory object
	 *
	 * @since   2.0.0
	 */
	public function __construct(private string $namespace, DatabaseInterface $db, public MVCFactoryInterface $factory)
	{
		$this->setDatabase($db);
	}


	/**
	 * Creates a router.
	 *
	 * @param   CMSApplicationInterface  $application  The application
	 * @param   AbstractMenu             $menu         The menu object to work with
	 *
	 * @return  RouterInterface
	 *
	 * @since   2.0.0
	 */
	public function createRouter(CMSApplicationInterface $application, AbstractMenu $menu): RouterInterface
	{
		$className = trim($this->namespace, '\\') . '\\' . ucfirst($application->getName()) . '\\Service\\Router';

		if (!class_exists($className))
		{
			throw new \RuntimeException('No router available for this application.');
		}

		return new $className($application, $menu, $this->getDatabase(), $this->factory);
	}
}