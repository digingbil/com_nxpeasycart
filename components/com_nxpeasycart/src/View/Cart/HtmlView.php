<?php

namespace Joomla\Component\Nxpeasycart\Site\View\Cart;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/**
 * Cart view for storefront.
 */
class HtmlView extends BaseHtmlView
{
    /**
     * @var array<string, mixed>
     */
    protected array $cart = [
        'items'   => [],
        'summary' => [],
    ];

    public function display($tpl = null): void
    {
        $document = $this->document;

        $wa = $document->getWebAssetManager();
        $wa->registerAndUseStyle(
            'com_nxpeasycart.site.css',
            'media/com_nxpeasycart/css/site.css',
            ['version' => 'auto', 'relative' => true]
        );
        $wa->getRegistry()->addRegistryFile('media/com_nxpeasycart/joomla.asset.json');
        $wa->useScript('com_nxpeasycart.site');

        $model      = $this->getModel();
        $this->cart = $model ? $model->getCart() : ['items' => [], 'summary' => []];

        $document->setTitle(Text::_('COM_NXPEASYCART_CART_TITLE'));

        parent::display($tpl);
    }
}
