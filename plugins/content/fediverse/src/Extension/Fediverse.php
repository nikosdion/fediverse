<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Joomla\Plugin\Content\Fediverse\Extension;

use Joomla\CMS\Date\Date;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\WebAsset\WebAssetManager;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Plugin\Content\Fediverse\Service\TootLoader;
use Joomla\String\StringHelper;

class Fediverse extends CMSPlugin implements SubscriberInterface
{
	public function __construct(
		&$subject,
		$config = [],
		private ?TootLoader $tootLoader = null
	)
	{
		parent::__construct($subject, $config);
	}

	/**
	 * Returns an array of events this subscriber will listen to.
	 *
	 * @return  array
	 * @since   1.0.0
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onContentPrepare' => 'onContentPrepare',
		];
	}

	/**
	 * Handles the onContentPrepare event
	 *
	 * @param   Event  $event  The event we are handling
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function onContentPrepare(Event $event)
	{
		[$context, $article, $params, $limitstart] = $event->getArguments();

		// Check whether the plugin should process or not
		if (empty($article?->text) || StringHelper::strpos($article?->text ?? '', 'toot') === false)
		{
			return;
		}

		/** @var WebAssetManager $webAssetManager */
		$webAssetManager = $this->getApplication()->getDocument()->getWebAssetManager();
		$webAssetManager->getRegistry()->addExtensionRegistryFile('plg_content_fediverse');

		$this->loadLanguage();

		$article->text = preg_replace_callback(
			'#{[\s]*toot[\s]*(.*)[\s]*}#sU',
			[$this, 'processTootEmbed'],
			$article->text
		);
	}

	/**
	 * Convert a language code to a writing system class (e.g. rtl, ltr, ...)
	 *
	 * @param   string|null  $lang
	 *
	 * @return  string
	 * @since   1.0.0
	 */
	private function langToWritingSystemClass(?string $lang): string
	{
		[$lang,] = explode('-', strtolower($lang ?? ''));

		return match ($lang)
		{
			/** @see https://lingohub.com/academy/glossary/right-to-left-language */
			'ar', 'arc', 'dv', 'fa', 'ha', 'he', 'khw', 'ks', 'ku', 'ps', 'ur', 'yi' => 'rtl',
			default => 'ltr',
		};
	}

	/**
	 * Replaces custom emojis in a string with <img> tags.
	 *
	 * @param   string  $text    The original text, including emoji shortcodes
	 * @param   array   $emojis  An array of Mastodon API Emoji objects
	 *
	 * @return  string
	 * @since   1.0.0
	 * @see     https://docs.joinmastodon.org/entities/emoji/
	 */
	private function parseEmojis(string $text, array $emojis): string
	{
		if (empty($emojis))
		{
			return $text;
		}

		$replacements = [];

		foreach ($emojis as $emoji)
		{
			$replacements[sprintf(':%s:', $emoji->shortcode)] =
				HTMLHelper::_(
					'image',
					$emoji->url,
					$emoji->shortcode,
					[
						'class' => 'plg-fediverse-content-emoji',
					]
				);
		}

		return str_replace(array_keys($replacements), array_values($replacements), $text);
	}

	/**
	 * Processes an embed toot plugin code
	 *
	 * @param   array  $match  The array of RegEx matches
	 *
	 * @return  string
	 * @since   1.0.0
	 * @see     self::onContentPrepare()
	 */
	private function processTootEmbed(array $match): string
	{
		if (count($match) < 2)
		{
			return $match[0];
		}

		$embedParams = [];
		$tootUrl     = trim(strip_tags($match[1]));
		// Replace non-breaking space
		$tootUrl = str_replace(chr(0xC2) . chr(0xA0), ' ', $tootUrl);
		$bits    = preg_split('[\s]', $tootUrl);

		if (!empty($bits))
		{
			$tootUrl     = array_shift($bits);
			$embedParams = $bits;
		}

		unset($bits);

		$embedParams = array_filter($embedParams);
		$toot        = $this->tootLoader->getTootInformation($tootUrl);

		$serverFromUrl = function (?string $url): string {
			if (empty($url))
			{
				return '';
			}

			$delimiterPos = strpos($url, '/@') ?: strpos($url, '/web') ?: null;

			if ($delimiterPos === null)
			{
				return '';
			}

			$server = substr($url, 0, $delimiterPos);
			$parts  = explode('/', $server);

			return end($parts);
		};

		/** @var WebAssetManager $webAssetManager */
		$webAssetManager = $this->getApplication()->getDocument()->getWebAssetManager();

		@ob_start();

		require PluginHelper::getLayoutPath($this->_type, $this->_name);

		return @ob_get_clean();
	}

	/**
	 * Returns the appropriate icon class for a status' visibility
	 *
	 * @param   string  $visibility
	 *
	 * @return  string
	 * @since   1.0.0
	 */
	private function statusVisibilityIcon(?string $visibility): string
	{
		return match ($visibility)
		{
			default => 'fa fa-globe',
			'unlisted' => 'fa fa-lock-open',
			'private' => 'fa fa-lock',
			'direct' => 'fa fa-at',
			null => 'fa fa-bomb'
		};
	}

	/**
	 * Returns a short description of how much time has elapsed, e.g. "24h" for about 24 hours ago.
	 *
	 * @param   int|string  $referenceDateTime  Timestamp os ISO date of the reference date/time
	 * @param   bool        $short              Use short form (s, m, ...) or long form (seconds, minutes, ...)?
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function timeAgo(int|string $referenceDateTime = 0, bool $short = true): string
	{
		if (is_string($referenceDateTime))
		{
			$referenceDateTime = Date::getInstance($referenceDateTime)->getTimestamp();
		}

		$currentDateTime = time();
		$raw             = $currentDateTime - $referenceDateTime;
		$clean           = abs($raw);

		$calcNum = [
			['s', 60],
			['m', 60 * 60],
			['h', 60 * 60 * 60],
			['d', 60 * 60 * 60 * 24],
			['w', 60 * 60 * 60 * 24 * 7],
			['y', 60 * 60 * 60 * 24 * 365],
		];

		$calc = [
			's' => [1, sprintf('PLG_CONTENT_FEDIVERSE_TIME_%s_SECOND', $short ? 'SHORT' : 'LONG')],
			'm' => [60, sprintf('PLG_CONTENT_FEDIVERSE_TIME_%s_MINUTE', $short ? 'SHORT' : 'LONG')],
			'h' => [60 * 60, sprintf('PLG_CONTENT_FEDIVERSE_TIME_%s_HOUR', $short ? 'SHORT' : 'LONG')],
			'd' => [60 * 60 * 24, sprintf('PLG_CONTENT_FEDIVERSE_TIME_%s_DAY', $short ? 'SHORT' : 'LONG')],
			'w' => [60 * 60 * 24 * 7, sprintf('PLG_CONTENT_FEDIVERSE_TIME_%s_WEEK', $short ? 'SHORT' : 'LONG')],
			'y' => [60 * 60 * 24 * 365, sprintf('PLG_CONTENT_FEDIVERSE_TIME_%s_YEAR', $short ? 'SHORT' : 'LONG')],
		];

		$usemeasure = 's';

		for ($i = 0; $i < count($calcNum); $i++)
		{
			if ($clean <= $calcNum[$i][1])
			{
				$usemeasure = $calcNum[$i][0];
				$i          = count($calcNum);
			}
		}

		$datedifference = floor($clean / $calc[$usemeasure][0]);

		if ($referenceDateTime != 0)
		{
			return Text::plural($calc[$usemeasure][1], $datedifference);
		}

		return '';
	}

}