<?php

namespace Joomla\Component\Nxpeasycart\Site\View\Order;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Component\Nxpeasycart\Site\Helper\SessionSecurityHelper;
use Joomla\Component\Nxpeasycart\Site\Helper\SiteAssetHelper;

/**
 * Order confirmation view.
 */
class HtmlView extends BaseHtmlView
{
    /**
     * @var array<string, mixed>|null
     */
    protected ?array $order = null;

    public function display($tpl = null): void
    {
        // SECURITY: Regenerate session ID on order confirmation page
        // This prevents session fixation attacks when returning from
        // external payment gateways (Stripe, PayPal) or after checkout
        SessionSecurityHelper::regenerateIfNeeded();

        $document = $this->getDocument();

        SiteAssetHelper::useSiteAssets($document);

        $model       = $this->getModel();
        $this->order = $model ? $model->getItem() : null;

        if ($this->order) {
            $document->setTitle(Text::sprintf('COM_NXPEASYCART_ORDER_CONFIRMED_TITLE', $this->order['order_no'] ?? ''));
        } else {
            $document->setTitle(Text::_('COM_NXPEASYCART_ORDER_NOT_FOUND'));
        }

        parent::display($tpl);
    }
}
