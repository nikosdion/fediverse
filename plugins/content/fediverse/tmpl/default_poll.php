<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

defined('_JEXEC') || die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\WebAsset\WebAssetManager;
use Joomla\Plugin\Content\Fediverse\Extension\Fediverse;

/**
 * @var object $currentToot    The toot we are processing
 */

if (empty($currentToot->poll))
{
	return;
}

?>
<div class="toot-embed-poll">
	<?php foreach ($currentToot->poll->options as $option):
		$percent = 100 * ($option->votes_count / ($currentToot->poll->votes_count ?: 1));
		?>
	<div class="toot-embed-poll-option">
		<div class="toot-embed-poll-option-title">
			<span class="toot-embed-poll-option-percent">
				<?= sprintf('%0u%%', $percent) ?>
			</span>
			<span class="toot-embed-poll-option-label">
				<?= htmlentities($option->title, ENT_COMPAT, 'UTF-8') ?>
			</span>
		</div>
		<meter class="toot-embed-poll-option-percent"
		       min="0"
		       max="100"
		       value="<?= sprintf('%0.2f', $percent) ?>"
		       aria-hidden="true">
		</meter>
	</div>
	<?php endforeach; ?>
	<div class="toot-embed-poll-info">
		<span><?= Text::plural('PLG_CONTENT_FEDIVERSE_LBL_POLL_PEOPLE_N', $currentToot->poll->voters_count ?? 0) ?></span>
		<span>&bull;</span>
		<span>
			<?php if($currentToot->poll->expired): ?>
				<?= Text::sprintf('PLG_CONTENT_FEDIVERSE_LBL_POLL_EXPIRED', HTMLHelper::_('date', $currentToot->poll->expires_at, Text::_('DATE_FORMAT_LC2'))) ?>
			<?php else: ?>
				<?= Text::plural('PLG_CONTENT_FEDIVERSE_LBL_POLL_LEFT', $this->timeAgo($currentToot->poll->expires_at, false)) ?>
			<?php endif; ?>
		</span>
	</div>
</div>