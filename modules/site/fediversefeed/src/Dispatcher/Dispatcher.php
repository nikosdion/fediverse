<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Module\FediverseFeed\Site\Dispatcher;

use Dionysopoulos\Module\FediverseFeed\Site\Service\AccountLoader;
use Dionysopoulos\Module\FediverseFeed\Site\Service\TootStreamLoader;
use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Http\Http;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\WebAsset\WebAssetManager;
use Joomla\Registry\Registry;

class Dispatcher extends AbstractModuleDispatcher
{
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
	public function parseEmojis(string $text, array $emojis): string
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
						'class' => 'fediverse-content-emoji',
					]
				);
		}

		return str_replace(array_keys($replacements), array_values($replacements), $text);
	}

	/**
	 * Convert a language code to a writing system class (e.g. rtl, ltr, ...)
	 *
	 * @param   string|null  $lang
	 *
	 * @return  string
	 * @since   1.0.0
	 */
	public function langToWritingSystemClass(?string $lang): string
	{
		[$lang, ] = explode('-', strtolower($lang ?? ''));

		return match($lang) {
			/** @see https://lingohub.com/academy/glossary/right-to-left-language */
			'ar', 'arc', 'dv', 'fa', 'ha', 'he', 'khw', 'ks', 'ku', 'ps', 'ur', 'yi' => 'rtl',
			default => 'ltr',
		};
	}

	/**
	 * Returns the layout data.
	 *
	 * If false is returned, then it means that the dispatch process should be aborted.
	 *
	 * @return  array|false
	 *
	 * @throws  Exception
	 * @since   1.0.0
	 */
	protected function getLayoutData(): array|false
	{
		$layoutData = parent::getLayoutData();

		/** @var Registry $params */
		$params        = $layoutData['params'];
		$accountLoader = $this->getAccountLoader($params);
		$streamLoader  = $this->getStreamLoader(
			params       : $params,
			accountLoader: $accountLoader
		);

		$username    = $params->get('handle', '');
		$accountInfo = $accountLoader->getInformationFromUsername($username);

		if ($accountInfo === null)
		{
			return false;
		}

		$tootsStream = $streamLoader->getStreamForUsername($username, $accountInfo);

		if ($tootsStream === null)
		{
			return false;
		}

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

		/** @var WebAssetManager $wam */
		$wam = $this->app->getDocument()->getWebAssetManager();
		$wam->getRegistry()->addExtensionRegistryFile('mod_fediversefeed');

		return array_merge(
			$layoutData,
			[
				'self'            => $this,
				'toots'           => $tootsStream,
				'account'         => $accountInfo,
				'headerTag'       => $headerTag,
				'layoutsPath'     => realpath(__DIR__ . '/../../layout'),
				'webAssetManager' => $wam,
			]
		);
	}

	/**
	 * Returns the account loader service
	 *
	 * @param   Registry  $params  The module parameters
	 *
	 * @return  AccountLoader The account loader service
	 * @throws  Exception
	 * @since   1.0.0
	 */
	private function getAccountLoader(Registry $params): AccountLoader
	{
		return new AccountLoader(
			http          : $this->getHttp($params),
			app           : $this->app instanceof CMSApplication ? $this->app : Factory::getApplication(),
			cacheLifetime : (int) $params->get('account_cache_lifetime', 3600),
			requestTimeout: (int) $params->get('get_timeout', 5),
			useCaching    : $params->get('cache_feed', 1) == 1
		);
	}

	/**
	 * Get a preconfigured Joomla HTTP client object
	 *
	 * @param   Registry  $params  The module parameters
	 *
	 * @return  Http  The Joomla HTTP client object
	 * @since   1.0.0
	 */
	private function getHttp(Registry $params): Http
	{
		$optionsSource = [
			'userAgent' => 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:41.0) Gecko/20100101 Firefox/41.0',
		];

		if (!empty($customCertificate = $params->get('custom_certificate', null)))
		{
			$optionsSource['curl']   = [
				'certpath' => $customCertificate,
			];
			$optionsSource['stream'] = [
				'certpath' => $customCertificate,
			];
		}

		$httpParams = new Registry($optionsSource);

		return (new HttpFactory())->getHttp($httpParams);
	}

	/**
	 * Returns the toot stream (timeline) loader service
	 *
	 * @param   Registry            $params         The module parameters
	 * @param   AccountLoader|null  $accountLoader  The account loader service
	 *
	 * @return  TootStreamLoader  The toot stream (timeline) loader service
	 * @throws  Exception
	 * @since   1.0.0
	 */
	private function getStreamLoader(Registry $params, ?AccountLoader $accountLoader = null): TootStreamLoader
	{
		return new TootStreamLoader(
			http          : $this->getHttp($params),
			app           : $this->app instanceof CMSApplication ? $this->app : Factory::getApplication(),
			accountLoader : $accountLoader ?? $this->getAccountLoader($params),
			maxToots      : (int) $params->get('feed_items', 5),
			cacheLifetime : (int) $params->get('feed_cache_lifetime', 3600),
			requestTimeout: (int) $params->get('get_timeout', 5),
			useCaching    : $params->get('cache_feed', 1) == 1
		);
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
	public function timeAgo(int|string $referenceDateTime = 0, bool $short = true): string
	{
		if (is_string($referenceDateTime))
		{
			$referenceDateTime = (clone Factory::getDate($referenceDateTime))->getTimestamp();
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
			's' => [1, sprintf('MOD_FEDIVERSEFEED_TIME_%s_SECOND', $short ? 'SHORT' : 'LONG')],
			'm' => [60, sprintf('MOD_FEDIVERSEFEED_TIME_%s_MINUTE', $short ? 'SHORT' : 'LONG')],
			'h' => [60 * 60, sprintf('MOD_FEDIVERSEFEED_TIME_%s_HOUR', $short ? 'SHORT' : 'LONG')],
			'd' => [60 * 60 * 24, sprintf('MOD_FEDIVERSEFEED_TIME_%s_DAY', $short ? 'SHORT' : 'LONG')],
			'w' => [60 * 60 * 24 * 7, sprintf('MOD_FEDIVERSEFEED_TIME_%s_WEEK', $short ? 'SHORT' : 'LONG')],
			'y' => [60 * 60 * 24 * 365, sprintf('MOD_FEDIVERSEFEED_TIME_%s_YEAR', $short ? 'SHORT' : 'LONG')],
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