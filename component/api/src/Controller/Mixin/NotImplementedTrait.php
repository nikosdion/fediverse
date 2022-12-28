<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Api\Controller\Mixin;

\defined('_JEXEC') || die;

trait NotImplementedTrait
{
	/**
	 * Handles features which are not yet implemented, returning an HTTP 405 Not Implemented response.
	 *
	 * @return  $this
	 * @since   2.0.0
	 */
	public function notImplemented()
	{
		$app = $this->app;
		$app->setHeader('Status', 405);
		$app->setHeader('Content-Type', 'application/json');

		$app->getDocument()->setBuffer(
			json_encode([
				'error'   => true,
				'code'    => 405,
				'message' => 'Not Implemented',
			])
		);

		return $this;
	}
}