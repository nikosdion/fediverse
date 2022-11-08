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
 * RSS Feed Parser Namespace handler for WebFeeds.
 *
 * This is a minimal implementation, just enough to support Mastodon
 *
 * @since  1.0.0
 */
class WebfeedsRSSParser implements NamespaceParserInterface
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
	public function processElementForFeed(Feed $feed, SimpleXMLElement $el)
	{
		foreach ($el->children('webfeeds', true) as $child)
		{
			switch ($child->getName())
			{
				case 'icon':
					$feed->icon = (string) $child;
					break;
				case 'logo':
					$feed->logo = (string) $child;
					break;
			}
		}
	}

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
	public function processElementForFeedEntry(FeedEntry $entry, SimpleXMLElement $el) {}
}
