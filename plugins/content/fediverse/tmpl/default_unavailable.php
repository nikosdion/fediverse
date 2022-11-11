<?php
/*
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   GNU General Public License, version 3
 */

defined('_JEXEC') || die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\WebAsset\WebAssetManager;
use Joomla\Plugin\Content\Fediverse\Extension\Fediverse;

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

?>
<aside class="toot-embed toot-embed-missing"
       data-url="<?= htmlentities($tootUrl, ENT_COMPAT, 'UTF-8') ?>">
	<header>
		<span class="fa fa-exclamation-circle" aria-hidden="true"></span>
		<?= Text::_('PLG_CONTENT_FEDIVERSE_ERR_CANNOT_LOAD_HEAD') ?>
	</header>
	<p>
		<?= Text::sprintf('PLG_CONTENT_FEDIVERSE_ERR_CANNOT_LOAD_BODY', htmlspecialchars($tootUrl, ENT_COMPAT, 'UTF-8')) ?>
	</p>
</aside>