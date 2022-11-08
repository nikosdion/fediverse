<?php
/*
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   GNU General Public License, version 3
 */

defined('_JEXEC') || die;

use Joomla\CMS\Document\Feed\FeedItem;
use Joomla\CMS\Language\Text;
use Joomla\CMS\WebAsset\WebAssetManager;

/**
 * @var array           $mediaFiles       The media files of this item
 * @var FeedItem        $feedItem         The feed item
 * @var bool            $inContentWarning Am I already inside a Content Warning?
 * @var string          $layoutsPath      Custom Joomla Layouts root path
 * @var WebAssetManager $webAssetManager  Joomla's WebAssetManager
 */
extract($displayData);

if (empty($mediaFiles))
{
	return;
}

$webAssetManager->usePreset('mod_fediversefeed.media');

?>
<div class="fediverse-toot-media">
	<?php foreach ($mediaFiles as $media):
		$description = $media->description === null
			? null
			: htmlentities($media->description, ENT_COMPAT, 'UTF-8');
		$sensitive = !$inContentWarning && $media?->rating == 'adult' && $media?->rating_type === 'simple';
		?>
		<figure class="fediverse-toot-media-item">
			<?php if (str_starts_with($media->item->type, 'image/')): ?>
				<img src="<?= $media->item->url ?>"
					 class="<?= $sensitive ? 'fediverse-toot-media-sensitive' : '' ?>"
					<?= $media->description ? 'alt="' . $description . '" title="' . $description . '"' : '' ?>
						loading="lazy"
				/>
			<?php elseif (str_starts_with($media->item->type, 'video/')): ?>
				<video class="<?= $sensitive ? 'fediverse-toot-media-sensitive' : '' ?>">
					<source src="<?= $media->item->url ?>" type="<?= $media->item->type ?>">
				</video>
			<?php else: ?>
				<div class="alert alert-warning">
					<?= Text::sprintf(
						'MOD_FEDIVERSEFEED_ERR_UNSUPPORTED_MEDIA',
						$media->item->type,
						$media->item->fileSize,
					) ?>
				</div>
			<?php endif; ?>
		<?php if (!empty($media->description) || $sensitive): ?>
		<figcaption class="bg-dark text-white border-top border-muted px-2 py-1">
			<?php if ($sensitive): ?>
			<span class="badge bg-warning">
				<span class="fa fa-exclamation-triangle"
					  aria-hidden="true"></span>
				<?= Text::_('MOD_FEDIVERSEFEED_CONTENT_WARNING_BADGE') ?>
			</span>
			<?php endif; ?>
			<?= $media->description ?>
		</figcaption>
		<?php endif; ?>
		</figure>
	<?php endforeach; ?>
</div>

<?php if (defined('MOD_FEDIVERSEFEED_MODAL')) {
	return;
}

define('MOD_FEDIVERSEFEED_MODAL', 1);
?>
<div class="modal fade" id="mod_fediversemodal_dialog" tabindex="-1"
	 role="dialog" aria-label="mod_fediversemodal_dialog_title" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<button type="button" class="btn-close novalidate" data-bs-dismiss="modal"
					aria-label="<?= Text::_('JLIB_HTML_BEHAVIOR_CLOSE') ?>">
			</button>
			<div class="modal-body p-3" id="mod_fediversemodal_dialog_content">
			</div>
		</div>
	</div>
</div>