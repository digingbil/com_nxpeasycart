<?php

namespace Nxp\EasyCart\Site\View\Checkout;

\defined('_JEXEC') or die;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Uri\Uri;

/**
 * Checkout view combining cart and configuration metadata.
 */
class HtmlView extends BaseHtmlView
{
    /**
     * @var array<string, mixed>
     */
    protected array $checkout = [];

    public function display($tpl = null): void
    {
        /** @var HtmlDocument $document */
        $document = $this->document;
        $document->addStyleSheet(Uri::root(true) . '/media/com_nxpeasycart/css/site.css');

        $wa = $document->getWebAssetManager();
        $wa->getRegistry()->addRegistryFile('media/com_nxpeasycart/joomla.asset.json');
        $wa->useScript('com_nxpeasycart.site');

        $model          = $this->getModel();
        $this->checkout = $model ? $model->getCheckout() : [];

        $document->setTitle(Text::_('COM_NXPEASYCART_CHECKOUT_TITLE'));

        parent::display($tpl);
    }
}
