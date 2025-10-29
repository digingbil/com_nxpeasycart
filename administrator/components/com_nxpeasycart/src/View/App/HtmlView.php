<?php

namespace Nxp\EasyCart\Admin\View\App;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/**
 * Basic HTML view for the admin dashboard wrapper.
 */
class HtmlView extends BaseHtmlView
{
    /**
     * View display method.
     *
     * @param string|null $tpl Template file to use
     *
     * @return void
     */
    public function display($tpl = null): void
    {
        $this->document->setTitle(Text::_('COM_NXPEASYCART'));

        parent::display($tpl);
    }
}
