<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

defined('_JEXEC') or die;

/** @var \Dionysopoulos\Component\ActivityPub\Administrator\View\Actor\HtmlView $this */

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('behavior.keepalive');

?>
<form action="<?= Route::_('index.php?option=com_activitypub&view=actor&layout=edit&id=' . $this->item->id) ?>"
      aria-label="<?= Text::_('COM_ACTIVITYPUB_ACTOR_EDIT', true) ?>"
      class="form-validate" id="autoreply-form" method="post" name="adminForm">

	<div class="main-card">
		<?= HTMLHelper::_('uitab.startTabSet', 'com_activitypub_admin_actor', [
			'recall' => true,
		]) ?>

		<?php foreach ($this->form->getFieldsets() as $fieldset => $info): ?>
			<?= HTMLHelper::_('uitab.addTab', 'com_activitypub_admin_actor', $fieldset, Text::_($info->label)) ?>

			<?php foreach ($this->form->getFieldset($fieldset) as $field): ?>
				<?= $field->renderField(); ?>
			<?php endforeach; ?>

			<?= HTMLHelper::_('uitab.endTab') ?>
		<?php endforeach ?>

		<?= HTMLHelper::_('uitab.endTabSet') ?>
	</div>

	<input type="hidden" name="task" value="">
	<?= HTMLHelper::_('form.token') ?>
</form>

