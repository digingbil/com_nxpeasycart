<?php

namespace Joomla\Component\Nxpeasycart\Site\View\Cart;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Component\Nxpeasycart\Site\Helper\SiteAssetHelper;

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
        $document = $this->getDocument();

        SiteAssetHelper::useSiteAssets($document);

        $model      = $this->getModel();
        $this->cart = $model ? $model->getCart() : ['items' => [], 'summary' => []];

        $document->setTitle(Text::_('COM_NXPEASYCART_CART_TITLE'));

        parent::display($tpl);
    }
}
