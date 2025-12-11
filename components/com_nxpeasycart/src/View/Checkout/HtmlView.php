<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Site\View\Checkout;

\defined('_JEXEC') or die;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Component\Nxpeasycart\Site\Helper\SiteAssetHelper;
use Joomla\Component\Nxpeasycart\Site\Service\TemplateAdapter;

/**
 * Checkout view combining cart and configuration metadata.
 *
 * @since 0.1.5
 */
class HtmlView extends BaseHtmlView
{
    /**
     * @var array<string, mixed>
     *
     * @since 0.1.5
     */
    protected array $checkout = [];

    /**
     * @var array<string, mixed>
     *
     * @since 0.1.5
     */
    protected array $theme = [];

    public function display($tpl = null): void
    {
        /** @var HtmlDocument $document */
        $document = $this->getDocument();
        $app       = Factory::getApplication();

        // Prevent indexing of checkout view.
        $document->setMetaData('robots', 'noindex, nofollow');
        $app->setHeader('X-Robots-Tag', 'noindex, nofollow', true);

        SiteAssetHelper::useSiteAssets($document);

        $model          = $this->getModel();
        $this->checkout = $model ? $model->getCheckout() : [];
        $this->theme    = TemplateAdapter::resolve();

        $document->setTitle(Text::_('COM_NXPEASYCART_CHECKOUT_TITLE'));

        parent::display($tpl);
    }
}
