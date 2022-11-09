<?php
/*
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   GNU General Public License, version 3
 */

// Prevent direct access
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;

defined('_JEXEC') or die;

class Pkg_FediverseInstallerScript extends InstallerScript
{
	protected $minimumJoomla = '4.2';

	protected $minimumPhp = '8.0.0';

	/**
	 * A list of extensions (modules, plugins) to enable after installation. Each item has four values, in this order:
	 * type (plugin, module, ...), name (of the extension), client (0=site, 1=admin), group (for plugins).
	 *
	 * @since 1.0.0
	 * @var   array
	 */
	protected $extensionsToEnable = [
		// System plugins
		['plugin', 'fediverse', 1, 'content'],
	];


	/**
	 * Tuns on installation (but not on upgrade). This happens in install and discover_install installation routes.
	 *
	 * @param   \JInstallerAdapterPackage  $parent  Parent object
	 *
	 * @return  bool
	 * @since   1.0.0
	 */
	public function install(InstallerAdapter $parent): bool
	{
		// Enable the extensions we need to install
		foreach ($this->extensionsToEnable as $ext)
		{
			$this->enableExtension($ext[0], $ext[1], $ext[2], $ext[3]);
		}

		return true;
	}


	/**
	 * Enable an extension
	 *
	 * @param   string       $type    The extension type.
	 * @param   string       $name    The name of the extension (the element field).
	 * @param   integer      $client  The application id (0: Joomla CMS site; 1: Joomla CMS administrator).
	 * @param   string|null  $group   The extension group (for plugins).
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	private function enableExtension(string $type, string $name, int $client = 1, string $group = null): void
	{
		try
		{
			/** @var DatabaseDriver $db */
			$db    = Factory::getApplication()->get('DatabaseDriver');
			$query = $db->getQuery(true)
			            ->update('#__extensions')
			            ->set($db->qn('enabled') . ' = ' . $db->q(1))
			            ->where('type = :type')
			            ->where('element = :element')
			            ->bind(':type', $type, ParameterType::STRING)
			            ->bind(':element', $name, ParameterType::STRING);
		}
		catch (Exception $e)
		{
			return;
		}


		switch ($type)
		{
			case 'plugin':
				// Plugins have a folder but not a client
				$query->where('folder = :folder')
				      ->bind(':folder', $group, ParameterType::STRING);
				break;

			case 'language':
			case 'module':
			case 'template':
				// Languages, modules and templates have a client but not a folder
				$query->where('client_id = :client_id')
				      ->bind(':client_id', $client, ParameterType::INTEGER);
				break;

			default:
			case 'library':
			case 'package':
			case 'component':
				// Components, packages and libraries don't have a folder or client.
				break;
		}

		try
		{
			$db->setQuery($query);
			$db->execute();
		}
		catch (Throwable $e)
		{
		}
	}
}
