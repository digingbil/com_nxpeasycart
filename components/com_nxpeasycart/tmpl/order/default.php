<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
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

$billingLines  = $buildAddressLines($order['billing'] ?? []);
$shippingLines = $buildAddressLines($order['shipping'] ?? []);
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
                                <?php echo htmlspecialchars($order['currency'], ENT_QUOTES, 'UTF-8'); ?>
                                <?php echo number_format(((int) ($item['total_cents'] ?? 0)) / 100, 2); ?>
                            </span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="nxp-ec-order-confirmation__totals">
                <div>
                    <span><?php echo Text::_('COM_NXPEASYCART_ORDER_SUBTOTAL'); ?></span>
                    <strong>
                        <?php echo htmlspecialchars($order['currency'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php echo number_format(((int) ($order['subtotal_cents'] ?? 0)) / 100, 2); ?>
                    </strong>
                </div>
                <div>
                    <span><?php echo Text::_('COM_NXPEASYCART_ORDER_TOTAL'); ?></span>
                    <strong>
                        <?php echo htmlspecialchars($order['currency'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php echo number_format(((int) ($order['total_cents'] ?? 0)) / 100, 2); ?>
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
        </section>
    </div>
</section>
