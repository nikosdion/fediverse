<?php
/*
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   GNU General Public License, version 3
 */

namespace Joomla\Module\FediverseFeed\Site\Dispatcher;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Extension\ModuleInterface;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\WebAsset\WebAssetManager;
use Joomla\Input\Input;
use Joomla\Module\FediverseFeed\Site\Helper\FediverseFeedHelper;
use Joomla\Registry\Registry;

class Dispatcher extends AbstractModuleDispatcher
{
	/**
	 * The module extension object.
	 *
	 * @since 1.0.0
	 * @var   ModuleInterface
	 */
	private ModuleInterface $moduleExtension;

	public function __construct(\stdClass $module, CMSApplicationInterface $app, Input $input)
	{
		parent::__construct($module, $app, $input);

		$this->moduleExtension = $this->app->bootModule('mod_fediversefeed', 'site');
	}

	protected function getLayoutData()
	{
		/** @var FediverseFeedHelper $helper */
		$helper = $this->moduleExtension->getHelper('FediverseFeedHelper');
		$helper->setApplication($this->app);

		$layoutData = parent::getLayoutData();
		/** @var Registry $params */
		$params   = $layoutData['params'];
		$username = $params->get('handle', '');
		$feedURL  = $helper->getFeedURL($username);
		$feed     = $feedURL ? $helper->getFeed($feedURL, $params) : null;

		$headerTag = $params->get('header_tag', 'h3');
		$headerTag = match ($headerTag)
		{
			'h1' => 'h2',
			'h2' => 'h3',
			'h3' => 'h4',
			'h4' => 'h5',
			'h5' => 'h6',
			'h6' => 'p',
			'default' => 'h3',
		};

		if ($feedURL)
		{
			$profileUrl = str_replace('@', 'web/@', substr($feedURL, 0, -4));
		}

		/** @var WebAssetManager $wam */
		$wam = $this->app->getDocument()->getWebAssetManager();
		$wam->getRegistry()->addExtensionRegistryFile('mod_fediversefeed');

		return array_merge(
			$layoutData,
			[
				'feed'                        => $feed,
				'feedUrl'                     => $feedURL,
				'profileUrl'                  => $profileUrl ?? '',
				'headerTag'                   => $headerTag,
				'layoutsPath'                 => realpath(__DIR__ . '/../../layout'),
				'webAssetManager'             => $wam,
				'modFediverseFeedConvertText' => function (string $text) use ($params): array {
					// Make links visible again
					$text = str_replace('<span class="invisible"', '<span ', $text);
					// Strip the images.
					$text = OutputFilter::stripImages($text);

					// Process content warning
					$contentWarning = null;

					if (str_starts_with($text, '<p><strong>'))
					{
						$hrPos          = strpos($text, '<hr />');
						$contentWarning = substr($text, 0, $hrPos);
						$strongPos      = strpos($contentWarning, '</strong>');
						$contentWarning = strip_tags(substr($contentWarning, $strongPos + 9));
						$text           = substr($text, $hrPos + 6);
					}

					$text = HTMLHelper::_('string.truncate', $text, $params->get('word_count', 0));
					$text = str_replace('&apos;', "'", $text);

					return [$contentWarning, $text];
				},
			]
		);
	}

}