<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\Component\Nxpeasycart\Administrator\Helper\ConfigHelper;
use Joomla\Component\Nxpeasycart\Administrator\Helper\MoneyHelper;
use Joomla\Component\Nxpeasycart\Site\Helper\RouteHelper;

/** @var array<string, mixed>|null $this->order */
$order = $this->order ?? null;

$buildAddressLines = static function (array $address): array {
    $lines = [];

    $name = trim(($address['first_name'] ?? '') . ' ' . ($address['last_name'] ?? ''));

    if ($name !== '') {
        $lines[] = $name;
    }

    foreach (['address_line1', 'address_line2'] as $key) {
        if (!empty($address[$key])) {
            $lines[] = (string) $address[$key];
        }
    }

    $cityParts = [];

    foreach (['city', 'region', 'postcode'] as $partKey) {
        if (!empty($address[$partKey])) {
            $cityParts[] = (string) $address[$partKey];
        }
    }

    if ($cityParts) {
        $lines[] = implode(', ', $cityParts);
    }

    if (!empty($address['country'])) {
        $lines[] = (string) $address['country'];
    }

    return $lines;
};

$billingLines  = $order ? $buildAddressLines($order['billing'] ?? []) : [];
$shippingLines = $order ? $buildAddressLines($order['shipping'] ?? []) : [];
$state         = $order && isset($order['state']) ? strtolower((string) $order['state']) : '';
$stateLabels   = [
    'cart'      => Text::_('COM_NXPEASYCART_ORDER_STATE_CART'),
    'pending'   => Text::_('COM_NXPEASYCART_ORDER_STATE_PENDING'),
    'paid'      => Text::_('COM_NXPEASYCART_ORDER_STATE_PAID'),
    'fulfilled' => Text::_('COM_NXPEASYCART_ORDER_STATE_FULFILLED'),
    'refunded'  => Text::_('COM_NXPEASYCART_ORDER_STATE_REFUNDED'),
    'canceled'  => Text::_('COM_NXPEASYCART_ORDER_STATE_CANCELED'),
];
$stateLabel    = $stateLabels[$state] ?? ucfirst($state);

$formatDate = static function (?string $value): string {
    if ($value === null || $value === '') {
        return '';
    }

    try {
        // Parse as UTC (database storage), convert to site timezone for display
        $date = Factory::getDate($value, 'UTC');
        $tz   = Factory::getApplication()->get('offset', 'UTC');
        $date->setTimezone(new \DateTimeZone($tz));

        // Note: Joomla's Date::format() has a $local param (2nd arg) - must be true
        // to use the timezone we set, otherwise it resets to UTC before formatting
        return $date->format(Text::_('DATE_FORMAT_LC2'), true);
    } catch (\Throwable $exception) {
        return (string) $value;
    }
};

$statusUpdated = $order ? $formatDate($order['status_updated_at'] ?? ($order['modified'] ?? $order['created'] ?? '')) : '';
$createdAt     = $order ? $formatDate($order['created'] ?? null) : '';
$trackingNumber = $order ? trim((string) ($order['tracking_number'] ?? '')) : '';
$carrier        = $order ? trim((string) ($order['carrier'] ?? '')) : '';
$trackingUrl    = $order ? trim((string) ($order['tracking_url'] ?? '')) : '';
$events         = $order && \is_array($order['fulfillment_events'] ?? null) ? $order['fulfillment_events'] : [];
$currency       = $order ? strtoupper((string) ($order['currency'] ?? ConfigHelper::getBaseCurrency())) : ConfigHelper::getBaseCurrency();
$formatMoney    = static fn (int $cents) => MoneyHelper::format($cents, $currency);
$downloads      = $order && \is_array($order['downloads'] ?? null) ? $order['downloads'] : [];
$showDownloads  = $order && $downloads && \in_array($state, ['paid', 'fulfilled'], true);

$downloadRemaining = static function (array $download): string {
    $used = (int) ($download['download_count'] ?? 0);
    $max  = isset($download['max_downloads']) ? (int) $download['max_downloads'] : null;

    if ($max === null || $max <= 0) {
        return Text::_('COM_NXPEASYCART_ORDER_DOWNLOADS_UNLIMITED');
    }

    $remaining = max(0, $max - $used);

    return Text::sprintf('COM_NXPEASYCART_ORDER_DOWNLOADS_REMAINING', $remaining);
};

