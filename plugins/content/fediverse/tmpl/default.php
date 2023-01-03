<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

defined('_JEXEC') || die;

use Dionysopoulos\Plugin\Content\Fediverse\Extension\Fediverse;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\WebAsset\WebAssetManager;

/**
 * The variables are passed from the content plugin
 *
 * @var  object          $toot            The raw toot information. See https://docs.joinmastodon.org/entities/status/
 * @var  string          $tootUrl         The URL of the original toot
 * @var  array           $embedParams     Embedding parameters, provided by the user
 * @var  Fediverse       $this            The plugin which loaded us
 * @var  callable        $serverFromUrl   Get the Mastodon server domain from a user's URL
 * @var  WebAssetManager $webAssetManager The Joomla Web Asset Manager object
 */

$webAssetManager->usePreset('plg_content_fediverse.embedded');

/**
 * @var object $currentToot The current toot to render
 * @see https://docs.joinmastodon.org/entities/status/
 */
$currentToot    = $currentToot ?? $toot;

if (empty($currentToot))
{
	require PluginHelper::getLayoutPath($this->_type, $this->_name, 'default_unavailable');

	return;
}

$parentToot       = $currentToot !== $toot;
$sensitive        = $currentToot->sensitive && !empty($currentToot->spoiler_text);
$sensitiveMedia   = $currentToot->sensitive && empty($currentToot->spoiler_text);
$writingDirection = $this->langToWritingSystemClass($currentToot?->language ?? 'en');

