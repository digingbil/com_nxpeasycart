<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Site\View\Order;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Component\Nxpeasycart\Site\Helper\SessionSecurityHelper;
use Joomla\Component\Nxpeasycart\Site\Helper\SiteAssetHelper;
use Joomla\Component\Nxpeasycart\Site\Service\CartSessionService;

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

        // Clear the cart for the current session after a successful checkout redirect.
        if ($this->order && $app->input->getCmd('status') === 'success') {
            $this->clearCartSession();
        }

        if ($this->order) {
            $document->setTitle(Text::sprintf('COM_NXPEASYCART_ORDER_CONFIRMED_TITLE', $this->order['order_no'] ?? ''));
        } else {
            $document->setTitle(Text::_('COM_NXPEASYCART_ORDER_NOT_FOUND'));
        }

        parent::display($tpl);
    }

    /**
     * Clear the cart for the active session when landing on the success page.
     *
     * @since 0.1.5
     */
    private function clearCartSession(): void
    {
        $container = Factory::getContainer();
        $providerPath = JPATH_ADMINISTRATOR . '/components/com_nxpeasycart/services/provider.php';

        if (!$container->has(CartSessionService::class) && is_file($providerPath)) {
            $container->registerServiceProvider(require $providerPath);
        }

        if ($container->has(CartSessionService::class)) {
            try {
                $container->get(CartSessionService::class)->clear();
            } catch (\Throwable $exception) {
                // Non-fatal: leave cart untouched if clear fails.
            }
        }
    }
}