$downloadExpiry = static function (array $download) use ($formatDate): string {
    $expiresAt = $download['expires_at'] ?? null;

    if ($expiresAt === null || $expiresAt === '') {
        return '';
    }

    return Text::sprintf('COM_NXPEASYCART_ORDER_DOWNLOADS_EXPIRES', $formatDate((string) $expiresAt));
};
?>

<section class="nxp-ec-order-confirmation">
    <?php if (!$order) : ?>
        <header>
            <h1><?php echo Text::_('COM_NXPEASYCART_ORDER_NOT_FOUND'); ?></h1>
            <p>
                <a href="<?php echo htmlspecialchars(RouteHelper::getCartRoute(), ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo Text::_('COM_NXPEASYCART_ORDER_RETURN_TO_CART'); ?>
                </a>
            </p>
        </header>

        <?php return; ?>
    <?php endif; ?>

    <header class="nxp-ec-order-confirmation__header">
        <h1>
            <?php echo Text::sprintf('COM_NXPEASYCART_ORDER_CONFIRMED_TITLE', htmlspecialchars($order['order_no'] ?? '', ENT_QUOTES, 'UTF-8')); ?>
        </h1>
        <p><?php echo Text::_('COM_NXPEASYCART_ORDER_CONFIRMED_LEAD'); ?></p>
        <div class="nxp-ec-order-confirmation__status">
            <span class="nxp-ec-order-confirmation__badge nxp-ec-order-confirmation__badge--<?php echo htmlspecialchars($state, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($stateLabel, ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <?php if ($statusUpdated !== '') : ?>
                <span class="nxp-ec-order-confirmation__timestamp">
                    <?php echo Text::sprintf(
                        'COM_NXPEASYCART_ORDER_LAST_UPDATED_AT',
                        htmlspecialchars($statusUpdated, ENT_QUOTES, 'UTF-8')
                    ); ?>
                </span>
            <?php endif; ?>
            <?php if ($createdAt !== '') : ?>
                <span class="nxp-ec-order-confirmation__timestamp">
                    <?php echo Text::sprintf(
                        'COM_NXPEASYCART_ORDER_PLACED_AT',
                        htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8')
                    ); ?>
                </span>
            <?php endif; ?>
        </div>
        <?php if ($state === 'pending' && !empty($order['payment_method']) && strtolower((string) $order['payment_method']) === 'paypal') : ?>
            <p class="nxp-ec-order-confirmation__notice nxp-ec-order-confirmation__notice--info">
                <?php echo Text::_('COM_NXPEASYCART_ORDER_PAYPAL_PENDING_NOTICE'); ?>
            </p>
        <?php endif; ?>
        <?php if (!empty($this->isPublic) && empty($this->isOwner)) : ?>
            <p class="nxp-ec-order-confirmation__notice">
                <?php echo Text::_('COM_NXPEASYCART_ORDER_PUBLIC_MASKING_NOTICE'); ?>
            </p>
        <?php endif; ?>
    </header>

    <div class="nxp-ec-order-confirmation__grid">
        <section class="nxp-ec-order-confirmation__summary">
            <h2><?php echo Text::_('COM_NXPEASYCART_ORDER_SUMMARY'); ?></h2>
            <ul class="nxp-ec-order-confirmation__items">
                <?php foreach ($order['items'] ?? [] as $item) : ?>
                    <?php
                        $title        = trim((string) ($item['title'] ?? ''));
                        $productTitle = trim((string) ($item['product_title'] ?? ''));
                        $variantLabel = trim((string) ($item['variant_label'] ?? ''));
                        $sku          = trim((string) ($item['sku'] ?? ''));
                        $image        = $item['image'] ?? null;
                        $qty          = (int) ($item['qty'] ?? 1);

                        if ($title === '' && $productTitle !== '') {
                            $title = $productTitle;
                        }
                    ?>
                    <li class="nxp-ec-order-confirmation__item">
                        <div class="nxp-ec-order-confirmation__item-left">
                            <?php if ($image) : ?>
                                <span class="nxp-ec-order-confirmation__thumb">
                                    <img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($title !== '' ? $title : $sku, ENT_QUOTES, 'UTF-8'); ?>" loading="lazy">
                                </span>
                            <?php endif; ?>
                            <div class="nxp-ec-order-confirmation__item-text">
                                <strong><?php echo htmlspecialchars($title !== '' ? $title : $sku, ENT_QUOTES, 'UTF-8'); ?></strong>
                                <?php if ($variantLabel !== '') : ?>
                                    <span class="nxp-ec-order-confirmation__variant"><?php echo htmlspecialchars($variantLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                                <?php if ($sku !== '') : ?>
                                    <span class="nxp-ec-order-confirmation__sku">
                                        <?php echo Text::_('COM_NXPEASYCART_PRODUCT_VARIANT_SKU_LABEL'); ?>:
                                        <?php echo htmlspecialchars($sku, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="nxp-ec-order-confirmation__item-price">
                            <span class="nxp-ec-order-confirmation__qty">× <?php echo $qty; ?></span>
                            <span class="nxp-ec-order-confirmation__amount">
                                <?php echo htmlspecialchars($formatMoney((int) ($item['total_cents'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($showDownloads) : ?>
                <div class="nxp-ec-order-confirmation__downloads">
                    <h3><?php echo Text::_('COM_NXPEASYCART_ORDER_DOWNLOADS_TITLE'); ?></h3>
                    <ul class="nxp-ec-order-confirmation__download-list">
                        <?php foreach ($downloads as $download) : ?>
                            <li class="nxp-ec-order-confirmation__download">
                                <div class="nxp-ec-order-confirmation__download-meta">
                                    <strong><?php echo htmlspecialchars((string) ($download['filename'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <?php if (!empty($download['version'])) : ?>
                                        <span class="nxp-ec-order-confirmation__download-version">
                                            v<?php echo htmlspecialchars((string) $download['version'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    <?php endif; ?>
                                    <div class="nxp-ec-order-confirmation__download-hint">
                                        <?php echo htmlspecialchars($downloadRemaining($download), ENT_QUOTES, 'UTF-8'); ?>
                                        <?php $expiresLabel = $downloadExpiry($download); ?>
                                        <?php if ($expiresLabel !== '') : ?>
                                            · <?php echo htmlspecialchars($expiresLabel, ENT_QUOTES, 'UTF-8'); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if (!empty($download['url'])) : ?>
                                    <a
                                        class="nxp-ec-btn"
                                        href="<?php echo htmlspecialchars((string) $download['url'], ENT_QUOTES, 'UTF-8'); ?>"
                                    >
                                        <?php echo Text::_('COM_NXPEASYCART_DOWNLOAD'); ?>
                                    </a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <div class="nxp-ec-order-confirmation__totals">
                <div>
                    <span><?php echo Text::_('COM_NXPEASYCART_ORDER_SUBTOTAL'); ?></span>
                    <strong>
                        <?php echo htmlspecialchars($formatMoney((int) ($order['subtotal_cents'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?>
                    </strong>
                </div>
                <?php if (!empty($order['shipping_cents'])) : ?>
                    <div>
                        <span><?php echo Text::_('COM_NXPEASYCART_CART_SHIPPING'); ?></span>
                        <strong>
                            <?php echo htmlspecialchars($formatMoney((int) $order['shipping_cents']), ENT_QUOTES, 'UTF-8'); ?>
                        </strong>
                    </div>
                <?php endif; ?>
                <?php if (!empty($order['discount_cents'])) : ?>
                    <div>
                        <span><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_DISCOUNT'); ?></span>
                        <strong>
                            -<?php echo htmlspecialchars($formatMoney((int) $order['discount_cents']), ENT_QUOTES, 'UTF-8'); ?>
                        </strong>
                    </div>
                <?php endif; ?>
                <?php if (!empty($order['tax_cents'])) : ?>
                    <?php
                    $taxRate = isset($order['tax_rate']) ? (float) $order['tax_rate'] : 0;
                    $taxInclusive = !empty($order['tax_inclusive']);
                    $taxName = !empty($order['tax_name']) ? (string) $order['tax_name'] : Text::_('COM_NXPEASYCART_CART_TAX');
                    $taxLabel = $taxName;
                    if ($taxRate > 0) {
                        $taxLabel = $taxInclusive
                            ? sprintf('%s (%s%% incl.)', $taxName, $taxRate)
                            : sprintf('%s (%s%%)', $taxName, $taxRate);
                    }
                    ?>
                    <div>
                        <span><?php echo $taxLabel; ?></span>
                        <strong>
                            <?php echo htmlspecialchars($formatMoney((int) $order['tax_cents']), ENT_QUOTES, 'UTF-8'); ?>
                        </strong>
                    </div>
                <?php endif; ?>
                <div>
                    <span><?php echo Text::_('COM_NXPEASYCART_ORDER_TOTAL'); ?></span>
                    <strong>
                        <?php echo htmlspecialchars($formatMoney((int) ($order['total_cents'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?>
                    </strong>
                </div>
            </div>
        </section>

        <section class="nxp-ec-order-confirmation__details">
            <h2><?php echo Text::_('COM_NXPEASYCART_ORDER_CUSTOMER'); ?></h2>
            <p><?php echo htmlspecialchars($order['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
            <?php if (!empty($order['billing']['phone'])) : ?>
                <p>
                    <?php echo Text::_('COM_NXPEASYCART_CHECKOUT_PHONE'); ?>:
                    <?php echo htmlspecialchars($order['billing']['phone'], ENT_QUOTES, 'UTF-8'); ?>
                </p>
            <?php endif; ?>

            <h3><?php echo Text::_('COM_NXPEASYCART_ORDER_BILLING'); ?></h3>
            <?php if (empty($billingLines)) : ?>
                <p class="nxp-ec-order-confirmation__address"><?php echo Text::_('JNONE'); ?></p>
            <?php else : ?>
                <address class="nxp-ec-order-confirmation__address">
                    <?php foreach ($billingLines as $line) : ?>
                        <?php echo htmlspecialchars($line, ENT_QUOTES, 'UTF-8'); ?><br />
                    <?php endforeach; ?>
                </address>
            <?php endif; ?>

            <?php if (!empty($shippingLines)) : ?>
                <h3><?php echo Text::_('COM_NXPEASYCART_ORDER_SHIPPING'); ?></h3>
                <address class="nxp-ec-order-confirmation__address">
                    <?php foreach ($shippingLines as $line) : ?>
                        <?php echo htmlspecialchars($line, ENT_QUOTES, 'UTF-8'); ?><br />
                    <?php endforeach; ?>
                </address>
            <?php endif; ?>

            <?php if ($carrier !== '' || $trackingNumber !== '' || $trackingUrl !== '') : ?>
                <h3><?php echo Text::_('COM_NXPEASYCART_ORDER_TRACKING'); ?></h3>
                <dl class="nxp-ec-order-confirmation__tracking">
                    <?php if ($carrier !== '') : ?>
                        <div>
                            <dt><?php echo Text::_('COM_NXPEASYCART_ORDER_TRACKING_CARRIER'); ?></dt>
                            <dd><?php echo htmlspecialchars($carrier, ENT_QUOTES, 'UTF-8'); ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($trackingNumber !== '') : ?>
                        <div>
                            <dt><?php echo Text::_('COM_NXPEASYCART_ORDER_TRACKING_NUMBER'); ?></dt>
                            <dd><?php echo htmlspecialchars($trackingNumber, ENT_QUOTES, 'UTF-8'); ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($trackingUrl !== '') : ?>
                        <div>
                            <dt><?php echo Text::_('COM_NXPEASYCART_ORDER_TRACKING_LINK'); ?></dt>
                            <dd>
                                <a href="<?php echo htmlspecialchars($trackingUrl, ENT_QUOTES, 'UTF-8'); ?>" rel="nofollow noopener" target="_blank">
                                    <?php echo Text::_('COM_NXPEASYCART_ORDER_TRACKING_VIEW'); ?>
                                </a>
                            </dd>
                        </div>
                    <?php endif; ?>
                </dl>
            <?php endif; ?>
        </section>
    </div>

    <?php if (!empty($events)) : ?>
        <section class="nxp-ec-order-confirmation__timeline">
            <h2><?php echo Text::_('COM_NXPEASYCART_ORDER_TIMELINE'); ?></h2>
            <ul>
                <?php foreach ($events as $event) : ?>
                    <?php
                        $eventState = isset($event['state']) ? strtolower((string) $event['state']) : '';
                        $eventLabel = $eventState !== '' && isset($stateLabels[$eventState])
                            ? $stateLabels[$eventState]
                            : trim((string) ($event['message'] ?? ''));
                        $eventLabel = $eventLabel !== '' ? $eventLabel : ucfirst((string) ($event['type'] ?? 'update'));
                        $eventAt    = $formatDate($event['at'] ?? null);
                    ?>
                    <li class="nxp-ec-order-confirmation__timeline-item">
                        <div>
                            <strong><?php echo htmlspecialchars($eventLabel, ENT_QUOTES, 'UTF-8'); ?></strong>
                            <?php if ($eventAt !== '') : ?>
                                <span class="nxp-ec-order-confirmation__timestamp">
                                    <?php echo htmlspecialchars($eventAt, ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</section>
