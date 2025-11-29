<?php

use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

/** @var array<string, mixed> $order */
/** @var array<string, mixed> $store */
/** @var array<string, mixed> $payment */

$payment = isset($payment) && \is_array($payment) ? $payment : [];
$store   = isset($store) && \is_array($store) ? $store : [];

$items    = $order['items']    ?? [];
$currency = $order['currency'] ?? 'USD';

$formatMoney = static function (int $cents) use ($currency): string {
    $amount = $cents / 100;

    if (class_exists('NumberFormatter', false)) {
        try {
            $locale = locale_get_default() ?: 'en_US';
            $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
            $formatted = $formatter->formatCurrency($amount, $currency);

            if ($formatted !== false) {
                return $formatted;
            }
        } catch (\Throwable $exception) {
            // Fall through to simple format
        }
    }

    return sprintf('%s %.2f', $currency, $amount);
};

$billingLines = [];

if (!empty($order['billing'])) {
    $billing = $order['billing'];
    $fullName = trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? ''));

    if ($fullName !== '') {
        $billingLines[] = $fullName;
    }

    foreach (['address_line1', 'address_line2'] as $field) {
        if (!empty($billing[$field])) {
            $billingLines[] = (string) $billing[$field];
        }
    }

    $cityParts = [];

    foreach (['city', 'region', 'postcode'] as $field) {
        if (!empty($billing[$field])) {
            $cityParts[] = (string) $billing[$field];
        }
    }

    if ($cityParts) {
        $billingLines[] = implode(', ', $cityParts);
    }

    if (!empty($billing['country'])) {
        $billingLines[] = (string) $billing['country'];
    }
}

$paymentMethod = $payment['method'] ?? '';
$isBankTransfer = $paymentMethod === 'bank_transfer';

$orderDate = '';

