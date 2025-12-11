<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Site\View\Cart;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Component\Nxpeasycart\Site\Helper\SiteAssetHelper;
use Joomla\Component\Nxpeasycart\Site\Service\TemplateAdapter;

/**
 * Cart view for storefront.
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
    protected array $theme = [];

    /**
     * @var array<string, mixed>
     *
     * @since 0.1.5
     */
    protected array $cart = [
        'items'   => [],
        'summary' => [],
    ];

    public function display($tpl = null): void
    {
        $document = $this->getDocument();
        $app       = \Joomla\CMS\Factory::getApplication();

        // Prevent indexing of cart view.
        $document->setMetaData('robots', 'noindex, nofollow');
        $app->setHeader('X-Robots-Tag', 'noindex, nofollow', true);

        SiteAssetHelper::useSiteAssets($document);

        $model      = $this->getModel();
        $this->cart = $model ? $model->getCart() : ['items' => [], 'summary' => []];
        $this->theme = TemplateAdapter::resolve();

        $document->setTitle(Text::_('COM_NXPEASYCART_CART_TITLE'));

        parent::display($tpl);
    }
}
