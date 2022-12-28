<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Api\View\Outbox;

\defined('_JEXEC') || die;

use ActivityPhp\Type;
use Dionysopoulos\Component\ActivityPub\Administrator\Mixin\GetActorTrait;
use Dionysopoulos\Component\ActivityPub\Api\Model\OutboxModel;
use Joomla\CMS\Document\JsonDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\JsonView as BaseJsonView;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;

class Jsonview extends BaseJsonView implements DatabaseAwareInterface
{
	use DatabaseAwareTrait;
	use GetActorTrait;

	/**
	 * The active document object (Redeclared for typehinting)
	 *
	 * @var    JsonDocument
	 * @since  2.0.0
	 */
	public $document;

	/**
	 * The content type
	 *
	 * @var  string
	 * @since  2.0.0
	 */
	protected string $type;

	public function __construct($config = [])
	{
		parent::__construct($config);

		$this->setDatabase(Factory::getContainer()->get('DatabaseDriver'));
	}

	/**
	 * Execute and display a template script.
	 *
	 * @return  void
	 *
	 * @since   2.0.0
	 */
	public function displayList()
	{
		// Set the correct MIME encoding and charset for the result
		$this->document->setMimeEncoding('application/activity+json');
		$this->document->setCharset('utf-8');

		/** @var OutboxModel $model */
		$model         = $this->getModel();
		$username      = $model->getState('filter.username');
		$hasPagination = $model->getState('list.paginate', true);
		$pagination    = $model->getPagination();
		$totalItems    = $model->getTotal();

		$user = $this->getUserFromUsername($username);

		$outboxUrl = $this->getApiUriForUser($user, 'outbox');
		$outboxUri = new Uri($outboxUrl);
		$outboxUri->setVar('page', 'true');

		$firstPage = $outboxUri->toString();

		if ($pagination->pagesTotal === 1)
		{
			$lastPage = $firstPage;
		}
		else
		{
			$outboxUri->setVar('offset', ($pagination->pagesTotal - 1) * $pagination->limit);
			$lastPage = $outboxUri->toString();
		}

		if (!$hasPagination)
		{
			$attributes = [
				'@context'   => 'https://www.w3.org/ns/activitystreams',
				'id'         => $outboxUrl,
				'totalItems' => $totalItems,
				'first'      => $firstPage,
				'last'       => $lastPage,
			];

			echo Type::create('OrderedCollection', $attributes)
				->toJson();

			return;
		}

		$attributes = [
			'@context'     => [
				'https://www.w3.org/ns/activitystreams',
				[
					"ostatus"          => "http://ostatus.org#",
					"atomUri"          => "ostatus:atomUri",
					"inReplyToAtomUri" => "ostatus:inReplyToAtomUri",
					'sensitive'        => 'as:sensitive',
					'toot'             => 'http://joinmastodon.org/ns#',
					'blurhash'         => 'toot:blurhash',
				],
			],
			'id'           => Uri::current(),
			'partOf'       => $outboxUrl,
			'orderedItems' => $model->getItems(),
			'first'        => $firstPage,
			'last'         => $lastPage,
		];

		if ($pagination->pagesCurrent > 1 && $pagination->pagesTotal > 1)
		{
			$outboxUri->setVar('offset', ($pagination->pagesCurrent - 2) * $pagination->limit);
			$attributes['prev'] = $outboxUri->toString();
		}

		if ($pagination->pagesCurrent < $pagination->pagesTotal)
		{
			$outboxUri->setVar('offset', $pagination->pagesCurrent * $pagination->limit);
			$attributes['next'] = $outboxUri->toString();
		}

		$collection = Type::create('OrderedCollection', $attributes);

		echo Type::create('OrderedCollection', $attributes)
			->toJson();
	}
}