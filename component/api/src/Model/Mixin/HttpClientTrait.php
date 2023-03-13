<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Api\Model\Mixin;

\defined('_JEXEC') || die;

use Joomla\CMS\Http\Http;
use Joomla\CMS\Http\HttpFactory;

/**
 * A trait to get a configured Joomla HTTP client instance.
 *
 * @since  2.0.0
 */
trait HttpClientTrait
{
	/**
	 * Get the Joomla HTTP client
	 *
	 * @return Http
	 * @since  2.0.0
	 */
	private function getHttpClient(): Http
	{
		$options = (defined('JDEBUG') && JDEBUG)
			? [
				CURLOPT_SSL_VERIFYHOST   => 0,
				CURLOPT_SSL_VERIFYPEER   => 0,
				CURLOPT_SSL_VERIFYSTATUS => 0,
			] : [];

		return HttpFactory::getHttp([
			'transport.curl' => $options,
		]);
	}

}