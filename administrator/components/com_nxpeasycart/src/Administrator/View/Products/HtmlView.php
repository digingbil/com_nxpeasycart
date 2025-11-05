<?php

namespace Joomla\Component\Nxpeasycart\Administrator\View\Products;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/**
 * Products listing view placeholder.
 */
class HtmlView extends BaseHtmlView
{
    /**
     * {@inheritDoc}
     */
    public function display($tpl = null): void
    {
        $this->getDocument()->setTitle(Text::_('COM_NXPEASYCART_MENU_PRODUCTS'));

        parent::display($tpl);
    }
}
