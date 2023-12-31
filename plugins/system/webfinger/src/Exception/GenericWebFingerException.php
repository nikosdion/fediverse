<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2024 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Plugin\System\WebFinger\Exception;

defined('_JEXEC') || die;

use RuntimeException;
use Throwable;

/**
 * Generic WebFinger exception with a message to be returned to the user.
 *
 * @since  2.0.0
 */
class GenericWebFingerException extends RuntimeException
{
	public function __construct(string $message = "", ?Throwable $previous = null)
	{
		parent::__construct($message, 500, $previous);
	}

}