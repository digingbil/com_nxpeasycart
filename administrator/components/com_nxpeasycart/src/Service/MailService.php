<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Mail\MailerInterface;
use Joomla\CMS\Uri\Uri;
use RuntimeException;

/**
 * Outgoing email helper for transactional mail.
 *
 * @since 0.1.5
 */
class MailService
{
    private const DEFAULT_LOCALE = 'en-GB';

    private MailerInterface $mailer;

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     * Load component language files for the order's locale.
     *
     * Uses the locale stored on the order (captured at checkout) to ensure
     * emails are rendered in the customer's language. Falls back to en-GB
     * if the order locale is missing or the language files don't exist.
     *
     * This method creates a new Language instance for the target locale to ensure
     * we get fresh translations rather than the admin's current language.
     *
     * @param array<string, mixed> $order The order data containing 'locale' key
     *
     * @since 0.1.11
     */
    private function loadOrderLanguage(array $order): void
    {
        $orderLocale = isset($order['locale']) && trim((string) $order['locale']) !== ''
            ? trim((string) $order['locale'])
            : self::DEFAULT_LOCALE;

        // Get a fresh language instance for the target locale
        $language = Language::getInstance($orderLocale);

        // Load component language files into this language instance
        $language->load('com_nxpeasycart', JPATH_SITE, $orderLocale, true, true);
        $language->load('com_nxpeasycart', JPATH_ADMINISTRATOR, $orderLocale, true, true);

        // Replace the application's language instance so Text::_() uses our locale
        Factory::$language = $language;
    }

    /**
     * Send an order confirmation email.
     *
     * @param array<string, mixed> $order
     *
     * @since 0.1.5
     */
    public function sendOrderConfirmation(array $order, array $options = []): void
    {
        $recipient = $order['email'] ?? '';

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        // Load language strings for the order's locale (customer's language at checkout)
        $this->loadOrderLanguage($order);

        $payment     = isset($options['payment']) && \is_array($options['payment']) ? $options['payment'] : [];
        $attachments = isset($options['attachments']) && \is_array($options['attachments'])
            ? $options['attachments']
            : [];

        $store   = $this->getStoreContext();
        $subject = Text::sprintf('COM_NXPEASYCART_EMAIL_ORDER_SUBJECT', $order['order_no'] ?? '');
        $body    = $this->renderTemplate('order_confirmation', [
            'order'   => $order,
            'store'   => $store,
            'payment' => $payment,
        ]);

        $this->sendMail($recipient, $subject, $body, $attachments);
    }

    /**
     * Send an order shipped notification email.
     *
     * Triggered when: order transitions to 'fulfilled' state AND tracking info is provided.
     *
     * @param array<string, mixed> $order
     * @param array<string, mixed> $options Tracking info: carrier, tracking_number, tracking_url
     *
     * @since 0.1.5
     */
    public function sendOrderShipped(array $order, array $options = []): void
    {
        $recipient = $order['email'] ?? '';

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        // Load language strings for the order's locale (customer's language at checkout)
        $this->loadOrderLanguage($order);

        $tracking = [
            'carrier'         => $options['carrier'] ?? ($order['carrier'] ?? ''),
            'tracking_number' => $options['tracking_number'] ?? ($order['tracking_number'] ?? ''),
            'tracking_url'    => $options['tracking_url'] ?? ($order['tracking_url'] ?? ''),
        ];

        $store   = $this->getStoreContext();
        $subject = Text::sprintf('COM_NXPEASYCART_EMAIL_ORDER_SHIPPED_SUBJECT', $order['order_no'] ?? '');
        $body    = $this->renderTemplate('order_shipped', [
            'order'    => $order,
            'store'    => $store,
            'tracking' => $tracking,
        ]);

        $this->sendMail($recipient, $subject, $body);
    }

