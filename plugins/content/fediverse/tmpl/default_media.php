<?php
/*
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

defined('_JEXEC') || die;

/**
 * @var object $currentToot    The toot we are processing
 * @var bool   $sensitiveMedia Is the media (and only the media!) marked as sensitive?
 * @var bool   $parentToot     Is this a parent (in reply to) toot?
 */

use Joomla\CMS\Language\Text;

?>
<div class="toot-embed-content-media">
<?php foreach($currentToot->media_attachments as $attachment): ?>
	<figure class="toot-embed-content-media-item">
		<?php if (str_starts_with($attachment->type, 'image')): ?>
		<img src="<?= $attachment->url ?? $attachment->remote_url ?? '' ?>"
		     class="<?= $sensitiveMedia ? 'toot-embed-content-media-item-sensitive' : '' ?>"
			<?= $attachment->description ? 'alt="' . $attachment->description . '" title="' . $attachment->description . '"' : '' ?>
			 loading="lazy"
		/>
		<?php elseif (
			str_starts_with($attachment->type, 'video')
			|| str_starts_with($attachment->type, 'gifv')
		): ?>
		<video class="<?= $sensitiveMedia ? 'toot-embed-content-media-item-sensitive' : '' ?>"
		       preload="metadata"
		       poster="<?= $attachment->preview_url ?? $attachment->preview_remote_url ?? '' ?>"
		>
			<source src="<?= $attachment->url ?? $attachment->remote_url ?? '' ?>">
		</video>
		<?php else: ?>
		<div class="alert alert-warning">
			<?= Text::sprintf(
				'PLG_CONTENT_FEDIVERSE_ERR_UNSUPPORTED_MEDIA',
				$attachment->type
			) ?>
		</div>
		<?php endif ?>
		<?php if (!empty($attachment->description) || $sensitiveMedia): ?>
		<figcaption>
			<?php if ($sensitiveMedia): ?>
			<span class="toot-embed-cw-badge">
				<span class="fa fa-exclamation-triangle"
				      title="<?= Text::_('PLG_CONTENT_FEDIVERSE_CONTENT_WARNING_BADGE') ?>"
				      aria-hidden="true"></span>
				<?= Text::_('PLG_CONTENT_FEDIVERSE_CONTENT_WARNING_BADGE') ?>
			</span>
			<?php endif; ?>
			<?php if (!empty($attachment->description)): ?>
				<?= $attachment->description ?>
			<?php endif ?>
		</figcaption>
		<?php endif; ?>
	</figure>
<?php endforeach; ?>
</div>

<?php if (defined('PLG_CONTENT_FEDIVERSE_MODAL')) {
	return;
}

define('PLG_CONTENT_FEDIVERSE_MODAL', 1);
?>
<div class="modal fade" id="plg_content_fediverse_dialog" tabindex="-1"
     role="dialog" aria-label="plg_content_fediverse_dialog_title" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<button type="button" class="btn-close novalidate" data-bs-dismiss="modal"
			        aria-label="<?= Text::_('JLIB_HTML_BEHAVIOR_CLOSE') ?>">
			</button>
			<div class="modal-body p-3" id="plg_content_fediverse_dialog_content">
			</div>
		</div>
	</div>
</div>