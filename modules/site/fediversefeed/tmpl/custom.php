<?php
/*
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   GNU General Public License, version 3
 */

defined('_JEXEC') || die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Feed\Feed;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
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

$hasTitle       = $feed->title !== null && $params->get('feed_title', 1) == 1;
$isLinked       = $params->get('feed_link', 1) == 1;
$hasDate        = $params->get('feed_date', 1) == 1;
$hasDescription = $params->get('feed_desc', 1) == 1;
$useTitle       = $params->get('feed_item_use_title', 0) == 1;
$hasImage       = $feed->image && $params->get('feed_image', 1) == 1;
$rtlFeed        = $params->get('feed_rtl', 0);
$direction      = $rtlFeed == 1 ? 'rtl' : 'ltr';
$maxItems       = min(count($feed), $params->get('feed_items', 5));

if ($hasDate)
{
	$dateSource = $feed->publishedDate ?? $feed->updatedDate->toISO8601() ?? null;
	$hasDate    = $hasDate !== null;
}

$webAssetManager->usePreset('mod_fediversefeed.custom');

?>
<div style="direction: <?= $direction ?>" class="fediverse-feed fediverse-feed-<?= $direction ?>">
	<?php if ($hasTitle || $hasDate || $hasDescription || $hasImage): ?>
	<div class="fediverse-feed-header">
		<?php if ($hasTitle || $hasImage): ?>
		<<?= $headerTag ?> class="fediverse-feed-header-top">
		<?php if ($isLinked): ?>
			<a href="<?= htmlspecialchars($profileUrl, ENT_COMPAT, 'UTF-8') ?>"
			   target="_blank" rel="noopener"
			   class="fediverse-feed-header-top-container">
			<?php else: ?>
			<span class="fediverse-feed-header-top-container">
			<?php endif; ?>
				<?php if ($hasImage): ?>
				<span class="fediverse-feed-header-top-avatar-wrapper">
					<img src="<?= $feed->image->uri ?>"
						 alt="<?= $feed->image->title ?>"
						 class="fediverse-feed-header-top-avatar">
				</span>
				<?php endif ?>
				<?php if ($hasTitle): ?>
					<span class="fediverse-feed-header-top-title">
					<?= $feed->title ?>
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
			<?= $feed->description ?>
		</p>
	<?php endif ?>
	<?php if ($hasDate): ?>
		<p class="fediverse-feed-header-date">
			<span class="fa fa-calendar" aria-hidden="true"></span>
			<span class="visually-hidden"><?= Text::_('MOD_FEDIVERSEFEED_LAST_UPDATED_ON') ?></span>
			<?= HTMLHelper::_('date', $dateSource, Text::_('DATE_FORMAT_LC5')) ?>
		</p>
	<?php endif ?>
</div>
<?php endif ?>

<ul class="fediverse-toots">
	<?php for ($i = 0; $i < $maxItems; $i++): ?>
		<?php
		$uri = $feed[$i]->uri || !$feed[$i]->isPermaLink
			? trim($feed[$i]->uri)
			: trim($feed[$i]->guid);
		$uri = !$uri || stripos($uri, 'http') !== 0 ? $feedUrl : $uri;
		[$contentWarning, $text] = $modFediverseFeedConvertText(trim($feed[$i]->content ?? ''));
		$mediaFiles = $feed[$i]?->media?->content ?? [];
		$title      = ($useTitle ? $feed[$i]?->title : null)
			?: HTMLHelper::_('date', $feed[$i]?->publishedDate ?? 'now', Text::_('DATE_FORMAT_LC2'));
		?>
		<li class="fediverse-toot">
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

				<?= $text ?>

				<?php if ($params->get('feed_media', 1) == 1): ?>
				<?= LayoutHelper::render('fediverse.media', [
					'mediaFiles'       => $mediaFiles,
					'feedItem'         => $feed[$i],
					'inContentWarning' => !empty($contentWarning),
					'layoutsPath'      => $layoutsPath,
					'webAssetManager'  => $webAssetManager,
				], $layoutsPath) ?>
				<?php endif ?>

				<?php if (!empty($contentWarning)): ?>
			</details>
			<?php endif; ?>

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
	<?php endfor; ?>
	</ul>
</div>