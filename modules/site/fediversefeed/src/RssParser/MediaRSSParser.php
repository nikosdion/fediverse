<?php
/*
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   GNU General Public License, version 3
 */

namespace Joomla\Module\FediverseFeed\Site\RssParser;

use Joomla\CMS\Feed\Feed;
use Joomla\CMS\Feed\FeedEntry;
use Joomla\CMS\Feed\Parser\NamespaceParserInterface;
use SimpleXMLElement;

/**
 * RSS Feed Parser Namespace handler for MediaRSS.
 *
 * This is a minimal implementation, just enough to support Mastodon
 *
 * @link   https://www.rssboard.org/media-rss
 * @since  1.0.0
 */
class MediaRSSParser implements NamespaceParserInterface
{
	/**
	 * Method to handle an element for the feed given that the media namespace is present.
	 *
	 * @param   Feed              $feed  The Feed object being built from the parsed feed.
	 * @param   SimpleXMLElement  $el    The current XML element object to handle.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function processElementForFeed(Feed $feed, SimpleXMLElement $el) {}

	/**
	 * Method to handle the feed entry element for the feed given that the media namespace is present.
	 *
	 * @param   FeedEntry         $entry  The FeedEntry object being built from the parsed feed entry.
	 * @param   SimpleXMLElement  $el     The current XML element object to handle.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function processElementForFeedEntry(FeedEntry $entry, SimpleXMLElement $el)
	{
		$entry->media = new \stdClass();

		foreach ($el->children('media', true) as $child)
		{
			if ($child->getName() === 'content')
			{
				$entry->media->content = array_merge(
					$entry->media->content ?? [],
					[$this->parseContentTag($child)]
				);
			}
		}
	}

	/**
	 * Parses the media:content tag
	 *
	 * @param   SimpleXMLElement|null  $el
	 *
	 * @return  object
	 * @since   1.0.0
	 * @see     https://www.rssboard.org/media-rss#media-content
	 */
	private function parseContentTag(?SimpleXMLElement $el)
	{
		$ret = (object) [
			'item'        => (object) [
				'url'          => (string) $el->attributes()->url ?: null,
				'fileSize'     => (int) $el->attributes()->fileSize ?: null,
				'type'         => (string) $el->attributes()->type ?: null,
				'medium'       => (string) $el->attributes()->medium ?: null,
				'isDefault'    => (string) $el->attributes()->isDefault ?: null,
				'expression'   => (string) $el->attributes()->expression ?: null,
				'bitrate'      => (int) $el->attributes()->bitrate ?: null,
				'framerate'    => (float) $el->attributes()->framerate ?: null,
				'samplingrate' => (int) $el->attributes()->samplingrate ?: null,
				'channels'     => (int) $el->attributes()->channels ?: null,
				'duration'     => (string) $el->attributes()->duration ?: null,
				'height'       => (int) $el->attributes()->height ?: null,
				'width'        => (int) $el->attributes()->width ?: null,
				'lang'         => (string) $el->attributes()->lang ?: null,
			],
			'rating'      => 'nonadult',
			'rating_type' => 'simple',
			'description' => null,
		];

		foreach ($el->children('media', true) as $child)
		{
			switch ($child->getName())
			{
				case 'rating':
					$ret->rating      = (string) $child;
					$ret->rating_type = (string) $child->attributes()->scheme ?: 'simple';
					$ret->rating_type = str_starts_with($ret->rating_type, 'urn:')
						? substr($ret->rating_type, 4)
						: $ret->rating_type;
					break;

				case 'description':
					$ret->description = (string) $child;
					break;
			}
		}

		return $ret;
	}

}
