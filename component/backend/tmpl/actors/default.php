<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2024 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;

/** @var \Dionysopoulos\Component\ActivityPub\Administrator\View\Actors\HtmlView $this */

$app       = Factory::getApplication();
$user      = $this->getCurrentUser();
$userId    = $user->get('id');
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$saveOrder = $listOrder == 'ordering';
$nullDate  = Factory::getContainer()->get('DatabaseDriver')->getNullDate();

$i = 0;

?>
<form action="<?= Route::_('index.php?option=com_activitypub&view=actors'); ?>"
      method="post"
      name="adminForm" id="adminForm">
	<div class="row">
		<div class="col-md-12">
			<div id="j-main-container" class="j-main-container">
				<?= LayoutHelper::render('joomla.searchtools.default', ['view' => $this]) ?>
				<?php if (empty($this->items)) : ?>
					<div class="alert alert-info">
						<span class="icon-info-circle" aria-hidden="true"></span><span
							class="visually-hidden"><?= Text::_('INFO'); ?></span>
						<?= Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
					</div>
				<?php else : ?>
					<table class="table" id="actorstable">
						<caption class="visually-hidden">
							<?= Text::_('COM_ACTIVITYPUB_ACTORS_TABLE_CAPTION'); ?>,
							<span id="orderedBy"><?= Text::_('JGLOBAL_SORTED_BY'); ?> </span>,
							<span id="filteredBy"><?= Text::_('JGLOBAL_FILTERED_BY'); ?></span>
						</caption>
						<thead>
						<tr>
							<td class="w-1 text-center">
								<?= HTMLHelper::_('grid.checkall'); ?>
							</td>
							<th scope="col">
								<?= Text::_('COM_ACTIVITYPUB_ACTORS_LBL_USER'); ?>
							</th>
							<th scope="col">
								<?= Text::_('COM_ACTIVITYPUB_ACTORS_LBL_NAME'); ?>
							</th>
							<th scope="col">
								<?= Text::_('COM_ACTIVITYPUB_ACTORS_LBL_TYPE'); ?>
							</th>
							<th scope="col" class="w-1 d-none d-md-table-cell">
								<?= HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'id', $listDirn, $listOrder); ?>
							</th>
						</tr>
						</thead>
						<tbody>
						<?php foreach ($this->items as $item) : ?>
							<?php
							$canEdit  = $user->authorise('core.edit', 'com_ats');

							if ($item->user_id)
							{
								$itemUser = Factory::getContainer()
									->get(UserFactoryInterface::class)
									->loadUserById($item->user_id);
							}
							else
							{
								$itemUser = new User();
								$itemUser->name = $item->name;
								$itemUser->username = $item->username;
							}

							$typeIcon = match($item->type) {
								default => 'fa-user-tie',
								'Organization' => 'fa-building',
								'Service' => 'fa-cogs',
							};

							?>
						<tr class="row<?= $i++ % 2; ?>" data-draggable-group="0">
							<td class="text-center">
								<?= HTMLHelper::_('grid.id', $i, $item->id, false, 'cid', 'cb', $itemUser->name); ?>
							</td>
							<td>
								<?php if ($item->user_id): ?>
									<span class="fa fa-user" aria-hidden="true"></span>
									<?= $itemUser->username ?>
								<?php else: ?>
									<span class="text-muted">
									<span class="fa fa-user-cog" aria-hidden="true"></span>
									<span class="sr-only"><?= Text::_('COM_ACTIVITYPUB_ACTORS_LBL_VIRTUAL') ?></span>
										<?= $itemUser->username ?>
									</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ($canEdit): ?>
									<a href="<?= Route::_('index.php?option=com_activitypub&task=actor.edit&id=' . (int) $item->id); ?>"
									   title="<?= Text::_('JACTION_EDIT'); ?>"
									><?= $this->escape($itemUser->name); ?></a>
								<?php else: ?>
									<?= $this->escape($itemUser->name); ?>
								<?php endif ?>
							</td>
							<td class="d-none d-md-table-cell">
								<span class="fa <?= $typeIcon ?>" aria-hidden="true"></span>
								<?= Text::_('COM_ACTIVITYPUB_ACTOR_FIELD_TYPE_' . $item->type) ?>
							</td>
							<td class="w-1 d-none d-md-table-cell">
								<?= $item->id ?>
							</td>
						</tr>
						<?php endforeach; ?>
						</tbody>
					</table>

					<?php // Load the pagination. ?>
					<?= $this->pagination->getListFooter(); ?>
				<?php endif; ?>

				<input type="hidden" name="task" value=""> <input type="hidden" name="boxchecked" value="0">
				<?= HTMLHelper::_('form.token'); ?>
			</div>
		</div>
	</div>
</form>