    /**
     * Send an order refunded notification email.
     *
     * Triggered when: order transitions to 'refunded' state OR a refund transaction is recorded.
     *
     * @param array<string, mixed> $order
     * @param array<string, mixed> $options Refund info: amount_cents, reason
     *
     * @since 0.1.5
     */
    public function sendOrderRefunded(array $order, array $options = []): void
    {
        $recipient = $order['email'] ?? '';

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        // Load language strings for the order's locale (customer's language at checkout)
        $this->loadOrderLanguage($order);

        $refund = [
            'amount_cents' => $options['amount_cents'] ?? ($order['total_cents'] ?? 0),
            'reason'       => $options['reason'] ?? '',
        ];

        $store   = $this->getStoreContext();
        $subject = Text::sprintf('COM_NXPEASYCART_EMAIL_ORDER_REFUNDED_SUBJECT', $order['order_no'] ?? '');
        $body    = $this->renderTemplate('order_refunded', [
            'order'  => $order,
            'store'  => $store,
            'refund' => $refund,
        ]);

        $this->sendMail($recipient, $subject, $body);
    }

    /**
     * Get store context for email templates.
     *
     * @since 0.1.5
     */
    private function getStoreContext(): array
    {
        $app    = Factory::getApplication();
        $config = method_exists($app, 'getConfig') ? $app->getConfig() : null;

        $siteName = $config ? (string) $config->get('sitename') : '';

        if ($siteName === '' && method_exists($app, 'get')) {
            $siteName = (string) $app->get('sitename', '');
        }

        return [
            'name' => $siteName !== '' ? $siteName : 'Your Store',
            'url'  => Uri::root(),
        ];
    }

    /**
     * Send an email with optional attachments.
     *
     * @since 0.1.5
     */
    private function sendMail(string $recipient, string $subject, string $body, array $attachments = []): void
    {
        $mailer = clone $this->mailer; // Avoid mutating the shared mailer instance.
        $mailer->setSubject($subject);
        $mailer->isHtml(true);
        $mailer->setBody($body);
        $mailer->addRecipient($recipient);

        foreach ($attachments as $attachment) {
            $data = (string) ($attachment['content'] ?? $attachment['data'] ?? '');

            if ($data === '') {
                continue;
            }

            $name = isset($attachment['name']) ? (string) $attachment['name'] : 'attachment.bin';
            $type = isset($attachment['type']) ? (string) $attachment['type'] : 'application/octet-stream';

            $path = $this->persistAttachment($data, $name);

            if ($path !== null) {
                $mailer->addAttachment($path, $name, 'base64', $type);
            }
        }

        $mailer->send();
    }

    /**
     * Render a blade-style PHP template.
     *
     * @param array<string, mixed> $context
     *
     * @since 0.1.5
     */
    private function renderTemplate(string $name, array $context = []): string
    {
        $path = JPATH_ADMINISTRATOR . '/components/com_nxpeasycart/templates/email/' . $name . '.php';

        if (!is_file($path)) {
            throw new RuntimeException('Email template not found: ' . $name);
        }

        extract($context, EXTR_SKIP);

        ob_start();
        include $path;

        return (string) ob_get_clean();
    }

    /**
     * Write attachment contents to a temporary file for the mailer to consume.
     *
     * @since 0.1.5
     */
    private function persistAttachment(string $contents, string $name): ?string
    {
        $app       = Factory::getApplication();
        $config    = method_exists($app, 'getConfig') ? $app->getConfig() : null;
        $configTmp = $config ? $config->get('tmp_path') : null;
        $tmpDir    = \is_string($configTmp) && $configTmp !== '' ? $configTmp : sys_get_temp_dir();

        $tmp = tempnam($tmpDir, 'nxp-ec-attach-');

        if ($tmp === false) {
            return null;
        }

        $extension = '';
        $dotPos    = strrpos($name, '.');

        if ($dotPos !== false && $dotPos < strlen($name) - 1) {
            $extension = substr($name, $dotPos);
        }

        $final = $tmp;

        if ($extension !== '') {
            $final = $tmp . $extension;
            @rename($tmp, $final);
        }

        if (@file_put_contents($final, $contents) === false) {
            return null;
        }

        register_shutdown_function(static function () use ($final): void {
            if (is_file($final)) {
                @unlink($final);
            }
        });

        return $final;
    }
}
