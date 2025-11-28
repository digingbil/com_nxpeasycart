<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Component\Nxpeasycart\Administrator\Helper\ConfigHelper;

/** @var array<string, mixed> $this->checkout */
$checkout = $this->checkout ?? [];
$theme    = $this->theme ?? [];

$cart          = $checkout['cart']           ?? ['items' => [], 'summary' => []];
$shippingRules = $checkout['shipping_rules'] ?? [];
$taxRates      = $checkout['tax_rates']      ?? [];
$settings      = $checkout['settings']       ?? [];
$userPrefill   = $checkout['user']           ?? [];
$phoneRequired = !empty($settings['checkout_phone_required']);
$phonePlaceholder = $phoneRequired
    ? Text::_('COM_NXPEASYCART_CHECKOUT_PHONE_PLACEHOLDER_REQUIRED')
    : Text::_('COM_NXPEASYCART_CHECKOUT_PHONE_PLACEHOLDER');


$payload = htmlspecialchars(
    json_encode(
        [
            'cart'           => $cart,
            'shipping_rules' => $shippingRules,
            'tax_rates'      => $taxRates,
            'settings'       => $settings,
            'payments'       => $checkout['payments'] ?? [],
            'token'          => Session::getFormToken(),
            'prefill'        => $userPrefill,
            'endpoints'      => [
                'checkout'      => Route::_('index.php?option=com_nxpeasycart&task=api.orders.store&format=json'),
                'payment'       => Route::_('index.php?option=com_nxpeasycart&task=payment.checkout&format=json'),
                'summary'       => Route::_('index.php?option=com_nxpeasycart&task=cart.summary&format=json'),
                'applyCoupon'   => Route::_('index.php?option=com_nxpeasycart&task=cart.applyCoupon&format=json'),
                'removeCoupon'  => Route::_('index.php?option=com_nxpeasycart&task=cart.removeCoupon&format=json'),
            ],
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ),
    ENT_QUOTES,
    'UTF-8'
);

$labels = [
    'title'                   => Text::_('COM_NXPEASYCART_CHECKOUT_TITLE'),
    'lead'                    => Text::_('COM_NXPEASYCART_CHECKOUT_LEAD'),
    'contact'                 => Text::_('COM_NXPEASYCART_CHECKOUT_CUSTOMER_DETAILS'),
    'email'                   => Text::_('COM_NXPEASYCART_CHECKOUT_EMAIL'),
    'billing'                 => Text::_('COM_NXPEASYCART_CHECKOUT_BILLING'),
    'first_name'              => Text::_('COM_NXPEASYCART_CHECKOUT_FIRST_NAME'),
    'last_name'               => Text::_('COM_NXPEASYCART_CHECKOUT_LAST_NAME'),
    'address'                 => Text::_('COM_NXPEASYCART_CHECKOUT_ADDRESS'),
    'city'                    => Text::_('COM_NXPEASYCART_CHECKOUT_CITY'),
    'postcode'                => Text::_('COM_NXPEASYCART_CHECKOUT_POSTCODE'),
    'country'                 => Text::_('COM_NXPEASYCART_CHECKOUT_COUNTRY'),
    'region'                  => Text::_('COM_NXPEASYCART_CHECKOUT_REGION'),
    'region_state'            => Text::_('COM_NXPEASYCART_CHECKOUT_REGION_STATE'),
    'region_province'         => Text::_('COM_NXPEASYCART_CHECKOUT_REGION_PROVINCE'),
    'region_territory'        => Text::_('COM_NXPEASYCART_CHECKOUT_REGION_STATE_TERRITORY'),
    'region_county'           => Text::_('COM_NXPEASYCART_CHECKOUT_REGION_COUNTY'),
    'select_country'          => Text::_('COM_NXPEASYCART_CHECKOUT_SELECT_COUNTRY'),
    'select_region'           => Text::_('COM_NXPEASYCART_CHECKOUT_SELECT_REGION'),
    'select_state'            => Text::_('COM_NXPEASYCART_CHECKOUT_SELECT_STATE'),
    'select_province'         => Text::_('COM_NXPEASYCART_CHECKOUT_SELECT_PROVINCE'),
    'phone'                   => Text::_('COM_NXPEASYCART_CHECKOUT_PHONE'),
    'phone_placeholder'       => Text::_('COM_NXPEASYCART_CHECKOUT_PHONE_PLACEHOLDER'),
    'phone_placeholder_required' => Text::_('COM_NXPEASYCART_CHECKOUT_PHONE_PLACEHOLDER_REQUIRED'),
    'phone_required'          => Text::_('COM_NXPEASYCART_ERROR_CHECKOUT_PHONE_REQUIRED'),
    'phone_invalid'           => Text::_('COM_NXPEASYCART_ERROR_CHECKOUT_PHONE_INVALID'),
    'shipping'                => Text::_('COM_NXPEASYCART_CHECKOUT_SHIPPING'),
    'shipping_address'        => Text::_('COM_NXPEASYCART_CHECKOUT_SHIPPING_ADDRESS'),
    'ship_to_different'       => Text::_('COM_NXPEASYCART_CHECKOUT_SHIP_TO_DIFFERENT'),
    'shipping_required'       => Text::_('COM_NXPEASYCART_ERROR_CHECKOUT_SHIPPING_REQUIRED'),
    'shipping_select_country' => Text::_('COM_NXPEASYCART_CHECKOUT_SHIPPING_SELECT_COUNTRY'),
    'shipping_no_rules'       => Text::_('COM_NXPEASYCART_CHECKOUT_SHIPPING_NO_RULES_FOR_COUNTRY'),
    'payment_method'          => Text::_('COM_NXPEASYCART_CHECKOUT_PAYMENT_METHOD'),
    'payment_offline'         => Text::_('COM_NXPEASYCART_CHECKOUT_PAYMENT_OFFLINE'),
    'processing'              => Text::_('COM_NXPEASYCART_CHECKOUT_PROCESSING'),
    'submit'                  => Text::_('COM_NXPEASYCART_CHECKOUT_SUBMIT'),
    'order_summary'           => Text::_('COM_NXPEASYCART_CHECKOUT_ORDER_SUMMARY'),
    'subtotal'                => Text::_('COM_NXPEASYCART_CHECKOUT_SUBTOTAL'),
    'total'                   => Text::_('COM_NXPEASYCART_CHECKOUT_TOTAL'),
    'empty_cart'              => Text::_('COM_NXPEASYCART_ERROR_CHECKOUT_EMPTY_CART'),
    'thank_you'               => Text::_('COM_NXPEASYCART_CHECKOUT_THANK_YOU'),
    'order_created'           => Text::_('COM_NXPEASYCART_CHECKOUT_ORDER_CREATED'),
    'view_order'              => Text::_('COM_NXPEASYCART_CHECKOUT_VIEW_ORDER'),
    'error_generic'           => Text::_('COM_NXPEASYCART_ERROR_CHECKOUT_GENERIC'),
    'coupon_label'            => Text::_('COM_NXPEASYCART_CHECKOUT_COUPON_LABEL'),
    'coupon_placeholder'      => Text::_('COM_NXPEASYCART_CHECKOUT_COUPON_PLACEHOLDER'),
    'coupon_apply'            => Text::_('COM_NXPEASYCART_CHECKOUT_COUPON_APPLY'),
    'coupon_remove'           => Text::_('COM_NXPEASYCART_CHECKOUT_COUPON_REMOVE'),
    'discount'                => Text::_('COM_NXPEASYCART_CHECKOUT_DISCOUNT'),
];

$labelsJson = htmlspecialchars(
    json_encode($labels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);
$locale   = \Joomla\CMS\Factory::getApplication()->getLanguage()->getTag();
$currency = strtoupper((string) ($cart['summary']['currency'] ?? ConfigHelper::getBaseCurrency()));
$cssVars = '';
foreach (($theme['css_vars'] ?? []) as $var => $value) {
    $cssVars .= $var . ':' . $value . ';';
}
?>

<section
    class="nxp-ec-checkout"
    data-nxp-island="checkout"
    data-nxp-checkout="<?php echo $payload; ?>"
    data-nxp-labels="<?php echo $labelsJson; ?>"
    data-nxp-locale="<?php echo htmlspecialchars($locale, ENT_QUOTES, 'UTF-8'); ?>"
    data-nxp-currency="<?php echo htmlspecialchars($currency, ENT_QUOTES, 'UTF-8'); ?>"
    <?php if ($cssVars !== '') : ?>style="<?php echo htmlspecialchars($cssVars, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>
>
    <header class="nxp-ec-checkout__header">
        <h1 class="nxp-ec-checkout__title"><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_TITLE'); ?></h1>
        <p class="nxp-ec-checkout__lead">
            <?php echo Text::_('COM_NXPEASYCART_CHECKOUT_LEAD'); ?>
        </p>
    </header>

    <div class="nxp-ec-checkout__layout">
        <form class="nxp-ec-checkout__form" method="post" id="nxp-ec-checkout-form">
            <input type="hidden" name="<?php echo Session::getFormToken(); ?>" value="1" />
            <div class="nxp-ec-checkout__field" aria-hidden="true">
                <label for="nxp-ec-checkout-company-website" class="visually-hidden">Company website</label>
                <input
                    type="text"
                    name="company_website"
                    id="nxp-ec-checkout-company-website"
                    tabindex="-1"
                    autocomplete="off"
                    style="position:absolute; left:-9999px; width:1px; height:1px; opacity:0;"
                />
            </div>
            <fieldset>
                <legend><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_CUSTOMER_DETAILS'); ?></legend>
                <div class="nxp-ec-checkout__field">
                    <label for="nxp-ec-checkout-email"><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_EMAIL'); ?></label>
                    <input type="email" name="email" id="nxp-ec-checkout-email" required />
                </div>
            </fieldset>

            <fieldset>
                <legend><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_BILLING'); ?></legend>
                <div class="nxp-ec-checkout__grid">
                    <div class="nxp-ec-checkout__field">
                        <label for="nxp-ec-checkout-first-name"><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_FIRST_NAME'); ?></label>
                        <input type="text" name="billing[first_name]" id="nxp-ec-checkout-first-name" required />
                    </div>
                    <div class="nxp-ec-checkout__field">
                        <label for="nxp-ec-checkout-last-name"><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_LAST_NAME'); ?></label>
                        <input type="text" name="billing[last_name]" id="nxp-ec-checkout-last-name" required />
                    </div>
                    <div class="nxp-ec-checkout__field">
                        <label for="nxp-ec-checkout-phone"><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_PHONE'); ?></label>
                        <input
                            type="tel"
                            name="billing[phone]"
                            id="nxp-ec-checkout-phone"
                            <?php echo $phoneRequired ? 'required' : ''; ?>
                            placeholder="<?php echo htmlspecialchars($phonePlaceholder, ENT_QUOTES, 'UTF-8'); ?>"
                            inputmode="tel"
                            autocomplete="tel"
                        />
                    </div>
                    <div class="nxp-ec-checkout__field nxp-ec-checkout__field--wide">
                        <label for="nxp-ec-checkout-address"><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_ADDRESS'); ?></label>
                        <input type="text" name="billing[address_line1]" id="nxp-ec-checkout-address" required />
                    </div>
                    <div class="nxp-ec-checkout__field">
                        <label for="nxp-ec-checkout-city"><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_CITY'); ?></label>
                        <input type="text" name="billing[city]" id="nxp-ec-checkout-city" required />
                    </div>
                    <div class="nxp-ec-checkout__field">
                        <label for="nxp-ec-checkout-postcode"><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_POSTCODE'); ?></label>
                        <input type="text" name="billing[postcode]" id="nxp-ec-checkout-postcode" required />
                    </div>
                    <div class="nxp-ec-checkout__field">
                        <label for="nxp-ec-checkout-region"><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_REGION'); ?></label>
                        <input type="text" name="billing[region]" id="nxp-ec-checkout-region" />
                    </div>
                    <div class="nxp-ec-checkout__field">
                        <label for="nxp-ec-checkout-country"><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_COUNTRY'); ?></label>
                        <input type="text" name="billing[country]" id="nxp-ec-checkout-country" required />
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_SHIPPING'); ?></legend>
                <p class="nxp-ec-checkout__radio-group" data-nxp-shipping-options>
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
                                    — <?php echo htmlspecialchars($currency, ENT_QUOTES, 'UTF-8'); ?>
                                    <?php echo number_format(((int) ($rule['price_cents'] ?? 0)) / 100, 2); ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </p>
            </fieldset>

            <button type="submit" class="nxp-ec-btn nxp-ec-btn--primary">
                <?php echo Text::_('COM_NXPEASYCART_CHECKOUT_SUBMIT'); ?>
            </button>
        </form>

        <aside class="nxp-ec-checkout__summary">
            <h2><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_ORDER_SUMMARY'); ?></h2>
            <div class="nxp-ec-checkout__cart" data-nxp-cart-summary>
                <?php if (empty($cart['items'])) : ?>
                    <p><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_EMPTY_CART'); ?></p>
                <?php else : ?>
                    <ul>
                        <?php foreach ($cart['items'] as $item) : ?>
                            <li>
                                <div>
                                    <strong><?php echo htmlspecialchars($item['product_title'] ?? $item['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <span class="nxp-ec-checkout__qty">× <?php echo (int) $item['qty']; ?></span>
                                </div>
                                <div class="nxp-ec-checkout__price">
                                    <?php echo htmlspecialchars($item['currency'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    <?php echo number_format(((int) $item['total_cents']) / 100, 2); ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="nxp-ec-checkout__coupon" data-nxp-coupon-section>
                        <?php if (!empty($cart['coupon'])) : ?>
                            <div class="nxp-ec-checkout__coupon-applied">
                                <span class="nxp-ec-checkout__coupon-code">
                                    <strong><?php echo htmlspecialchars($cart['coupon']['code'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong>
                                </span>
                                <button type="button" class="nxp-ec-btn nxp-ec-btn--ghost" data-nxp-coupon-remove>
                                    <?php echo Text::_('COM_NXPEASYCART_CHECKOUT_COUPON_REMOVE'); ?>
                                </button>
                            </div>
                        <?php else : ?>
                            <details class="nxp-ec-checkout__coupon-form">
                                <summary><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_COUPON_LABEL'); ?></summary>
                                <div class="nxp-ec-checkout__coupon-input-group">
                                    <div class="nxp-ec-checkout__field">
                                        <input
                                            type="text"
                                            id="nxp-ec-coupon-code"
                                            placeholder="<?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_CHECKOUT_COUPON_PLACEHOLDER'), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-nxp-coupon-input
                                            autocomplete="off"
                                        />
                                    </div>
                                    <button type="button" class="nxp-ec-btn nxp-ec-btn--ghost" data-nxp-coupon-apply>
                                        <?php echo Text::_('COM_NXPEASYCART_CHECKOUT_COUPON_APPLY'); ?>
                                    </button>
                                </div>
                                <div class="nxp-ec-checkout__coupon-message" data-nxp-coupon-message aria-live="polite"></div>
                            </details>
                        <?php endif; ?>
                    </div>

                    <div class="nxp-ec-checkout__totals">
                        <div>
                            <span><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_SUBTOTAL'); ?></span>
                            <strong>
                                <?php echo htmlspecialchars($cart['summary']['currency'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                <?php echo number_format(((int) ($cart['summary']['subtotal_cents'] ?? 0)) / 100, 2); ?>
                            </strong>
                        </div>
                        <?php if (!empty($cart['summary']['discount_cents'])) : ?>
                            <div data-nxp-discount-row>
                                <span><?php echo Text::_('COM_NXPEASYCART_CHECKOUT_DISCOUNT'); ?></span>
                                <strong>
                                    -<?php echo htmlspecialchars($cart['summary']['currency'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    <?php echo number_format(((int) ($cart['summary']['discount_cents'] ?? 0)) / 100, 2); ?>
                                </strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($cart['summary']['tax_cents'])) : ?>
                            <div>
                                <span><?php echo Text::_('COM_NXPEASYCART_CART_TAX'); ?></span>
                                <strong>
                                    <?php echo htmlspecialchars($cart['summary']['currency'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    <?php echo number_format(((int) ($cart['summary']['tax_cents'] ?? 0)) / 100, 2); ?>
                                </strong>
                            </div>
                        <?php endif; ?>
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
