<?php

use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Nxpeasycart\Site\Helper\RouteHelper;
use NumberFormatter;

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
    $amount = $cents / 100;

    try {
        return (new NumberFormatter(null, NumberFormatter::CURRENCY))->formatCurrency($amount, $currency);
    } catch (Throwable $exception) {
        return sprintf('%s %.2f', $currency, $amount);
    }
};

$isBankTransfer = isset($payment['method']) && $payment['method'] === 'bank_transfer';
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
    <tr>
        <td style="padding-top: 12px; font-weight: bold;"><?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_EMAIL_TABLE_TOTAL'), ENT_QUOTES, 'UTF-8'); ?></td>
        <td align="right" style="padding-top: 12px; font-weight: bold;">
            <?php echo htmlspecialchars($formatMoney((int) ($order['total_cents'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?>
        </td>
            </tr>
        </tfoot>
    </table>

    <p style="margin: 0 0 16px; font-size: 14px;">
        <?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_EMAIL_ORDER_FOOTER'), ENT_QUOTES, 'UTF-8'); ?>
    </p>

    <p style="margin: 0; font-size: 13px; color: #64748b;">
        <?php echo htmlspecialchars($store['url'] ?? Uri::root(), ENT_QUOTES, 'UTF-8'); ?>
    </p>
</div>
