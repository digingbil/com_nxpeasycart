<?php

namespace Joomla\Component\Nxpeasycart\Site\View\Category;

\defined('_JEXEC') or die;

/**
 * JSON view proxy that reuses the HTML view logic for format=json responses.
 */
class JsonView extends HtmlView
{
    /**
     * {@inheritDoc}
     */
    public function display($tpl = null): void
    {
        parent::display($tpl);
    }
}
