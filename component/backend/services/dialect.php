<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

/**
 * Add custom attributes to the core ActivityPub objects.
 *
 * Our ActivityPub objects include various properties which are used by Mastodon and OStatus to facilitate
 * interoperability with other ActivityPub consumers. By default, the ActivityPhp library will reject them as unknown.
 * Adding them in a Dialect allows them to be accepted without a hitch.
 *
 * @see   https://landrok.github.io/activitypub/activitypub-dialects-management.html
 * @since 2.0.0
 */
\ActivityPhp\Type\Dialect::add(
	'fediverse',
	[
		'Article|Note|Tombstone' => [
			'atomUri', 'inReplyToAtomUri', 'sensitive'
		],
		'Document|Image' => [
			'blurhash', 'width', 'height'
		],
		'Hashtag' => [
			'href', 'name'
		]
	]
);