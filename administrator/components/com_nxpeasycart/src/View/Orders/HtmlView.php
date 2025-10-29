<?php

namespace Nxp\EasyCart\Admin\View\Orders;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

\defined('_JEXEC') or die;

/**
 * Orders placeholder view.
 */
class HtmlView extends BaseHtmlView
{
    /**
     * Render the view.
     *
     * @param string|null $tpl Template name.
     *
     * @return void
     */
    public function display($tpl = null): void
    {
        $this->document->setTitle(Text::_('COM_NXPEASYCART_MENU_ORDERS'));

        parent::display($tpl);
    }
}
