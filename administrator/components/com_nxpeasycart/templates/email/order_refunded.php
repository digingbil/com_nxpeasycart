<?php

use Joomla\CMS\Factory as JoomlaFactory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use NumberFormatter;

/** @var array<string, mixed> $order */
/** @var array<string, mixed> $store */
/** @var array<string, mixed> $refund */

$refund = isset($refund) && \is_array($refund) ? $refund : [];

$items    = $order['items']    ?? [];
$currency = $order['currency'] ?? 'USD';
$publicToken = isset($order['public_token']) ? (string) $order['public_token'] : '';
$trackUrl    = '';

if ($publicToken !== '') {
    // Build SEF URL by looking up the menu item alias
    $siteRoot = rtrim(Uri::root(), '/');
    $menuAlias = '';

    try {
        // Get site menu to find the landing/order menu item
        $app = JoomlaFactory::getApplication('site');
        $menu = $app->getMenu();
        $menuItems = $menu->getItems('component', 'com_nxpeasycart') ?: [];

        foreach ($menuItems as $item) {
            $view = $item->query['view'] ?? '';
            if ($view === 'landing' || $view === 'order') {
                $menuAlias = $item->route ?? $item->alias ?? '';
                break;
            }
        }
    } catch (\Throwable $e) {
        // Fallback if menu lookup fails
    }

    if ($menuAlias !== '') {
        $trackUrl = $siteRoot . '/' . $menuAlias . '/order?ref=' . rawurlencode($publicToken);
    } else {
        $trackUrl = $siteRoot . '/index.php?option=com_nxpeasycart&view=order&ref=' . rawurlencode($publicToken);
    }
}

$formatMoney = static function (int $cents) use ($currency): string {
    $amount = $cents / 100;

    try {
        return (new NumberFormatter(null, NumberFormatter::CURRENCY))->formatCurrency($amount, $currency);
    } catch (Throwable $exception) {
        return sprintf('%s %.2f', $currency, $amount);
    }
};

$refundAmount = isset($refund['amount_cents']) ? (int) $refund['amount_cents'] : (int) ($order['total_cents'] ?? 0);
$refundReason = $refund['reason'] ?? '';
$isPartialRefund = $refundAmount < (int) ($order['total_cents'] ?? 0);
?>

<div style="font-family: Arial, sans-serif; color: #111827;">
    <h1 style="font-size: 20px; margin-bottom: 16px;">
        <?php echo htmlspecialchars($store['name'] ?? 'Your Store', ENT_QUOTES, 'UTF-8'); ?>
    </h1>

    <p style="margin: 0 0 24px; font-size: 15px;">
        <?php echo htmlspecialchars(Text::sprintf('COM_NXPEASYCART_EMAIL_GREETING', $order['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
    </p>

    <p style="margin: 0 0 24px; font-size: 15px;">
        <?php if ($isPartialRefund) : ?>
            <?php echo htmlspecialchars(Text::sprintf('COM_NXPEASYCART_EMAIL_ORDER_PARTIAL_REFUND', $order['order_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
        <?php else : ?>
            <?php echo htmlspecialchars(Text::sprintf('COM_NXPEASYCART_EMAIL_ORDER_REFUNDED', $order['order_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
        <?php endif; ?>
    </p>

    <div style="margin: 0 0 24px; padding: 16px; background: #fef3c7; border-radius: 8px; border-left: 4px solid #f59e0b;">
        <h2 style="margin: 0 0 12px; font-size: 16px; color: #92400e;">
            <?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_EMAIL_REFUND_DETAILS'), ENT_QUOTES, 'UTF-8'); ?>
        </h2>
        <p style="margin: 0 0 8px; font-size: 14px;">
            <strong><?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_EMAIL_REFUND_AMOUNT'), ENT_QUOTES, 'UTF-8'); ?>:</strong>
            <?php echo htmlspecialchars($formatMoney($refundAmount), ENT_QUOTES, 'UTF-8'); ?>
        </p>
        <?php if ($refundReason !== '') : ?>
            <p style="margin: 0; font-size: 14px;">
                <strong><?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_EMAIL_REFUND_REASON'), ENT_QUOTES, 'UTF-8'); ?>:</strong>
                <?php echo htmlspecialchars($refundReason, ENT_QUOTES, 'UTF-8'); ?>
            </p>
        <?php endif; ?>
    </div>

    <p style="margin: 0 0 16px; font-size: 14px;">
        <?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_EMAIL_REFUND_PROCESSING'), ENT_QUOTES, 'UTF-8'); ?>
    </p>

    <?php if ($trackUrl !== '') : ?>
        <p style="margin: 0 0 12px; font-size: 14px;">
            <?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_EMAIL_ORDER_TRACKING_COPY'), ENT_QUOTES, 'UTF-8'); ?>
        </p>
        <p style="margin: 0 0 24px;">
            <a href="<?php echo htmlspecialchars($trackUrl, ENT_QUOTES, 'UTF-8'); ?>"
                style="display: inline-block; padding: 12px 18px; background: #4f6d7a; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 14px;">
                <?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_EMAIL_ORDER_TRACK'), ENT_QUOTES, 'UTF-8'); ?>
            </a>
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
                <td style="padding-top: 12px; font-weight: bold;">
                    <?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_ORDER_SUBTOTAL'), ENT_QUOTES, 'UTF-8'); ?>
                </td>
                <td align="right" style="padding-top: 12px; font-weight: bold;">
                    <?php echo htmlspecialchars($formatMoney((int) ($order['subtotal_cents'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?>
                </td>
            </tr>
            <tr>
                <td style="padding-top: 12px; font-weight: bold; color: #dc2626;">
                    <?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_EMAIL_REFUND_AMOUNT'), ENT_QUOTES, 'UTF-8'); ?>
                </td>
                <td align="right" style="padding-top: 12px; font-weight: bold; color: #dc2626;">
                    -<?php echo htmlspecialchars($formatMoney($refundAmount), ENT_QUOTES, 'UTF-8'); ?>
                </td>
            </tr>
        </tfoot>
    </table>

    <p style="margin: 0 0 16px; font-size: 14px;">
        <?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_EMAIL_REFUND_FOOTER'), ENT_QUOTES, 'UTF-8'); ?>
    </p>

    <p style="margin: 0; font-size: 13px; color: #64748b;">
        <?php echo htmlspecialchars($store['url'] ?? Uri::root(), ENT_QUOTES, 'UTF-8'); ?>
    </p>
</div>
