<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Layout\LayoutHelper;

$displayData = [
	'textPrefix' => 'COM_ACTIVITYPUB_ACTORS',
	'formURL'    => 'index.php?option=com_activitypub&view=actors',
	'icon'       => 'fa fa-users',
];

$user = Factory::getApplication()->getIdentity();

if ($user->authorise('core.create', 'com_activitypub'))
{
	$displayData['createURL'] = 'index.php?option=com_activitypub&task=actor.add';
}

echo LayoutHelper::render('joomla.content.emptystate', $displayData);