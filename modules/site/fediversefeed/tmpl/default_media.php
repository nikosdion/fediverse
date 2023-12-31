<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2024 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

defined('_JEXEC') || die;

use Dionysopoulos\Module\FediverseFeed\Site\Dispatcher\Dispatcher;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\WebAsset\WebAssetManager;
use Joomla\Input\Input;
use Joomla\Registry\Registry;

/**
 * These variables are extracted from the indexed array returned by the getLayoutData() method.
 *
 * @see \Dionysopoulos\Module\FediverseFeed\Site\Dispatcher\Dispatcher::getLayoutData()
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
 * @var stdClass        $toot            The current toot being rendered
 */

if ($params->get('feed_media', 1) != 1 || empty($toot->media_attachments))
{
	return;
}

$webAssetManager->usePreset('mod_fediversefeed.media');

?>
<div class="fediverse-toot-media">
	<?php foreach ($toot->media_attachments as $media):
		$description = htmlentities(trim($media->description ?? ''), ENT_COMPAT, 'UTF-8') ?: null;
		$sensitive = $toot->sensitive && empty($toot->spoiler_text);
		?>
		<figure class="fediverse-toot-media-item">
			<?php if ($media->type === 'image'): ?>
				<picture class="<?= $sensitive ? 'fediverse-toot-media-sensitive' : '' ?>">
					<source srcset="<?= $media->url ?>"
							height="<?= $media->meta->original->height ?>"
							width="<?= $media->meta->original->width ?>">
					<source srcset="<?= $media->preview_url ?>"
							height="<?= $media->meta->small->height ?>"
							width="<?= $media->meta->small->width ?>">
					<img src="<?= $media->preview_url ?>"
						<?php if (!empty($description)): ?>
							alt="<?= $description ?>" title="<?= $description ?>"
						<?php else: ?>
							alt=""
						<?php endif ?>
						 loading="lazy"
					/>
				</picture>
			<?php elseif ($media->type === 'video' || $media->type === 'gifv'): ?>
				<video class="<?= $sensitive ? 'fediverse-toot-media-sensitive' : '' ?>"
					   poster="<?= $media->preview_url ?>"
					   preload="metadata"
				>
					<source src="<?= $media->url ?>">
				</video>
			<?php else: ?>
				<div class="alert alert-warning">
					<?= Text::sprintf(
						'MOD_FEDIVERSEFEED_ERR_UNSUPPORTED_MEDIA',
						$media->type
					) ?>
				</div>
			<?php endif; ?>
			<?php if (!empty($description) || $sensitive): ?>
			<figcaption>
				<?php if ($sensitive): ?>
					<span class="badge bg-warning fediverse-cw-badge">
						<span class="fa fa-exclamation-triangle" aria-hidden="true"></span>
						<?= Text::_('MOD_FEDIVERSEFEED_CONTENT_WARNING_BADGE') ?>
					</span>
				<?php endif; ?>
				<?= $description ?>
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
