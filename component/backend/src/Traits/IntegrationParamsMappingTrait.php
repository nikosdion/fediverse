<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\Traits;

use Joomla\Registry\Registry;

/**
 * Handles an Actor's integration parameters.
 *
 * It can convert between the form data and the internal parameters registry object representation, in either way.
 *
 * @since 2.0.0
 */
trait IntegrationParamsMappingTrait
{
	private array $integrationParamsMap = [];

	/**
	 * Add a mapping between a form field and a parameters' registry key.
	 *
	 * @param   string      $formKey    The form field name, e.g. "my_custom_something".
	 * @param   string      $paramsKey  The corresponding parameters registry key, e.g. "myCustom.something".
	 * @param   mixed|null  $default    The default value to use when there's no value set.
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	protected function addIntegrationParamsMapping(string $formKey, string $paramsKey, mixed $default = null): void
	{
		$this->integrationParamsMap[] = [$formKey, $paramsKey, $default];
		$this->integrationParamsMap   = array_unique($this->integrationParamsMap, SORT_REGULAR);
	}

	/**
	 * Set values in the parameters' registry from an array of posted form data.
	 *
	 * @param   Registry  $params  The parameters registry object.
	 * @param   array     $data    The form data POSTed to the application.
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	protected function setParamsFromFormData(Registry $params, array $data): void
	{
		foreach ($this->integrationParamsMap as $info)
		{
			[$formKey, $paramsKey, $default] = $info;
			$params->set($paramsKey, $data[$formKey] ?? $default);
		}
	}

	/**
	 * Set values into the form data object from the parameters' registry.
	 *
	 * @param   Registry  $params  The parameters registry object.
	 * @param   array     $data    The form data object which will be used to populate the form.
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	protected function setFormDataFromParams(Registry $params, array|object &$data): void
	{
		foreach ($this->integrationParamsMap as $info)
		{
			[$formKey, $paramsKey, $default] = $info;

			if (is_array($data))
			{
				$data[$formKey] = $params->get($paramsKey, $default);
			}
			else
			{
				$data->{$formKey} = $params->get($paramsKey, $default);
			}
		}
	}
}