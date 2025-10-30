<?php

namespace Nxp\EasyCart\Admin\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;

/**
 * Default display controller for the admin application.
 */
class DisplayController extends BaseController
{
    /**
     * Default view for the component backend.
     *
     * @var string
     */
    protected $default_view = 'app';

    /**
     * {@inheritDoc}
     */
    public function display($cachable = false, $urlparams = [])
    {
        $requestedView = $this->input->getCmd('view', $this->default_view);
        $section = $requestedView ?: $this->default_view;

        if ($section === 'app') {
            $section = $this->input->getCmd('screen', 'dashboard');
        }

        if ($requestedView !== 'app') {
            $this->input->set('view', 'app');
        }

        $this->input->set('appSection', $section);

        return parent::display($cachable, $urlparams);
    }
}
