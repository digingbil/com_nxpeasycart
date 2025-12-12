<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Nxpeasycart\Administrator\Helper\MoneyHelper;
use Joomla\Component\Nxpeasycart\Site\Helper\RouteHelper;

/** @var array<string, mixed> $order */
/** @var array<string, mixed> $store */
/** @var array<string, mixed> $payment */

$payment = isset($payment) && \is_array($payment) ? $payment : [];

$items    = $order['items']    ?? [];
$currency = $order['currency'] ?? 'USD';
$publicToken = isset($order['public_token']) ? (string) $order['public_token'] : '';
$trackUrl    = '';

if ($publicToken !== '') {
    $relative = RouteHelper::getOrderRoute($order['order_no'] ?? '', false, $publicToken);
    $trackUrl = str_starts_with($relative, 'http://') || str_starts_with($relative, 'https://')
        ? $relative
        : rtrim(Uri::root(), '/') . '/' . ltrim($relative, '/');
}

$formatMoney = static function (int $cents) use ($currency): string {
    return MoneyHelper::format($cents, $currency);
};

$isBankTransfer = isset($payment['method']) && $payment['method'] === 'bank_transfer';
$isCod = isset($payment['method']) && $payment['method'] === 'cod';
$isOfflinePayment = $isBankTransfer || $isCod;

// Order state and flags
$orderState = strtolower((string) ($order['state'] ?? 'pending'));
$isPaidOrFulfilled = \in_array($orderState, ['paid', 'fulfilled'], true);
$hasDigital = !empty($order['has_digital']);
$hasPhysical = !empty($order['has_physical']);
$isDigitalOnly = $hasDigital && !$hasPhysical;

// Only show downloads if order is paid/fulfilled (not for pending offline payments)
$downloads = [];
if ($isPaidOrFulfilled && isset($order['downloads']) && \is_array($order['downloads'])) {
    $downloads = $order['downloads'];
}

$downloadRemaining = static function (array $download): string {
    $used = (int) ($download['download_count'] ?? 0);
    $max = isset($download['max_downloads']) ? (int) $download['max_downloads'] : null;

    if ($max === null || $max <= 0) {
        return Text::_('COM_NXPEASYCART_ORDER_DOWNLOADS_UNLIMITED');
    }

    $remaining = max(0, $max - $used);

    return Text::sprintf('COM_NXPEASYCART_ORDER_DOWNLOADS_REMAINING', $remaining);
};

$downloadExpiry = static function (array $download): string {
    if (empty($download['expires_at'])) {
        return '';
    }

    return Text::sprintf('COM_NXPEASYCART_ORDER_DOWNLOADS_EXPIRES', (string) $download['expires_at']);
};
?>

