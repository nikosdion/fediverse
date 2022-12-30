<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Api\View\Object;

defined('_JEXEC') || die;

use Dionysopoulos\Component\ActivityPub\Api\Model\ActorModel;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\JsonView as BaseJsonView;

class JsonView extends BaseJsonView
{
	/**
	 * Display the Activity item
	 *
	 * @param   string|null  $tpl
	 *
	 * @return  void
	 * @throws  \Exception
	 * @since   2.0.0
	 */
	public function displayItem(?string $tpl = null): void
	{
		// Set the correct MIME encoding and charset for the result
		$this->document->setMimeEncoding('application/activity+json');
		$this->document->setCharset('utf-8');

		/** @var ActorModel $model */
		$model = $this->getModel();
		$activity = $model->getItem();

		if (empty($activity))
		{
			throw new \RuntimeException(Text::_('COM_ACTIVITYPUB_OBJECT_ERR_NOT_FOUND'), 404);
		}

		echo $activity->toJson();
	}
}