<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2024 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Uri\Uri;

\defined('_JEXEC') || die;

/** @var \Dionysopoulos\Component\ActivityPub\Site\View\Profile\HtmlView $this */

?>
	<header id="activitypub-profile-header" class="d-flex gap-1 flex-column">
		<?php if (!empty($this->actorObject->image)): ?>
			<div class="activitypub-profile-header-image">
				<?= HTMLHelper::image($this->actorObject->image->url, '', ['class' => 'img-fluid w-100']) ?>
			</div>
		<?php endif ?>
		<div id="activitypub-profile-key-info" class="d-flex gap-2 flex-row align-items-center">
			<?php if (!empty($this->actorObject->icon)): ?>
				<?= HTMLHelper::image(
					$this->actorObject->icon->url,
					'',
					[
						'id'    => 'activitypub-profile-avatar',
						'class' => 'img-fluid rounded',
						'style' => 'max-width: 5em; max-height: 5em',
					]
				) ?>
			<?php endif; ?>

			<div class="flex-grow-1">
				<div class="d-flex">
					<h2 id="activitypub-profile-fullname" class="flex-grow-1">
						<?= $this->escape($this->actorObject->name) ?>
					</h2>
					<span class="text-muted">
					<?php if ($this->actorObject->type === 'Person'): ?>
						<span class="fa fa-user" aria-hidden="true"></span>
					<?php elseif ($this->actorObject->type === 'Organization'): ?>
						<span class="fa fa-building" aria-hidden="true"></span>
					<?php elseif ($this->actorObject->type === 'Service'): ?>
						<span class="fa fa-user-cog" aria-hidden="true"></span>
					<?php endif; ?>
				</span>
				</div>
				<p id="activitypub-profile-username" class="font-monospace text-muted small">
					@<?= $this->user->username ?>@<?= Uri::getInstance()->toString(['host']) ?>
				</p>
			</div>
		</div>
	</header>
<?php if (!empty($this->actorObject->summary)): ?>
	<section id="activitypub-profile-summary" class="my-2 p-2">
		<?= $this->actorObject->summary ?>
	</section>
<?php endif; ?>