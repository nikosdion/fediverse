<?php
/*
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
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
 */

$hasTitle       = $params->get('feed_title', 1) == 1;
$isLinked       = $params->get('feed_link', 1) == 1;
$hasDate        = $params->get('feed_date', 1) == 1;
$hasDescription = $params->get('feed_desc', 1) == 1;
$hasImage       = $account->avatar && $params->get('feed_image', 1) == 1;
$direction      = $params->get('feed_rtl', 0) == 1 ? 'rtl' : 'ltr';
$hasDate        = !empty($feed);
$lastDate       = $hasDate ? reset($toots)->created_at : 'now';
$webAssetManager->usePreset('mod_fediversefeed.custom');

?>
<div class="fediverse-feed fediverse-feed-<?= $direction ?>">
	<?php if ($hasTitle || $hasDate || $hasDescription || $hasImage): ?>
	<div class="fediverse-feed-header">
		<?php if ($hasTitle || $hasImage): ?>
		<<?= $headerTag ?> class="fediverse-feed-header-top">
			<?php if ($isLinked): ?>
			<a href="<?= htmlspecialchars($account->url, ENT_COMPAT, 'UTF-8') ?>"
			   target="_blank" rel="noopener"
			   class="fediverse-feed-header-top-container">
			<?php else: ?>
			<span class="fediverse-feed-header-top-container">
			<?php endif; ?>
				<?php if ($hasImage): ?>
				<span class="fediverse-feed-header-top-avatar-wrapper">
					<img src="<?= $account->avatar ?>"
						 alt="<?= htmlentities($self->parseEmojis($account->display_name, $account->emojis), ENT_COMPAT, 'UTF-8') ?>"
						 class="fediverse-feed-header-top-avatar">
				</span>
				<?php endif ?>
				<?php if ($hasTitle): ?>
					<span class="fediverse-feed-header-top-title">
					<?= $self->parseEmojis($account->display_name, $account->emojis) ?>
				</span>
				<?php endif ?>
			<?php if ($isLinked): ?>
			</a>
			<?php else: ?>
			</span>
			<?php endif; ?>
		</<?= $headerTag ?>>
	<?php endif ?>
	<?php if ($hasDescription): ?>
		<p class="fediverse-feed-header-description">
			<?= $self->parseEmojis($account->note, $account->emojis ?? []) ?>
		</p>
	<?php endif ?>
	<?php if ($hasDate): ?>
		<p class="fediverse-feed-header-date">
			<span class="fa fa-calendar" aria-hidden="true"></span>
			<span class="visually-hidden"><?= Text::_('MOD_FEDIVERSEFEED_LAST_UPDATED_ON') ?></span>
			<?= HTMLHelper::_('date', $lastDate, Text::_('DATE_FORMAT_LC5')) ?>
		</p>
	<?php endif ?>
</div>
<?php endif ?>

<ul class="fediverse-toots">
	<?php foreach ($toots as $toot): ?>
	<?php
		$currentToot = $toot;
		$reblog      = false;

		if ($toot->reblog ?? null)
		{
			$reblog      = true;
			$currentToot = $toot->reblog;
		}
		?>
	<li class="fediverse-toot fediverse-toot-<?= $self->langToWritingSystemClass($currentToot->language) ?>">
		<?php if ($reblog): ?>
		<div class="fediverse-reblog-info">
			<span class="fa fa-retweet"
				  aria-hidden="true"></span>
			<span class="fediverse-visually-hidden">
				<?= Text::sprintf(
					'MOD_FEDIVERSEFEED_REBLOGGED',
					$self->parseEmojis($currentToot->account->display_name, $currentToot->account->emojis)
				) ?>
			</span>
			<a href="<?= $currentToot->account->url ?>">
				@<?= $currentToot->account->acct ?>
			</a>
		</div>
		<div class="fediverse-reblog-content">
		<?php endif; ?>

		<?php require ModuleHelper::getLayoutPath($module->module, 'default_toot') ?>

		<?php if ($reblog): ?>
		</div>
		<?php endif; ?>

		<?php
			$title = HTMLHelper::_('date', $toot->created_at ?? 'now', Text::_('DATE_FORMAT_LC2'));
		?>

		<div class="fediverse-toot-permalink fediverse-toot-permalink-<?= $direction ?>">
			<span class="fa fa-clock"
				  title="<?= Text::_('MOD_FEDIVERSEFEED_TOOTED_ON') ?>"
				  aria-hidden="true"></span>
			<span class="fediverse-visually-hidden"><?= Text::_('MOD_FEDIVERSEFEED_TOOTED_ON') ?></span>
			<?php if (!empty($uri)) : ?>
				<span class="fediverse-toot-link">
					<a href="<?= htmlspecialchars($uri, ENT_COMPAT, 'UTF-8') ?>"
					   target="_blank" rel="noopener">
						<?= trim($title) ?>
					</a>
				</span>
			<?php else : ?>
			<span class="fediverse-toot-link"><?= trim($title) ?></span>
			<?php endif; ?>
		</div>
	</li>
	<?php endforeach; ?>
</ul>
</div>