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
 *
 * @since 0.1.5
 */
class HtmlView extends BaseHtmlView
{
    /**
     * @var array<string, mixed>|null
     *
     * @since 0.1.5
     */
    protected ?array $order = null;
    protected bool $isPublic = false;
    protected bool $isOwner  = false;

    public function display($tpl = null): void
    {
        // SECURITY: Regenerate session ID on order confirmation page
        // This prevents session fixation attacks when returning from
        // external payment gateways (Stripe, PayPal) or after checkout
        SessionSecurityHelper::regenerateIfNeeded();

        $document = $this->getDocument();
        $app       = Factory::getApplication();

        // Prevent indexing of order confirmation/status pages.
        $document->setMetaData('robots', 'noindex, nofollow');
        $app->setHeader('X-Robots-Tag', 'noindex, nofollow', true);

        SiteAssetHelper::useSiteAssets($document);

        $model       = $this->getModel();
        $this->order = $model ? $model->getItem() : null;
        $this->isPublic = $model && method_exists($model, 'isPublicView') ? (bool) $model->isPublicView() : false;
        $this->isOwner  = $model && method_exists($model, 'isOwnerView') ? (bool) $model->isOwnerView() : false;

        if ($this->order) {
            $document->setTitle(Text::sprintf('COM_NXPEASYCART_ORDER_CONFIRMED_TITLE', $this->order['order_no'] ?? ''));
        } else {
            $document->setTitle(Text::_('COM_NXPEASYCART_ORDER_NOT_FOUND'));
        }

        parent::display($tpl);
    }
}
