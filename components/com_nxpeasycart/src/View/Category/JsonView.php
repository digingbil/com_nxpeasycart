<?php

namespace Joomla\Component\Nxpeasycart\Site\View\Category;

\defined('_JEXEC') or die;

/**
 * JSON view proxy that reuses the HTML view logic for format=json responses.
 *
 * @since 0.1.5
 */
class JsonView extends HtmlView
{
    /**
     * {@inheritDoc}
     *
     * @since 0.1.5
     */
    public function display($tpl = null): void
    {
        parent::display($tpl);
    }
}
