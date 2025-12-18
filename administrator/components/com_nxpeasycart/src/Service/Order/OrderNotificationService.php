<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Administrator\Service\Order;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Component\Nxpeasycart\Administrator\Helper\ConfigHelper;
use Joomla\Component\Nxpeasycart\Administrator\Service\AuditService;
use Joomla\Component\Nxpeasycart\Administrator\Service\DigitalFileService;
use Joomla\Component\Nxpeasycart\Administrator\Service\MailService;

/**
 * Order notification service.
 *
 * Handles email notifications for order state transitions and events.
 *
 * @since 0.3.2
 */
class OrderNotificationService
{
    private ?AuditService $audit = null;
    private ?DigitalFileService $digitalFileService = null;

    public function __construct(?AuditService $audit = null, ?DigitalFileService $digitalFileService = null)
    {
        $this->audit = $audit;
        $this->digitalFileService = $digitalFileService;
    }

    /**
     * Send appropriate email notification based on state transition.
     *
     * @since 0.1.5
     */
    public function sendStateTransitionEmail(array $order, string $fromState, string $toState): void
    {
        // Check if auto-send is enabled in settings
        if (!ConfigHelper::isAutoSendOrderEmails()) {
            return;
        }

        // Only send emails for specific state transitions
        if ($toState === 'fulfilled' && $fromState !== 'fulfilled') {
            $this->sendShippedEmail($order);
        } elseif ($toState === 'refunded' && $fromState !== 'refunded') {
            $this->sendRefundedEmail($order);
        }
    }

    /**
     * Send order shipped notification email.
     *
     * @since 0.1.5
     */
    public function sendShippedEmail(array $order): void
    {
        try {
            $mailService = $this->getMailService();

            if ($mailService === null) {
                return;
            }

            $mailService->sendOrderShipped($order, [
                'carrier'         => $order['carrier'] ?? null,
                'tracking_number' => $order['tracking_number'] ?? null,
                'tracking_url'    => $order['tracking_url'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // Log error but don't fail the state transition
            $this->getAuditService()->record(
                'order',
                (int) $order['id'],
                'order.email.failed',
                ['type' => 'shipped', 'error' => $e->getMessage()]
            );
        }
    }

    /**
     * Send order refunded notification email.
     *
     * @since 0.1.5
     */
    public function sendRefundedEmail(array $order): void
    {
        try {
            $mailService = $this->getMailService();

            if ($mailService === null) {
                return;
            }

            $mailService->sendOrderRefunded($order, [
                'amount_cents' => $order['total_cents'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            // Log error but don't fail the state transition
            $this->getAuditService()->record(
                'order',
                (int) $order['id'],
                'order.email.failed',
                ['type' => 'refunded', 'error' => $e->getMessage()]
            );
        }
    }

    /**
     * Send the "downloads ready" email for orders with digital items.
     *
     * Called when a manual payment is recorded and the order has digital items.
     * Ensures download links are populated before sending.
     *
     * @param array<string, mixed> $order
     *
     * @since 0.1.13
     */
    public function sendDownloadsReadyEmail(array $order): void
    {
        if (empty($order['has_digital'])) {
            return;
        }

        $mailService = $this->getMailService();

        if ($mailService === null) {
            return;
        }

        try {
            // Ensure downloads are populated with URLs
            if (empty($order['downloads']) && $this->digitalFileService !== null) {
                $orderId = (int) ($order['id'] ?? 0);

                if ($orderId > 0) {
                    $downloads = $this->digitalFileService->getDownloadsForOrder($orderId);

                    if (!empty($downloads)) {
                        $order['downloads'] = $downloads;
                    }
                }
            }

            $mailService->sendDownloadsReady($order);
        } catch (\Throwable $e) {
            // Log but don't fail the payment recording
            $this->getAuditService()->record(
                'order',
                (int) ($order['id'] ?? 0),
                'order.email.downloads_ready_failed',
                ['message' => $e->getMessage()]
            );
        }
    }

    /**
     * Resolve the MailService, ensuring the service provider is registered.
     *
     * @return MailService|null
     *
     * @since 0.1.9
     */
    private function getMailService(): ?MailService
    {
        $container = Factory::getContainer();

        // Ensure service provider is loaded if MailService isn't registered yet
        if (!$container->has(MailService::class)) {
            $providerPath = JPATH_ADMINISTRATOR . '/components/com_nxpeasycart/services/provider.php';

            if (is_file($providerPath)) {
                try {
                    $container->registerServiceProvider(require $providerPath);
                } catch (\Throwable $e) {
                    // Provider already registered or failed - continue
                }
            }
        }

        // Still not available? Try creating manually as fallback
        if (!$container->has(MailService::class)) {
            if (!$container->has(\Joomla\CMS\Mail\MailerFactoryInterface::class)) {
                return null;
            }

            try {
                $container->set(
                    MailService::class,
                    static fn ($container) => new MailService(
                        $container->get(\Joomla\CMS\Mail\MailerFactoryInterface::class)->createMailer()
                    )
                );
            } catch (\Throwable $e) {
                return null;
            }
        }

        try {
            return $container->get(MailService::class);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get the AuditService, creating it if needed.
     */
    private function getAuditService(): AuditService
    {
        if ($this->audit === null) {
            $container = Factory::getContainer();

            if (!$container->has(AuditService::class)) {
                $container->set(
                    AuditService::class,
                    static fn ($container) => new AuditService(
                        $container->get(\Joomla\Database\DatabaseInterface::class)
                    )
                );
            }

            $this->audit = $container->get(AuditService::class);
        }

        return $this->audit;
    }
}
