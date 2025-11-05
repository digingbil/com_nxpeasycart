<?php

namespace Joomla\Component\Nxpeasycart\Site\View\Checkout;

\defined('_JEXEC') or die;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

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

        $wa = $document->getWebAssetManager();
        $wa->registerAndUseStyle(
            'com_nxpeasycart.site.css',
            'media/com_nxpeasycart/css/site.css',
            ['version' => 'auto', 'relative' => true]
        );
        $wa->getRegistry()->addRegistryFile('media/com_nxpeasycart/joomla.asset.json');
        $wa->useScript('com_nxpeasycart.site');

        $model          = $this->getModel();
        $this->checkout = $model ? $model->getCheckout() : [];

        $document->setTitle(Text::_('COM_NXPEASYCART_CHECKOUT_TITLE'));

        parent::display($tpl);
    }
}
