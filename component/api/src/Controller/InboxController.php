<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2024 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Api\Controller;

\defined('_JEXEC') || die;

use Dionysopoulos\Component\ActivityPub\Api\Controller\Mixin\NotImplementedTrait;

/**
 * Controller for ActivityPub Inbox interactions
 *
 * @since  2.0.0
 */
class InboxController extends OutboxController
{
	use NotImplementedTrait;

	/**
	 * The content type returned by this API controller
	 *
	 * @var   string
	 * @since 2.0.0
	 */
	protected string $contentType = 'inbox';

	/**
	 * The prefix for the state variables set by this API controller
	 *
	 * @var   string
	 * @since 2.0.0
	 */
	protected string $statePrefix = 'inbox';
}