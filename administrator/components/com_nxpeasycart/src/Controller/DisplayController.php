<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;

/**
 * Default display controller for the admin application.
 *
 * @since 0.1.5
 */
class DisplayController extends BaseController
{
    /**
     * Default view for the component backend.
     *
     * @var string
     *
     * @since 0.1.5
     */
    protected $default_view = 'app';

    /**
     * {@inheritDoc}
     *
     * @since 0.1.5
     */
    public function display($cachable = false, $urlparams = [])
    {
        $requestedView = $this->input->getCmd('view', $this->default_view);
        $section       = $requestedView ?: $this->default_view;

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
