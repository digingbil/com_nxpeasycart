<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var array<string, mixed> $this->checkout */
$checkout = $this->checkout ?? [];

$cart = $checkout['cart'] ?? ['items' => [], 'summary' => []];
$shippingRules = $checkout['shipping_rules'] ?? [];
$taxRates = $checkout['tax_rates'] ?? [];
$settings = $checkout['settings'] ?? [];

$payload = htmlspecialchars(
    json_encode(
        [
            'cart' => $cart,
            'shipping_rules' => $shippingRules,
            'tax_rates' => $taxRates,
            'settings' => $settings,
            'payments' => $checkout['payments'] ?? [],
            'token' => Session::getFormToken(),
            'endpoints' => [
                'checkout' => Route::_('index.php?option=com_nxpeasycart&task=api.orders.store&format=json'),
                'payment' => Route::_('index.php?option=com_nxpeasycart&task=payment.checkout&format=json'),
            ],
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ),
    ENT_QUOTES,
    'UTF-8'
);
?>

<section
    class="nxp-checkout"
    data-nxp-island="checkout"
    data-nxp-checkout="<?php echo $payload; ?>"
>
    <header class="nxp-checkout__header">
        <h1 class="nxp-checkout__title"><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_TITLE'); ?></h1>
        <p class="nxp-checkout__lead">
            <?php echo Text::_('COM_NXPEASYCART_CHECKOUT_LEAD'); ?>
        </p>
    </header>

    <div class="nxp-checkout__layout">
        <form class="nxp-checkout__form" method="post" id="nxp-checkout-form">
            <input type="hidden" name="<?php echo Session::getFormToken(); ?>" value="1" />
            <fieldset>
                <legend><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_CUSTOMER_DETAILS'); ?></legend>
                <div class="nxp-checkout__field">
                    <label for="nxp-checkout-email"><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_EMAIL'); ?></label>
                    <input type="email" name="email" id="nxp-checkout-email" required />
                </div>
            </fieldset>

            <fieldset>
                <legend><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_BILLING'); ?></legend>
                <div class="nxp-checkout__grid">
                    <div class="nxp-checkout__field">
                        <label for="nxp-checkout-first-name"><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_FIRST_NAME'); ?></label>
                        <input type="text" name="billing[first_name]" id="nxp-checkout-first-name" required />
                    </div>
                    <div class="nxp-checkout__field">
                        <label for="nxp-checkout-last-name"><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_LAST_NAME'); ?></label>
                        <input type="text" name="billing[last_name]" id="nxp-checkout-last-name" required />
                    </div>
                    <div class="nxp-checkout__field nxp-checkout__field--wide">
                        <label for="nxp-checkout-address"><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_ADDRESS'); ?></label>
                        <input type="text" name="billing[address_line1]" id="nxp-checkout-address" required />
                    </div>
                    <div class="nxp-checkout__field">
                        <label for="nxp-checkout-city"><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_CITY'); ?></label>
                        <input type="text" name="billing[city]" id="nxp-checkout-city" required />
                    </div>
                    <div class="nxp-checkout__field">
                        <label for="nxp-checkout-postcode"><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_POSTCODE'); ?></label>
                        <input type="text" name="billing[postcode]" id="nxp-checkout-postcode" required />
                    </div>
                    <div class="nxp-checkout__field">
                        <label for="nxp-checkout-country"><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_COUNTRY'); ?></label>
                        <input type="text" name="billing[country]" id="nxp-checkout-country" required />
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_SHIPPING'); ?></legend>
                <p class="nxp-checkout__radio-group" data-nxp-shipping-options>
                    <?php if (empty($shippingRules)) : ?>
                        <span><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_NO_SHIPPING_RULES'); ?></span>
                    <?php else : ?>
                        <?php foreach ($shippingRules as $index => $rule) : ?>
                            <label>
                                <input
                                    type="radio"
                                    name="shipping_rule"
                                    value="<?php echo (int) $rule['id']; ?>"
                                    <?php echo $index === 0 ? 'checked' : ''; ?>
                                />
                                <span>
                                    <?php echo htmlspecialchars($rule['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    — <?php echo htmlspecialchars($cart['summary']['currency'] ?? 'USD', ENT_QUOTES, 'UTF-8'); ?>
                                    <?php echo number_format(((int) ($rule['price_cents'] ?? 0)) / 100, 2); ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </p>
            </fieldset>

            <button type="submit" class="nxp-btn nxp-btn--primary">
                <?php echo Text::_('COM_NXPEASYCART_CHECKOUT_SUBMIT'); ?>
            </button>
        </form>

        <aside class="nxp-checkout__summary">
            <h2><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_ORDER_SUMMARY'); ?></h2>
            <div class="nxp-checkout__cart" data-nxp-cart-summary>
                <?php if (empty($cart['items'])) : ?>
                    <p><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_EMPTY_CART'); ?></p>
                <?php else : ?>
                    <ul>
                        <?php foreach ($cart['items'] as $item) : ?>
                            <li>
                                <div>
                                    <strong><?php echo htmlspecialchars($item['product_title'] ?? $item['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <span class="nxp-checkout__qty">× <?php echo (int) $item['qty']; ?></span>
                                </div>
                                <div class="nxp-checkout__price">
                                    <?php echo htmlspecialchars($item['currency'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    <?php echo number_format(((int) $item['total_cents']) / 100, 2); ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="nxp-checkout__totals">
                        <div>
                            <span><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_SUBTOTAL'); ?></span>
                            <strong>
                                <?php echo htmlspecialchars($cart['summary']['currency'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                <?php echo number_format(((int) ($cart['summary']['subtotal_cents'] ?? 0)) / 100, 2); ?>
                            </strong>
                        </div>
                        <div>
                            <span><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_TOTAL'); ?></span>
                            <strong data-nxp-checkout-total>
                                <?php echo htmlspecialchars($cart['summary']['currency'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                <?php echo number_format(((int) ($cart['summary']['total_cents'] ?? 0)) / 100, 2); ?>
                            </strong>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</section>
