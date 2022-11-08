<?php
/*
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   GNU General Public License, version 3
 */

defined('_JEXEC') || die;

/**
 * Default module layout
 *
 * Automatically loads the Bootstrap or Custom layout depending on your site's template.
 *
 * If your site's template uses the `bootstrap.css` dependency through Joomla's WebAssetManager, or you are using
 * Cassiopeia, or your template is a (direct) child template of Cassiopeia then the Bootstrap layout is loaded.
 *
 * Otherwise, the Custom layout is loaded.
 */

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Feed\Feed;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\WebAsset\WebAssetManager;
use Joomla\Input\Input;
use Joomla\Registry\Registry;

/**
 * These variables are extracted from the indexed array returned by the
 * \Joomla\Module\FediverseFeed\Site\Dispatcher\Dispatcher::getLayoutData() method.
 *
 * @see \Joomla\Module\FediverseFeed\Site\Dispatcher\Dispatcher::getLayoutData()
 * @var stdClass        $module                      The module data loaded by Joomla
 * @var SiteApplication $app                         The Joomla administrator application object
 * @var Input           $input                       The application input object
 * @var Registry        $params                      The module parameters
 * @var stdClass        $template                    The current admin template
 * @var Feed|null       $feed                        The Mastodon RSS feed
 * @var string|null     $feedUrl                     The URL to the Mastodon RSS feed
 * @var string          $profileUrl                  The URL to the Mastodon public profile
 * @var callable        $modFediverseFeedConvertText Helper method to convert the feed description
 * @var string          $headerTag                   HTML tag for the header text
 * @var string          $layoutsPath                 Custom Joomla Layouts root path
 * @var WebAssetManager $webAssetManager             Joomla's WebAssetManager
 */

if (empty($feed)):
	?>
	<div class="alert alert-warning">
		<?= Text::_('MOD_FEDIVERSEFEED_ERR_NO_FEED') ?>
	</div>
	<?php
	return;
endif;

$layout = 'custom';

$templateInfo = $app->getTemplate(true);

if (
	($webAssetManager->assetExists('bootstrap.css', 'style') && $webAssetManager->isAssetActive('bootstrap.css', 'style'))
	|| $templateInfo->template === 'cassiopeia'
	|| $templateInfo?->parent === 'cassiopeia'
)
{
	$layout = 'bootstrap';
}

require_once ModuleHelper::getLayoutPath($module->module, $layout);