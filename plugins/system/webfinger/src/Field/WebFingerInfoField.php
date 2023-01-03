<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Plugin\System\WebFinger\Field;

defined('_JEXEC') || die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

/**
 * A custom form field to show notes about what WebFinger is (and warn if it's not working right).
 *
 * @since  2.0.0
 */
class WebFingerInfoField extends FormField
{
	/**
	 * The form control prefix for field names from the Form object attached to the form field.
	 *
	 * @var    string
	 * @since  2.0.0
	 */
	protected $formControl = 'WebFingerInfo';

	/**
	 * Method to get the field input markup.
	 *
	 * @return  string  The field input markup.
	 *
	 * @since   2.0.0
	 */
	protected function getInput()
	{
		// Get the standard message
		$messages = [
			['info', $this->getNoteMessage()],
		];

		// Do I need to add an error message about SEF URLs?
		$app  = Factory::getApplication();
		$user = $app->getIdentity();

		if (
			$user->authorise('core.admin') &&
			(!$app->get('sef', false) || !$app->get('sef_rewrite'))
		)
		{
			array_unshift($messages, [
				'danger', Text::_('PLG_SYSTEM_WEBFINGER_CONFIG_NOTE_SEF_REQUIRED'),
			]);
		}

		return implode(
			"\n",
			array_map(
				function (array $message): string {
					[$type, $text] = $message;

					return <<< HTML
<div class="mb-4 alert alert-{$type}">{$text}</div>
HTML;

				},
				$messages
			)
		);
	}

	/**
	 * Return the standard note message for the current user.
	 *
	 * @return  string
	 * @throws  \Exception
	 * @since   2.0.0
	 */
	private function getNoteMessage(): string
	{
		$app  = Factory::getApplication();
		$user = $app->getIdentity();
		$url  = rtrim(Uri::base(false), '/');

		if (str_ends_with($url, '/administrator'))
		{
			$url = substr($url, 0, -14);
		}

		$hostname = Uri::getInstance()->toString(['host']);
		$hostname = str_starts_with($hostname, 'www.') ? substr($hostname, 4) : $hostname;
		$url      .= sprintf("/.well-known/webfinger?resource=acct:%s@%s", $user->username, $hostname);

		return Text::sprintf('PLG_SYSTEM_WEBFINGER_CONFIG_NOTE_WHATIS', $url);
	}
}