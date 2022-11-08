<?php
/*
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   GNU General Public License, version 3
 */

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Feed\Feed;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
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
$hasImage       = $feed->image && $params->get('feed_image', 1) == 1;
$rtlFeed        = $params->get('feed_rtl', 0);
$direction      = $rtlFeed == 1 ? 'rtl' : 'ltr';
$maxItems       = min(count($feed), $params->get('feed_items', 10));

if ($hasDate)
{
	$dateSource = $feed->publishedDate ?? $feed->updatedDate->toISO8601() ?? null;
	$hasDate    = $hasDate !== null;
}

?>
<div style="direction: <?= $direction ?>" class="text-start fediverse-feed">

	<?php if ($hasTitle || $hasDate || $hasDescription || $hasImage): ?>
	<div class="mb-3 border-bottom border-muted">
		<?php if ($hasTitle || $hasImage): ?>
		<<?= $headerTag ?>>
			<?php if ($isLinked): ?>
			<a href="<?= htmlspecialchars($profileUrl, ENT_COMPAT, 'UTF-8') ?>"
			   target="_blank" rel="noopener"
			   class="d-flex flex-row gap-2 align-items-center justify-content-evenly">
			<?php else: ?>
				<span class="d-flex flex-row gap-2 align-items-center justify-content-evenly">
			<?php endif; ?>
				<?php if ($hasImage): ?>
				<span class="flex-shrink-1">
					<img src="<?= $feed->image->uri ?>"
						 alt="<?= $feed->image->title ?>"
						 class="img-fluid rounded-circle"
						 style="max-width: 2em">
				</span>
				<?php endif ?>
				<?php if ($hasTitle): ?>
				<span class="flex-grow-1">
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
		<p class="small text-muted">
			<?= $feed->description ?>
		</p>
		<?php endif ?>
		<?php if ($hasDate): ?>
		<p class="small text-muted text-center">
			<span class="fa fa-calendar" aria-hidden="true"></span>
			<span class="visually-hidden">Last updated on</span>
			<?= HTMLHelper::_('date', $dateSource, Text::_('DATE_FORMAT_LC5')) ?>
		</p>
		<?php endif ?>
	</div>
	<?php endif ?>

	<ul class="fediverse-toots list-unstyled m-0 p-0">
		<?php for ($i = 0; $i < $maxItems; $i++): ?>
			<?php
			$uri  = $feed[$i]->uri || !$feed[$i]->isPermaLink
				? trim($feed[$i]->uri)
				: trim($feed[$i]->guid);
			$uri  = !$uri || stripos($uri, 'http') !== 0 ? $feedUrl : $uri;
			[$contentWarning, $text] = $modFediverseFeedConvertText(trim($feed[$i]->content ?? ''));
			$mediaFiles = $feed[$i]?->media?->content ?? [];
			?>
			<li class="fediverse-toot m-0 p-0 <?= ($i !== 0) ? 'border-top border-muted pt-3' : '' ?> pb-1">
				<div class="fediverse-toot-text">
					<?php if (!empty($contentWarning)): ?>
					<details class="mb-2">
						<summary>
							<span class="fa fa-exclamation-triangle"
								  title="<?= Text::_('MOD_FEDIVERSEFEED_CONTENT_WARNING') ?> <?= htmlspecialchars($contentWarning, ENT_COMPAT, 'UTF-8') ?>"
								  aria-hidden="true"></span>
							<span class="visually-hidden"><?= Text::_('MOD_FEDIVERSEFEED_CONTENT_WARNING') ?></span>
							<strong><?= strip_tags($contentWarning) ?></strong>
						</summary>
						<?= $text ?>
					</details>
					<?php else: ?>
					<?= $text ?>
					<?php endif; ?>
				</div>

				<?php if (count($mediaFiles)): ?>
				<div class="fediverse-toot-media">
					<?php foreach ($mediaFiles as $media):
						$description = $media->description === null
							? null
							: htmlentities($media->description, ENT_COMPAT, 'UTF-8');
						?>
					<?php if (str_starts_with($media->item->type, 'image/')): ?>
						<img src="<?= $media->item->url ?>"
							 <?= $media->description ? 'alt="' . $description . '" title="' . $description . '"' : '' ?>
						/>
					<?php elseif (str_starts_with($media->item->type, 'video/')): ?>
						<video controls class="img-responsive" style="max-width: 100%">
							<source src="<?= $media->item->url ?>" type="<?= $media->item->type ?>">
						</video>
					<?php else: ?>
						<div class="alert alert-warning">
							<?= sprintf(
									'Unsupported media type ‘%s’ (%u bytes long)',
								$media->item->type,
								$media->item->fileSize,
							) ?>
						</div>
					<?php endif; ?>
					<?php endforeach; ?>
				</div>
				<?php endif ?>

				<div class="fediverse-toot-permalink text-end">
					<span class="fa fa-clock"
						  title="<?= Text::_('MOD_FEDIVERSEFEED_TOOTED_ON') ?>"
						  aria-hidden="true"></span>
					<span class="visually-hidden"><?= Text::_('MOD_FEDIVERSEFEED_TOOTED_ON') ?></span>
					<?php if (!empty($uri)) : ?>
						<span class="fediverse-toot-link">
                        	<a href="<?= htmlspecialchars($uri, ENT_COMPAT, 'UTF-8') ?>"
							   target="_blank" rel="noopener">
                        		<?= trim($feed[$i]->title) ?>
							</a>
						</span>
					<?php else : ?>
						<span class="fediverse-toot-link"><?= trim($feed[$i]->title) ?></span>
					<?php endif; ?>
				</div>
			</li>
		<?php endfor; ?>
	</ul>
</div>