<div style="font-family: Arial, sans-serif; color: #111827;">
    <h1 style="font-size: 20px; margin-bottom: 16px;">
        <?php echo htmlspecialchars($store['name'] ?? Text::_('COM_NXPEASYCART_EMAIL_STORE_FALLBACK'), ENT_QUOTES, 'UTF-8'); ?>
    </h1>

    <p style="margin: 0 0 24px; font-size: 15px;">
        <?php echo htmlspecialchars(Text::sprintf('COM_NXPEASYCART_EMAIL_GREETING', $order['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
    </p>

    <p style="margin: 0 0 24px; font-size: 15px;">
        <?php echo htmlspecialchars(Text::sprintf('COM_NXPEASYCART_EMAIL_ORDER_PLACED', $order['order_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
    </p>

    <?php if ($trackUrl !== '') : ?>
        <p style="margin: 0 0 12px; font-size: 14px;">
            <?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_EMAIL_ORDER_TRACKING_COPY'), ENT_QUOTES, 'UTF-8'); ?>
        </p>
        <p style="margin: 0 0 24px;">
            <a href="<?php echo htmlspecialchars($trackUrl, ENT_QUOTES, 'UTF-8'); ?>"
                style="display: inline-block; padding: 12px 18px; background: #111827; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 14px;">
                <?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_EMAIL_ORDER_TRACK'), ENT_QUOTES, 'UTF-8'); ?>
            </a>
        </p>
    <?php endif; ?>

    <?php if ($isBankTransfer) : ?>
        <p style="margin: 0 0 16px; font-size: 14px;">
            <?php echo htmlspecialchars(Text::sprintf('COM_NXPEASYCART_EMAIL_BANK_TRANSFER_INTRO', $order['order_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
        </p>
        <?php if (!empty($payment['instructions'])) : ?>
            <p style="margin: 0 0 12px; font-size: 14px; white-space: pre-line;">
                <?php echo htmlspecialchars((string) $payment['instructions'], ENT_QUOTES, 'UTF-8'); ?>
            </p>
        <?php endif; ?>
        <?php if (!empty($payment['account_name']) || !empty($payment['iban']) || !empty($payment['bic'])) : ?>
            <div style="margin: 0 0 16px; padding: 12px; background: #f1f5f9; border-radius: 8px;">
                <?php if (!empty($payment['account_name'])) : ?>
                    <div style="margin-bottom: 6px;">
                        <strong><?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_INVOICE_BANK_ACCOUNT_NAME'), ENT_QUOTES, 'UTF-8'); ?>:</strong>
                        <?php echo htmlspecialchars((string) $payment['account_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($payment['iban'])) : ?>
                    <div style="margin-bottom: 6px;">
                        <strong><?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_INVOICE_BANK_IBAN'), ENT_QUOTES, 'UTF-8'); ?>:</strong>
                        <?php echo htmlspecialchars((string) $payment['iban'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($payment['bic'])) : ?>
                    <div style="margin-bottom: 6px;">
                        <strong><?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_INVOICE_BANK_BIC'), ENT_QUOTES, 'UTF-8'); ?>:</strong>
                        <?php echo htmlspecialchars((string) $payment['bic'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <p style="margin: 0 0 16px; font-size: 14px;">
            <?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_EMAIL_BANK_TRANSFER_INVOICE_ATTACHED'), ENT_QUOTES, 'UTF-8'); ?>
        </p>
    <?php endif; ?>

    <?php if (!empty($order['billing']['phone'])) : ?>
        <p style="margin: 0 0 16px; font-size: 14px;">
            <strong><?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_CHECKOUT_PHONE'), ENT_QUOTES, 'UTF-8'); ?>:</strong>
            <?php echo htmlspecialchars($order['billing']['phone'], ENT_QUOTES, 'UTF-8'); ?>
        </p>
    <?php endif; ?>

    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin-bottom: 24px;">
        <thead>
            <tr>
                <th align="left" style="border-bottom: 1px solid #e2e8f0; padding: 8px 0; font-size: 13px; text-transform: uppercase;"><?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_EMAIL_TABLE_ITEM'), ENT_QUOTES, 'UTF-8'); ?></th>
                <th align="right" style="border-bottom: 1px solid #e2e8f0; padding: 8px 0; font-size: 13px; text-transform: uppercase;"><?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_EMAIL_TABLE_TOTAL'), ENT_QUOTES, 'UTF-8'); ?></th>
    </tr>
</thead>
<tbody>
    <?php foreach ($items as $item) : ?>
        <tr>
                    <td style="padding: 8px 0; font-size: 14px;">
                        <?php echo htmlspecialchars($item['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                        <br />
                        <small style="color: #64748b;">
                            × <?php echo (int) ($item['qty'] ?? 1); ?> · <?php echo htmlspecialchars($formatMoney((int) ($item['unit_price_cents'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?>
                        </small>
                    </td>
                    <td align="right" style="padding: 8px 0; font-size: 14px;">
                        <?php echo htmlspecialchars($formatMoney((int) ($item['total_cents'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?>
            </td>
        </tr>
    <?php endforeach; ?>
</tbody>
<tfoot>
    <tr>
        <td style="padding-top: 12px; font-weight: bold;">
            <?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_ORDER_SUBTOTAL'), ENT_QUOTES, 'UTF-8'); ?>
        </td>
        <td align="right" style="padding-top: 12px; font-weight: bold;">
            <?php echo htmlspecialchars($formatMoney((int) ($order['subtotal_cents'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?>
        </td>
    </tr>
    <?php $shippingCents = (int) ($order['shipping_cents'] ?? 0); ?>
    <?php if ($shippingCents > 0) : ?>
        <tr>
            <td style="padding-top: 6px;">
                <?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_ORDER_SHIPPING'), ENT_QUOTES, 'UTF-8'); ?>
            </td>
            <td align="right" style="padding-top: 6px;">
                <?php echo htmlspecialchars($formatMoney($shippingCents), ENT_QUOTES, 'UTF-8'); ?>
            </td>
        </tr>
    <?php endif; ?>
    <?php $discountCents = (int) ($order['discount_cents'] ?? 0); ?>
    <?php if ($discountCents > 0) : ?>
        <tr>
            <td style="padding-top: 6px;">
                <?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_CHECKOUT_DISCOUNT'), ENT_QUOTES, 'UTF-8'); ?>
            </td>
            <td align="right" style="padding-top: 6px;">
                -<?php echo htmlspecialchars($formatMoney($discountCents), ENT_QUOTES, 'UTF-8'); ?>
            </td>
        </tr>
    <?php endif; ?>
    <?php $taxCents = (int) ($order['tax_cents'] ?? 0); ?>
    <?php if ($taxCents > 0) : ?>
        <?php
            $taxRate = isset($order['tax_rate']) ? (float) $order['tax_rate'] : 0;
            $taxInclusive = !empty($order['tax_inclusive']);
            $taxLabel = Text::_('COM_NXPEASYCART_CART_TAX');

            if ($taxRate > 0) {
                $taxLabel = $taxInclusive
                    ? Text::sprintf('COM_NXPEASYCART_TAX_LABEL_INCLUSIVE', $taxRate)
                    : Text::sprintf('COM_NXPEASYCART_TAX_LABEL_EXCLUSIVE', $taxRate);
            }
        ?>
        <tr>
            <td style="padding-top: 6px;">
                <?php echo htmlspecialchars($taxLabel, ENT_QUOTES, 'UTF-8'); ?>
            </td>
            <td align="right" style="padding-top: 6px;">
                <?php echo htmlspecialchars($formatMoney($taxCents), ENT_QUOTES, 'UTF-8'); ?>
            </td>
        </tr>
    <?php endif; ?>
    <tr>
        <td style="padding-top: 12px; font-weight: bold;"><?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_EMAIL_TABLE_TOTAL'), ENT_QUOTES, 'UTF-8'); ?></td>
        <td align="right" style="padding-top: 12px; font-weight: bold;">
            <?php echo htmlspecialchars($formatMoney((int) ($order['total_cents'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?>
        </td>
            </tr>
        </tfoot>
    </table>

    <?php if (!empty($downloads)) : ?>
        <h2 style="font-size: 16px; margin: 0 0 12px;">
            <?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_EMAIL_DOWNLOADS_TITLE'), ENT_QUOTES, 'UTF-8'); ?>
        </h2>
        <p style="margin: 0 0 12px; font-size: 14px;">
            <?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_EMAIL_DOWNLOADS_INTRO'), ENT_QUOTES, 'UTF-8'); ?>
        </p>
        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin-bottom: 24px;">
            <thead>
                <tr>
                    <th align="left" style="border-bottom: 1px solid #e2e8f0; padding: 8px 0; font-size: 13px; text-transform: uppercase;">
                        <?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_EMAIL_DOWNLOADS_FILE'), ENT_QUOTES, 'UTF-8'); ?>
                    </th>
                    <th align="left" style="border-bottom: 1px solid #e2e8f0; padding: 8px 0; font-size: 13px; text-transform: uppercase;">
                        <?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_EMAIL_DOWNLOADS_LINK'), ENT_QUOTES, 'UTF-8'); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($downloads as $download) : ?>
                    <tr>
                        <td style="padding: 8px 0; font-size: 14px;">
                            <?php echo htmlspecialchars((string) ($download['filename'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                            <?php if (!empty($download['version'])) : ?>
                                <br>
                                <small style="color: #64748b;">
                                    <?php echo htmlspecialchars(Text::sprintf('COM_NXPEASYCART_EMAIL_DOWNLOADS_VERSION', (string) $download['version']), ENT_QUOTES, 'UTF-8'); ?>
                                </small>
                            <?php endif; ?>
                            <br>
                            <small style="color: #64748b;">
                                <?php echo htmlspecialchars($downloadRemaining($download), ENT_QUOTES, 'UTF-8'); ?>
                                <?php $expiresLabel = $downloadExpiry($download); ?>
                                <?php if ($expiresLabel !== '') : ?>
                                    · <?php echo htmlspecialchars($expiresLabel, ENT_QUOTES, 'UTF-8'); ?>
                                <?php endif; ?>
                            </small>
                        </td>
                        <td style="padding: 8px 0; font-size: 14px;">
                            <?php if (!empty($download['url'])) : ?>
                                <a
                                    href="<?php echo htmlspecialchars((string) $download['url'], ENT_QUOTES, 'UTF-8'); ?>"
                                    style="display: inline-block; padding: 10px 14px; background: #0f172a; color: #fff; text-decoration: none; border-radius: 6px; font-size: 13px;"
                                >
                                    <?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_EMAIL_DOWNLOADS_BUTTON'), ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if ($hasPhysical) : ?>
        <p style="margin: 0 0 16px; font-size: 14px;">
            <?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_EMAIL_ORDER_FOOTER'), ENT_QUOTES, 'UTF-8'); ?>
        </p>
    <?php elseif ($isDigitalOnly && $isPaidOrFulfilled) : ?>
        <p style="margin: 0 0 16px; font-size: 14px;">
            <?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_EMAIL_ORDER_FOOTER_DIGITAL'), ENT_QUOTES, 'UTF-8'); ?>
        </p>
    <?php elseif ($isDigitalOnly && !$isPaidOrFulfilled) : ?>
        <p style="margin: 0 0 16px; font-size: 14px;">
            <?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_EMAIL_ORDER_FOOTER_DIGITAL_PENDING'), ENT_QUOTES, 'UTF-8'); ?>
        </p>
    <?php endif; ?>

    <p style="margin: 0; font-size: 13px; color: #64748b;">
        <?php echo htmlspecialchars($store['url'] ?? Uri::root(), ENT_QUOTES, 'UTF-8'); ?>
    </p>
</div>
