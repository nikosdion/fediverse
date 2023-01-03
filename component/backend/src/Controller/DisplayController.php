<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2022-2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Component\ActivityPub\Administrator\Controller;

defined('_JEXEC') || die;

use Doctrine\Inflector\InflectorFactory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

class DisplayController extends BaseController
{
	/**
	 * The default view.
	 *
	 * @var    string
	 * @since  2.0.0
	 */
	protected $default_view = 'actors';

	/**
	 * Default MVC display method.
	 *
	 * @param   boolean  $cachable   If true, the view output will be cached
	 * @param   array    $urlparams  An array of safe url parameters and their variable types, for valid values see
	 *                               {@link InputFilter::clean()}.
	 *
	 * @return  bool|DisplayController A DisplayController object to support chaining.
	 *
	 * @throws  \Exception
	 * @since   2.0.0
	 */
	public function display($cachable = false, $urlparams = []): bool|DisplayController
	{
		$inflector    = InflectorFactory::create()->build();
		$view         = $this->input->get('view', 'banners');
		$singularView = $inflector->singularize($view);
		$pluralView   = $inflector->pluralize($view);
		$layout       = $this->input->get('layout', 'default');
		$id           = $this->input->getInt('id');

		// Are we in an edit layout? If so, make sure the ID was held first (we didn't access the edit layout directly).
		if (($view === $singularView) && $layout == 'edit' && !$this->checkEditId('com_activitypub.edit.' . $view, $id))
		{
			// Somehow the person just went to the form - we don't allow that.
			if (!count($this->app->getMessageQueue()))
			{
				$this->setMessage(Text::sprintf('JLIB_APPLICATION_ERROR_UNHELD_ID', $id), 'error');
			}

			$this->setRedirect(Route::_('index.php?option=com_activitypub&view=' . $pluralView, false));

			return false;
		}

		return parent::display($cachable, $urlparams);
	}
}