?>
<aside class="toot-embed<?= $parentToot ? '-parent' : '' ?> toot-embed-writing-system-<?= $writingDirection ?>"
	   <?php if (!$parentToot): ?>
	   lang="<?= $currentToot?->language ?? 'en' ?>"
	   <?php endif; ?>
	   data-url="<?= htmlentities($tootUrl, ENT_COMPAT, 'UTF-8') ?>">
	<?php
	// Display parent toot, if present
	if (!$parentToot && !empty($currentToot?->_parent) && !in_array('noreply', $embedParams))
	{
		$cacheSensitive = $sensitive;
		$cacheSensitiveMedia = $sensitiveMedia;
		$cacheCurrentToot = $currentToot;
		$cacheWritingDirection = $writingDirection;
		$currentToot      = $currentToot->_parent;
		require PluginHelper::getLayoutPath($this->_type, $this->_name);
		$currentToot = $cacheCurrentToot;
		$parentToot = false;
		$sensitive = $cacheSensitive;
		$sensitiveMedia = $cacheSensitiveMedia;
		$writingDirection = $cacheWritingDirection;
	}
	?>
	<header class="toot-embed-header">
		<div class="toot-embed-header-avatar">
			<img src="<?= $currentToot->account->avatar ?>"
				 aria-hidden="true"
				 class="toot-embed-header-avatar-image">
		</div>
		<div class="toot-embed-header-info">
			<span class="toot-embed-header-displayname"><?= $this->parseEmojis($currentToot->account->display_name, $currentToot->account->emojis) ?></span>
			<span class="toot-embed-header-username">
				<a href="<?= $currentToot->account->url ?>" rel="nofollow">
					@<span class="toot-embed-header-username-localname-<?= $writingDirection ?>"><?= $currentToot->account->username ?></span>@<span class="toot-embed-header-username-servername-<?= $writingDirection ?>"><?= $serverFromUrl($currentToot->account->url) ?></span>
				</a>
			</span>
		</div>
		<?php if($parentToot): ?>
		<div class="toot-embed-header-ago">
			<a href="<?= $currentToot->url ?>"
			   rel="nofollow">
				<span class="<?= $this->statusVisibilityIcon($currentToot->visibility) ?>"
					  title="<?= Text::_('PLG_CONTENT_FEDIVERSE_LBL_VISIBILITY_' . $currentToot->visibility) ?>"
					  aria-hidden="true"
				></span>
				<span class="toot-embed-visually-hidden"><?= Text::_('PLG_CONTENT_FEDIVERSE_LBL_VISIBILITY_' . $currentToot->visibility) ?></span>
				<?= $this->timeAgo((new DateTime($currentToot->created_at))->getTimestamp()) ?>
			</a>
		</div>
		<?php endif; ?>
	</header>
	<div class="toot-embed-content <?= $sensitive ? 'toot-embed-content-sensitive' : '' ?>">
		<?php if ($sensitive): ?>
		<details>
			<summary>
				<span class="toot-embed-cw-badge">
					<span class="fa fa-exclamation-triangle"
						  title="<?= Text::_('PLG_CONTENT_FEDIVERSE_CONTENT_WARNING') ?> <?= htmlspecialchars($currentToot->spoiler_text, ENT_COMPAT, 'UTF-8') ?>"
						  aria-hidden="true"></span>
					<span class="toot-embed-visually-hidden"><?= Text::_('PLG_CONTENT_FEDIVERSE_CONTENT_WARNING') ?></span>
					<?= strip_tags($currentToot->spoiler_text) ?>
				</span>
			</summary>
			<?php endif; // sensitive ?>
			<div class="toot-embed-content-text">
				<?= $this->parseEmojis($currentToot->content, $currentToot->emojis) ?>
			</div>
			<?php
			if (is_countable($currentToot->media_attachments) && count($currentToot->media_attachments))
			{
				$webAssetManager->useScript('plg_content_fediverse.media');
				require PluginHelper::getLayoutPath($this->_type, $this->_name, 'default_media');
			}
			?>

			<?php require PluginHelper::getLayoutPath($this->_type, $this->_name, 'default_poll'); ?>

			<?php if ($sensitive): ?>
		</details>
	<?php endif; // sensitive ?>
	</div>
	<?php if (!$parentToot): ?>
	<div class="toot-embed-status">
		<span class="toot-embed-status-date">
			<a href="<?= $currentToot->url ?>" rel="nofollow">
				<?= HTMLHelper::_('date', $currentToot->created_at, Text::_('DATE_FORMAT_LC2')) ?>
			</a>
		</span>
		&bull;
		<span class="toot-embed-status-visibility">
			<span class="<?= $this->statusVisibilityIcon($currentToot->visibility) ?>"
				  title="<?= Text::_('PLG_CONTENT_FEDIVERSE_LBL_VISIBILITY_' . $currentToot->visibility) ?>"
				  aria-hidden="true"
			></span>
			<span class="toot-embed-visually-hidden"><?= Text::_('PLG_CONTENT_FEDIVERSE_LBL_VISIBILITY_' . $currentToot->visibility) ?></span>
		</span>
		&bull;
		<span class="toot-embed-status-software">
			<?php if (empty($currentToot->application->website)): ?>
				<?= htmlentities($currentToot->application->name, ENT_COMPAT, 'UTF-8') ?>
			<?php else: ?>
				<a href="<?= htmlentities($currentToot->application->website, ENT_COMPAT, 'UTF-8') ?>"
				   rel="nofollow">
					<?= htmlentities($currentToot->application->name, ENT_COMPAT, 'UTF-8') ?>
				</a>
			<?php endif ?>
		</span>
	</div>
	<?php endif ?>
	<?php
		$replies = Text::plural('PLG_CONTENT_FEDIVERSE_LBL_ACTION_REPLIES_N', $currentToot->replies_count);
		$boosts = Text::plural('PLG_CONTENT_FEDIVERSE_LBL_ACTION_BOOSTS_N', $currentToot->reblogs_count);
		$faves = Text::plural('PLG_CONTENT_FEDIVERSE_LBL_ACTION_FAVES_N', $currentToot->favourites_count);
	?>
	<div class="toot-embed-actions">
		<span class="toot-embed-actions-replies">
			<a href="<?= $currentToot->url ?>"
			   rel="nofollow">
				<span class="fa fa-reply" aria-hidden="true" title="<?= $replies ?>"></span>
				<span class="toot-embed-visually-hidden"><?= $replies ?></span>
				<?php if ($currentToot->replies_count > 0): ?>
				<span aria-hidden="true"><?= $currentToot->replies_count ?></span>
				<?php endif; ?>
			</a>
		</span>
		<span class="toot-embed-actions-boosts">
			<a href="<?= $currentToot->url ?>"
			   rel="nofollow">
				<span class="fa fa-retweet" aria-hidden="true" title="<?= $boosts ?>"></span>
				<span class="toot-embed-visually-hidden"><?= $boosts ?></span>
				<?php if ($currentToot->reblogs_count > 0): ?>
				<span aria-hidden="true"><?= $currentToot->reblogs_count ?></span>
				<?php endif; ?>
			</a>
		</span>
		<span class="toot-embed-actions-favourites">
			<a href="<?= $currentToot->url ?>"
			   rel="nofollow">
				<span class="fa fa-star" aria-hidden="true" title="<?= $faves ?>"></span>
				<span class="toot-embed-visually-hidden"><?= $faves ?></span>
				<?php if ($currentToot->favourites_count > 0): ?>
				<span aria-hidden="true"><?= $currentToot->favourites_count ?></span>
				<?php endif; ?>
			</a>
		</span>
		<span class="toot-embed-actions-share">
			<a href="<?= $currentToot->url ?>"
			   rel="nofollow">
				<span class="fa fa-share-alt" aria-hidden="true" title="<?= Text::_('PLG_CONTENT_FEDIVERSE_LBL_ACTION_SHARE') ?>"></span>
				<span class="toot-embed-visually-hidden">title="<?= Text::_('PLG_CONTENT_FEDIVERSE_LBL_ACTION_SHARE') ?>"</span>
			</a>
		</span>
	</div>
</aside>