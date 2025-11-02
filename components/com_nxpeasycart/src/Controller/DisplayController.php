<?php

namespace Joomla\Component\Nxpeasycart\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Component\Nxpeasycart\Site\Service\CartSessionService;

/**
 * Default site controller for routing storefront views.
 */
class DisplayController extends BaseController
{
    /**
     * Default view name.
     *
     * @var string
     */
    protected $default_view = 'category';

    /**
     * {@inheritDoc}
     */
    public function display($cachable = false, $urlparams = [])
    {
        try {
            Factory::getContainer()
                ->get(CartSessionService::class)
                ->attachToApplication();
        } catch (\Throwable $exception) {
            // Cart bootstrap failures should not block the storefront; swallow and continue.
        }

        return parent::display($cachable, $urlparams);
    }
}
