<?php

namespace Nxp\EasyCart\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;
use Nxp\EasyCart\Site\Service\CartSessionService;

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
    protected $default_view = 'product';

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
