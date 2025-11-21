<?php

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
 */
class HtmlView extends BaseHtmlView
{
    /**
     * @var array<string, mixed>
     */
    protected array $checkout = [];

    /**
     * @var array<string, mixed>
     */
    protected array $theme = [];

    public function display($tpl = null): void
    {
        /** @var HtmlDocument $document */
        $document = $this->getDocument();

        SiteAssetHelper::useSiteAssets($document);

        $model          = $this->getModel();
        $this->checkout = $model ? $model->getCheckout() : [];
        $this->theme    = TemplateAdapter::resolve();

        $document->setTitle(Text::_('COM_NXPEASYCART_CHECKOUT_TITLE'));

        parent::display($tpl);
    }
}
