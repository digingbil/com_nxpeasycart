<?php

namespace Nxp\EasyCart\Admin\Administrator\View\Settings;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/**
 * Settings view placeholder.
 */
class HtmlView extends BaseHtmlView
{
    /**
     * {@inheritDoc}
     */
    public function display($tpl = null): void
    {
        $this->document->setTitle(Text::_('COM_NXPEASYCART_MENU_SETTINGS'));

        parent::display($tpl);
    }
}
