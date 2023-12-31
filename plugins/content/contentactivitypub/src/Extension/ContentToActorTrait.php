<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2024 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Plugin\Content\ContentActivityPub\Extension;

\defined('_JEXEC') || die;

use Dionysopoulos\Component\ActivityPub\Administrator\Table\ActorTable;
use Exception;
use Joomla\CMS\MVC\Factory\MVCFactory;
use Joomla\Component\Content\Administrator\Table\ArticleTable;
use Joomla\Database\DatabaseIterator;
use Joomla\Registry\Registry;

trait ContentToActorTrait
{
	/**
	 * Get a list of the applicable actors for this piece of content.
	 *
	 * There are two methods we try:
	 *
	 * * **Using the MySQL JSON extensions**. This is lightning fast, but relies on a database feature which might not
	 *   be enabled on most commercial hosts.
	 * * **Using plain old PHP to inspect the JSON**. This is excruciatingly slow, but does not rely on any database
	 *   features Joomla itself does not use.
	 *
	 * Note that either way only actors who have a non-zero follow count are queried for performance reasons.
	 *
	 * @param   ArticleTable  $article  The article to filter for
	 *
	 * @return  ActorTable[]
	 * @throws  Exception
	 * @since   2.0.0
	 */
	private function getActorsForContent(ArticleTable $article): array
	{
		return $this->getActorsForContentUsingMySQLJSON($article)
			?? $this->getActorsForContentUsingPHP($article)
			?? [];
	}

	/**
	 * Get a list of applicable actors using the MySQL JSON extensions.
	 *
	 * This is **very** fast, but it's not always available on commercial hosts.
	 *
	 * Note that only actors who have a non-zero follow count are queried for performance reasons.
	 *
	 * @param   ArticleTable  $article  The article to filter for
	 *
	 * @return  ActorTable[]|null
	 * @throws  Exception
	 * @since   2.0.0
	 */
	private function getActorsForContentUsingMySQLJSON(ArticleTable $article): ?array
	{
		$db = $this->getDatabase();

		$existsQuery = $db->getQuery(true)
			->select('1')
			->from($db->quoteName('#__activitypub_followers', 'f'))
			->where(
				$db->quoteName('f.actor_id') . ' = ' . $db->quoteName('a.id')
			);

		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__activitypub_actors', 'a'))
			->where([
				'EXISTS(' . $existsQuery . ')',
				'JSON_EXTRACT(' . $db->quoteName('params') . ', \'$.content.enable\') = 1',
				'JSON_CONTAINS(' . $db->quoteName('params') . ', \'["' . (int) $article->catid . '"]\', \'$.content.categories\')',
				'JSON_CONTAINS(' . $db->quoteName('params') . ', \'["' . (int) $article->access . '"]\', \'$.content.accesslevel\')',
			]);
		try
		{
			$actors = $db->setQuery($query)->loadObjectList('id');
		}
		catch (Exception $e)
		{
			return null;
		}

		/** @var MVCFactory $mvcFactory */
		$mvcFactory = $this->getApplication()
			->bootComponent('com_activitypub')
			->getMVCFactory();

		return array_map(
			function ($rawActor) use ($mvcFactory) {
				$table = $mvcFactory->createTable('Actor', 'Administrator');
				$table->bind((array) $rawActor);

				return $table;
			},
			$actors
		);
	}

	/**
	 * Get a list of the applicable actors using plain old PHP.
	 *
	 * This is excruciatingly slow. It is only used as a fallback, in case the MySQL JSON extensions are not enabled on
	 * the server.
	 *
	 * Note that only actors who have a non-zero follow count are queried for performance reasons.
	 *
	 * @param   ArticleTable  $article  The article to filter for
	 *
	 * @return  ActorTable[]|null
	 * @throws  Exception
	 * @since   2.0.0
	 */
	private function getActorsForContentUsingPHP(ArticleTable $article): ?array
	{
		$db = $this->getDatabase();

		$existsQuery = $db->getQuery(true)
			->select('1')
			->from($db->quoteName('#__activitypub_followers', 'f'))
			->where(
				$db->quoteName('f.actor_id') . ' = ' . $db->quoteName('a.id')
			);

		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__activitypub_actors', 'a'))
			->where([
				'EXISTS(' . $existsQuery . ')',
			]);

		try
		{
			/** @var DatabaseIterator $iterator */
			$iterator = $db->setQuery($query)->getIterator();
		}
		catch (Exception $e)
		{
			return null;
		}

		if (count($iterator) === 0)
		{
			$iterator = null;

			return [];
		}

		/** @var MVCFactory $mvcFactory */
		$mvcFactory = $this->getApplication()
			->bootComponent('com_activitypub')
			->getMVCFactory();
		$results    = [];

		foreach ($iterator as $actorData)
		{
			$params      = new Registry($actorData->params);
			$categories  = $params->get('content.categories', []);
			$accessLevel = $params->get('content.accesslevel', [1, 5]);

			if ($params->get('content.enable', 1) == 0 || empty($categories) || empty($accessLevel))
			{
				continue;
			}

			if (!in_array($article->catid, $categories) || !in_array($article->access, $accessLevel))
			{
				continue;
			}

			$actorTable = $mvcFactory->createTable('Actor', 'Administrator');
			$actorTable->bind((array) $actorData);
			$results[] = $actorTable;
		}

		return $results;
	}

}