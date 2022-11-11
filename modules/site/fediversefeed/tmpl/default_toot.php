<?php
/*
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   GNU General Public License, version 3
 */

defined('_JEXEC') || die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\WebAsset\WebAssetManager;
use Joomla\Input\Input;
use Joomla\Module\FediverseFeed\Site\Dispatcher\Dispatcher;
use Joomla\Registry\Registry;

/**
 * These variables are extracted from the indexed array returned by the getLayoutData() method.
 *
 * @see \Joomla\Module\FediverseFeed\Site\Dispatcher\Dispatcher::getLayoutData()
 *
 * @var stdClass        $module          The module data loaded by Joomla
 * @var SiteApplication $app             The Joomla administrator application object
 * @var Input           $input           The application input object
 * @var Registry        $params          The module parameters
 * @var stdClass        $template        The current admin template
 * @var Dispatcher      $self            The Dispatcher object we are called for, used for helper methods
 * @var array           $toots           The toots in the timeline
 * @var stdClass        $account         The account metadata
 * @var string          $headerTag       HTML tag for the header text
 * @var string          $layoutsPath     Custom Joomla Layouts root path
 * @var WebAssetManager $webAssetManager Joomla's WebAssetManager
 *
 * These variables are inherited from default.php
 *
 * @var stdClass        $currentToot     The toot to render
 * @var stdClass        $reblog          Is this a reblogged toot?
 */

$uri            = $currentToot->url;
$contentWarning = $currentToot->spoiler_text ?: null;
$mediaFiles     = $currentToot->media_attachments ?? [];
$title          = HTMLHelper::_('date', $currentToot->created_at ?? 'now', Text::_('DATE_FORMAT_LC2'));

?>
<?php if (!empty($contentWarning)): ?>
<details class="mb-2">
	<summary>
		<div class="fediverse-cw-badge">
			<span class="fa fa-exclamation-triangle"
			      title="<?= Text::_('MOD_FEDIVERSEFEED_CONTENT_WARNING') ?> <?= htmlspecialchars($contentWarning, ENT_COMPAT, 'UTF-8') ?>"
			      aria-hidden="true"></span>
			<span class="fediverse-visually-hidden"><?= Text::_('MOD_FEDIVERSEFEED_CONTENT_WARNING') ?></span>
			<strong><?= strip_tags($contentWarning) ?></strong>
		</div>
	</summary>
	<?php endif; ?>

	<?php if ($reblog): ?>
	<blockquote lang="<?= $currentToot->language ?>">
		<?= $self->parseEmojis($currentToot->content, $currentToot->emojis) ?>
	</blockquote>
	<?php else: ?>
	<div lang="<?= $currentToot->language ?>">
		<?= $self->parseEmojis($currentToot->content, $currentToot->emojis) ?>
	</div>
	<?php endif; ?>

	<?php require ModuleHelper::getLayoutPath($module->module, 'default_media') ?>

	<?php require ModuleHelper::getLayoutPath($module->module, 'default_poll') ?>

	<?php if (!empty($contentWarning)): ?>
</details>
<?php endif; ?>
