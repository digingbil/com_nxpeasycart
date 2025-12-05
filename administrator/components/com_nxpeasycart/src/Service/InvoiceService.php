<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Service;

\defined('_JEXEC') or die;

use Dompdf\Dompdf;
use Dompdf\Options;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Uri\Uri;

/**
 * Generate PDF invoices for orders.
 *
 * @since 0.1.5
 */
class InvoiceService
{
    private const DEFAULT_LOCALE = 'en-GB';

    private SettingsService $settings;

    private PaymentGatewayService $payments;

    public function __construct(SettingsService $settings, PaymentGatewayService $payments)
    {
        $this->settings = $settings;
        $this->payments = $payments;
    }

    /**
     * Load component language files for the order's locale.
     *
     * Uses the locale stored on the order (captured at checkout) to ensure
     * invoices are rendered in the customer's language. Falls back to en-GB
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
     * Render the invoice HTML for an order.
     *
     * @param array<string, mixed> $order
     * @param array<string, mixed> $context
     *
     * @since 0.1.5
     */
    public function renderInvoiceHtml(array $order, array $context = []): string
    {
        // Load language strings for the order's locale (customer's language at checkout)
        $this->loadOrderLanguage($order);

        $store = $this->resolveStore($context['store'] ?? []);
        $payment = $this->resolvePaymentDetails($context['payment'] ?? []);

        return $this->renderTemplate('invoice/invoice', [
            'order'   => $order,
            'store'   => $store,
            'payment' => $payment,
        ]);
    }

    /**
     * Generate a PDF invoice for the given order.
     *
     * @param array<string, mixed> $order
     * @param array<string, mixed> $context
     *
     * @return array{filename: string, content: string, html?: string}
     *
     * @since 0.1.5
     */
    public function generateInvoice(array $order, array $context = []): array
    {
        $html = $this->renderInvoiceHtml($order, $context);
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'invoice-' . ($order['order_no'] ?? 'order') . '.pdf';

        return [
            'filename' => $filename,
            'content'  => $dompdf->output(),
            'html'     => $html,
        ];
    }

    /**
     * Resolve store context using saved settings with sensible fallbacks.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array{name: string, email: string, phone: string, url: string}
     *
     * @since 0.1.5
     */
    private function resolveStore(array $overrides = []): array
    {
        $settings = [];

        try {
            $settings = $this->settings->all();
        } catch (\Throwable $exception) {
            $settings = [];
        }

        $store = \is_array($settings['store'] ?? null) ? $settings['store'] : [];
        $app   = Factory::getApplication();
        $siteName = '';

        if (method_exists($app, 'getConfig') && $app->getConfig() !== null) {
            $siteName = (string) $app->getConfig()->get('sitename', '');
        }

        if ($siteName === '' && method_exists($app, 'get')) {
            $siteName = (string) $app->get('sitename', '');
        }

        return [
            'name'  => (string) ($overrides['name'] ?? ($store['name'] ?? $siteName)),
            'email' => (string) ($overrides['email'] ?? ($store['email'] ?? '')),
            'phone' => (string) ($overrides['phone'] ?? ($store['phone'] ?? '')),
            'url'   => (string) ($overrides['url'] ?? ($store['url'] ?? Uri::root())),
        ];
    }

    /**
     * Resolve bank transfer details for inclusion on invoices.
     *
     * @param array<string, mixed> $payment
     *
     * @return array<string, mixed>
     *
     * @since 0.1.5
     */
    private function resolvePaymentDetails(array $payment): array
    {
        $method  = (string) ($payment['method'] ?? '');
        $details = \is_array($payment['details'] ?? null) ? $payment['details'] : $payment;

        if ($method === 'bank_transfer' && empty($details)) {
            $details = $this->payments->getGatewayConfig('bank_transfer');
        }

        return [
            'method'       => $method !== '' ? $method : ($details ? 'bank_transfer' : ''),
            'label'        => $details['label']        ?? 'Bank transfer',
            'instructions' => $details['instructions'] ?? '',
            'account_name' => $details['account_name'] ?? '',
            'iban'         => $details['iban']         ?? '',
            'bic'          => $details['bic']          ?? '',
        ];
    }

    /**
     * Render a PHP template with the given context.
     *
     * @param array<string, mixed> $context
     *
     * @since 0.1.5
     */
    private function renderTemplate(string $name, array $context = []): string
    {
        $path = JPATH_ADMINISTRATOR . '/components/com_nxpeasycart/templates/' . $name . '.php';

        if (!is_file($path)) {
            return '';
        }

        extract($context, EXTR_SKIP);

        ob_start();
        include $path;

        return (string) ob_get_clean();
    }
}
