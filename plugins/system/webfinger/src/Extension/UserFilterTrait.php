<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2024 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Plugin\System\WebFinger\Extension;

use Exception;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;

defined('_JEXEC') || die;

/**
 * Filter users by their WebFinger consent options
 *
 * @since 2.0.0
 */
trait UserFilterTrait
{
	/**
	 * Filter users by WebFinger consent.
	 *
	 * A NULL input results in false; you cannot display a non-existent user record.
	 *
	 * A user record with an ID of 0 always results in true; this is reserved for “virtual” users and non-user resources
	 * which warrant special WebFinger processing by the plugins in the `webfinger` group.
	 *
	 * If a user is forcibly marked as non-consenting, or they have not provided explicit consent yet, or they have
	 * provided explicit non-consent we return false.
	 *
	 * If a user is forcibly marked as consenting, or they have provided explicit consent we return true.
	 *
	 * This allows the WebFinger code to determine whether a user can be displayed or not.
	 *
	 * @param   User|null  $user  The user record to filter
	 *
	 * @return  bool  True if they have consented (or are forcibly consented) to being listed in WebFinger
	 * @since   2.0.0
	 */
	protected function filterUser(?User $user): bool
	{
		// No user?
		if (empty($user))
		{
			return false;
		}

		// Virtual user: always allowed
		if (empty($user->id))
		{
			return true;
		}

		// Users with pending activation or outright blocked: not allowed.
		if (!empty($user->activation) || $user->block)
		{
			return false;
		}

		// Do I have forced consent?
		$forcedConsent = $this->getForcedConsent($user);

		if (is_bool($forcedConsent))
		{
			return $forcedConsent;
		}

		// Has the user provided consent?
		return $this->hasUserConsent($user);
	}

	/**
	 * Get the forced consent state for a user
	 *
	 * @param   User|null  $user  The user to check for forced consent.
	 *
	 * @return  bool|null  Boolean if there is forced consent/non-consent, NULL if the user's option is to be respected.
	 * @since   2.0.0
	 */
	protected function getForcedConsent(?User $user): ?bool
	{
		// Invalid user records are considered non-consenting
		if (empty($user) || empty($user->id))
		{
			return false;
		}

		// What's the user mode?
		$userMode = $this->params->get('user_mode', 'consent');

		// All users are forcibly allowed?
		if ($userMode === 'all')
		{
			return true;
		}

		// All users are forcibly forbidden?
		if ($userMode === 'none')
		{
			return false;
		}

		// Parse force-allow and force-forbid user groups
		$forceAllowGroups    = $this->params->get('allow_groups', []) ?: [];
		$forceAllowGroups    = is_array($forceAllowGroups) ? $forceAllowGroups : [];
		$forceDisallowGroups = $this->params->get('disallow_groups', []) ?: [];
		$forceDisallowGroups = is_array($forceDisallowGroups) ? $forceDisallowGroups : [];

		// -- Forced disallow groups are parsed first: Deny always trumps Allow in Joomla.
		if (array_intersect($user->getAuthorisedGroups(), $forceDisallowGroups))
		{
			return false;
		}

		// -- Check for forced allow groups
		if (array_intersect($user->getAuthorisedGroups(), $forceAllowGroups))
		{
			return true;
		}

		// No forced consent has been detected
		return null;
	}

	/**
	 * Has the user explicitly consented to being listed in the WebFinger directory?
	 *
	 * @param   User|null  $user
	 *
	 * @return  bool
	 * @since   2.0.0
	 */
	protected function hasUserConsent(?User $user): bool
	{
		// I need a real user to check for consent
		if (empty($user) || empty($user->id))
		{
			return false;
		}

		/** @var DatabaseDriver $db */
		$db     = $this->getDatabase();
		$userId = $user->id;
		$query  = $db->getQuery(true)
			->select($db->quoteName('profile_value'))
			->from($db->quoteName('#__user_profiles'))
			->where([
				$db->quoteName('user_id') . '= :userid',
				$db->quoteName('profile_key') . ' = ' . $db->quote('webfinger.consent'),
			])
			->bind(':userid', $userId, ParameterType::INTEGER);

		try
		{
			return ($db->setQuery($query)->loadResult() ?: 0) == 1;
		}
		catch (Exception $e)
		{
			return false;
		}
	}

	/**
	 * Checks if a relation is one of the requested relations.
	 *
	 * @param   string  $rel                 The relation to check
	 * @param   array   $requestedRelations  The requested relations. Empty means all relations.
	 *
	 * @return  bool
	 * @since   2.0.0
	 */
	protected function isRel(string $rel, array $requestedRelations): bool
	{
		if (empty($requestedRelations))
		{
			return true;
		}

		if (in_array($rel, $requestedRelations))
		{
			return true;
		}

		$rel                = strtolower($rel);
		$requestedRelations = array_map('strtolower', $requestedRelations);

		return in_array($rel, $requestedRelations);
	}
}