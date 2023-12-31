<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2024 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\Field;

\defined('_JEXEC') || die;

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\Language\LanguageHelper;

class LanguagesField extends ListField
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  2.0.0
	 */
	protected $type = 'Languages';

	/**
	 * Method to get the field options.
	 *
	 * @return  array  The field option objects.
	 *
	 * @since   2.0.0
	 */
	protected function getOptions()
	{
		// Initialize some field attributes.
		$client = (string) $this->element['client'];

		if ($client !== 'site' && $client !== 'administrator') {
			$client = 'site';
		}

		// Make sure the languages are sorted base on locale instead of random sorting
		$languages = LanguageHelper::createLanguageList($this->value, \constant('JPATH_' . strtoupper($client)), true, true);

		if (\count($languages) > 1) {
			usort(
				$languages,
				function ($a, $b) {
					return strcmp($a['value'], $b['value']);
				}
			);
		}

		// Merge any additional options in the XML definition.
		$options = array_merge(
			parent::getOptions(),
			$languages
		);

		return $options;
	}

}