<?php
/*
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   GNU General Public License, version 3
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\Extension;

use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\Component;
use Joomla\CMS\MVC\Factory\MVCFactoryAwareTrait;
use Psr\Container\ContainerInterface;

class ActivityPubComponent extends Component implements BootableExtensionInterface
{
	use MVCFactoryAwareTrait;

	public function boot(ContainerInterface $container)
	{
		// TODO: Implement boot() method.
	}
}