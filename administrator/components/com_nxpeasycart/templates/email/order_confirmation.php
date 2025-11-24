<?php

use Joomla\CMS\Language\Text;
use NumberFormatter;

/** @var array<string, mixed> $order */
/** @var array<string, mixed> $store */

$items    = $order['items']    ?? [];
$currency = $order['currency'] ?? 'USD';

$formatMoney = static function (int $cents) use ($currency): string {
    $amount = $cents / 100;

    try {
        return (new NumberFormatter(null, NumberFormatter::CURRENCY))->formatCurrency($amount, $currency);
    } catch (Throwable $exception) {
        return sprintf('%s %.2f', $currency, $amount);
    }
};
?>

<div style="font-family: Arial, sans-serif; color: #111827;">
    <h1 style="font-size: 20px; margin-bottom: 16px;">
        <?php echo htmlspecialchars($store['name'] ?? 'Your Store', ENT_QUOTES, 'UTF-8'); ?>
    </h1>

    <p style="margin: 0 0 24px; font-size: 15px;">
        <?php echo htmlspecialchars(Text::sprintf('COM_NXPEASYCART_EMAIL_GREETING', $order['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
    </p>

    <p style="margin: 0 0 24px; font-size: 15px;">
        <?php echo htmlspecialchars(Text::sprintf('COM_NXPEASYCART_EMAIL_ORDER_PLACED', $order['order_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
    </p>

    <?php if (!empty($order['billing']['phone'])) : ?>
        <p style="margin: 0 0 16px; font-size: 14px;">
            <strong><?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_CHECKOUT_PHONE'), ENT_QUOTES, 'UTF-8'); ?>:</strong>
            <?php echo htmlspecialchars($order['billing']['phone'], ENT_QUOTES, 'UTF-8'); ?>
        </p>
    <?php endif; ?>

    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin-bottom: 24px;">
        <thead>
            <tr>
                <th align="left" style="border-bottom: 1px solid #e2e8f0; padding: 8px 0; font-size: 13px; text-transform: uppercase;">Item</th>
                <th align="right" style="border-bottom: 1px solid #e2e8f0; padding: 8px 0; font-size: 13px; text-transform: uppercase;">Total</th>
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
                <td style="padding-top: 12px; font-weight: bold;">Total</td>
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