if (!empty($order['created'])) {
    $timestamp = strtotime((string) $order['created']);
    $orderDate = $timestamp ? date('Y-m-d', $timestamp) : '';
}
?>
<!doctype html>
<html>
    <head>
        <meta charset="utf-8">
        <title><?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_INVOICE_TITLE'), ENT_QUOTES, 'UTF-8'); ?></title>
        <style>
            body {
                font-family: "DejaVu Sans", Arial, sans-serif;
                color: #1f2937;
                font-size: 12px;
                margin: 0;
                padding: 24px;
            }
            h1, h2, h3 {
                margin: 0 0 8px;
                color: #0f172a;
            }
            .nxp-ec-invoice__header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 20px;
                border-bottom: 1px solid #e5e7eb;
                padding-bottom: 12px;
            }
            .nxp-ec-invoice__meta {
                text-align: right;
                font-size: 12px;
                color: #4b5563;
            }
            .nxp-ec-invoice__grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 16px;
                margin-bottom: 20px;
            }
            .nxp-ec-invoice__panel {
                padding: 12px;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                background: #f8fafc;
            }
            table {
                width: 100%;
                border-collapse: collapse;
            }
            th, td {
                padding: 8px 6px;
            }
            th {
                border-bottom: 1px solid #e5e7eb;
                text-align: left;
                font-size: 12px;
                color: #6b7280;
                text-transform: uppercase;
                letter-spacing: 0.02em;
            }
            td {
                border-bottom: 1px solid #f1f5f9;
                font-size: 12px;
            }
            .nxp-ec-invoice__totals td {
                font-weight: bold;
                border-bottom: none;
            }
            .nxp-ec-invoice__note {
                margin-top: 12px;
                padding: 10px 12px;
                border-radius: 6px;
                background: #f0f9ff;
                border: 1px solid #e0f2fe;
                color: #0f172a;
            }
            .nxp-ec-invoice__bank {
                margin-top: 8px;
                padding: 10px 12px;
                border-radius: 6px;
                background: #fdf2f8;
                border: 1px solid #fbcfe8;
            }
            .nxp-ec-small {
                font-size: 11px;
                color: #6b7280;
            }
        </style>
    </head>
    <body>
        <div class="nxp-ec-invoice__header">
            <div>
                <h1><?php echo htmlspecialchars($store['name'] ?? 'Your Store', ENT_QUOTES, 'UTF-8'); ?></h1>
                <div class="nxp-ec-small">
                    <?php echo htmlspecialchars($store['url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                </div>
            </div>
            <div class="nxp-ec-invoice__meta">
                <div><strong><?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_INVOICE_NUMBER'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo htmlspecialchars($order['order_no'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                <?php if ($orderDate !== '') : ?>
                    <div><strong><?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_INVOICE_DATE'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo htmlspecialchars($orderDate, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <?php if (!empty($order['email'])) : ?>
                    <div><strong><?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_EMAIL'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo htmlspecialchars($order['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="nxp-ec-invoice__grid">
            <div class="nxp-ec-invoice__panel">
                <h3><?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_INVOICE_BILLING'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <?php if (empty($billingLines)) : ?>
                    <div class="nxp-ec-small"><?php echo htmlspecialchars(Text::_('JNONE'), ENT_QUOTES, 'UTF-8'); ?></div>
                <?php else : ?>
                    <?php foreach ($billingLines as $line) : ?>
                        <div><?php echo htmlspecialchars($line, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php if (!empty($order['shipping'])) : ?>
                <?php
                $shippingLines = [];
                $shipping = $order['shipping'];
                $shipName = trim(($shipping['first_name'] ?? '') . ' ' . ($shipping['last_name'] ?? ''));
                if ($shipName !== '') {
                    $shippingLines[] = $shipName;
                }
                foreach (['address_line1', 'address_line2'] as $field) {
                    if (!empty($shipping[$field])) {
                        $shippingLines[] = (string) $shipping[$field];
                    }
                }
                $shipCityParts = [];
                foreach (['city', 'region', 'postcode'] as $field) {
                    if (!empty($shipping[$field])) {
                        $shipCityParts[] = (string) $shipping[$field];
                    }
                }
                if ($shipCityParts) {
                    $shippingLines[] = implode(', ', $shipCityParts);
                }
                if (!empty($shipping['country'])) {
                    $shippingLines[] = (string) $shipping['country'];
                }
                ?>
                <div class="nxp-ec-invoice__panel">
                    <h3><?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_ORDER_SHIPPING'), ENT_QUOTES, 'UTF-8'); ?></h3>
                    <?php foreach ($shippingLines as $line) : ?>
                        <div><?php echo htmlspecialchars($line, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <table>
            <thead>
                <tr>
                    <th><?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_CART_HEADING_PRODUCT'), ENT_QUOTES, 'UTF-8'); ?></th>
                    <th><?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_CART_HEADING_QTY'), ENT_QUOTES, 'UTF-8'); ?></th>
                    <th><?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_CART_HEADING_TOTAL'), ENT_QUOTES, 'UTF-8'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item) : ?>
                    <?php
                        $title = trim((string) ($item['title'] ?? ''));
                        $sku = trim((string) ($item['sku'] ?? ''));
                    ?>
                    <tr>
                        <td>
                            <div><?php echo htmlspecialchars($title !== '' ? $title : $sku, ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php if ($sku !== '') : ?>
                                <div class="nxp-ec-small"><?php echo htmlspecialchars($sku, ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo (int) ($item['qty'] ?? 1); ?></td>
                        <td><?php echo htmlspecialchars($formatMoney((int) ($item['total_cents'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="nxp-ec-invoice__totals">
                <tr>
                    <td colspan="2"><?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_ORDER_SUBTOTAL'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($formatMoney((int) ($order['subtotal_cents'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
                <?php if (!empty($order['shipping_cents'])) : ?>
                    <tr>
                        <td colspan="2"><?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_CART_SHIPPING'), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($formatMoney((int) $order['shipping_cents']), ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if (!empty($order['tax_cents'])) : ?>
                    <tr>
                        <td colspan="2"><?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_CART_TAX'), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($formatMoney((int) $order['tax_cents']), ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if (!empty($order['discount_cents'])) : ?>
                    <tr>
                        <td colspan="2"><?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_CART_DISCOUNT') ?: 'Discount', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>-<?php echo htmlspecialchars($formatMoney((int) $order['discount_cents']), ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td colspan="2"><?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_ORDER_TOTAL'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($formatMoney((int) ($order['total_cents'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
            </tfoot>
        </table>

        <?php if ($isBankTransfer) : ?>
            <div class="nxp-ec-invoice__note">
                <strong><?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_INVOICE_BANK_TRANSFER_HEADING'), ENT_QUOTES, 'UTF-8'); ?></strong>
                <div>
                    <?php echo htmlspecialchars(Text::sprintf('COM_NXPEASYCART_INVOICE_BANK_TRANSFER_REFERENCE', $order['order_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <?php if (!empty($payment['instructions'])) : ?>
                    <div class="nxp-ec-invoice__bank">
                        <?php echo nl2br(htmlspecialchars((string) $payment['instructions'], ENT_QUOTES, 'UTF-8')); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($payment['account_name']) || !empty($payment['iban']) || !empty($payment['bic'])) : ?>
                    <div class="nxp-ec-invoice__bank">
                        <?php if (!empty($payment['account_name'])) : ?>
                            <div><strong><?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_INVOICE_BANK_ACCOUNT_NAME'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo htmlspecialchars((string) $payment['account_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($payment['iban'])) : ?>
                            <div><strong><?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_INVOICE_BANK_IBAN'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo htmlspecialchars((string) $payment['iban'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($payment['bic'])) : ?>
                            <div><strong><?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_INVOICE_BANK_BIC'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo htmlspecialchars((string) $payment['bic'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </body>
</html>
