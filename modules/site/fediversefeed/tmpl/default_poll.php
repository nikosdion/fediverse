<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

defined('_JEXEC') || die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/**
 * @var object $currentToot    The toot we are processing
 */

if (empty($currentToot->poll))
{
	return;
}

?>
<div class="fediverse-toot-poll">
	<?php foreach ($currentToot->poll->options as $option):
		$percent = 100 * ($option->votes_count / ($currentToot->poll->votes_count ?: 1));
		?>
	<div class="fediverse-toot-poll-option">
		<div class="fediverse-toot-poll-option-title">
			<span class="fediverse-toot-poll-option-percent">
				<?= sprintf('%0u%%', $percent) ?>
			</span>
			<span class="fediverse-toot-poll-option-label">
				<?= htmlentities($option->title, ENT_COMPAT, 'UTF-8') ?>
			</span>
		</div>
		<meter class="fediverse-toot-poll-option-percent"
		       min="0"
		       max="100"
		       value="<?= sprintf('%0.2f', $percent) ?>"
		       aria-hidden="true">
		</meter>
	</div>
	<?php endforeach; ?>
	<div class="fediverse-toot-poll-info">
		<span><?= Text::plural('PLG_CONTENT_FEDIVERSE_LBL_POLL_PEOPLE_N', $currentToot->poll->voters_count ?? 0) ?></span>
		<span>&bull;</span>
		<span>
			<?php if($currentToot->poll->expired): ?>
				<?= Text::sprintf('PLG_CONTENT_FEDIVERSE_LBL_POLL_EXPIRED', HTMLHelper::_('date', $currentToot->poll->expires_at, Text::_('DATE_FORMAT_LC2'))) ?>
			<?php else: ?>
				<?= Text::plural('PLG_CONTENT_FEDIVERSE_LBL_POLL_LEFT', $self->timeAgo($currentToot->poll->expires_at, false)) ?>
			<?php endif; ?>
		</span>
	</div>
</div>