<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  mod_nxpeasycart_cart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Nxpeasycart\Administrator\Helper\ConfigHelper;
use Joomla\Component\Nxpeasycart\Administrator\Helper\MoneyHelper;
use Joomla\Component\Nxpeasycart\Site\Helper\RouteHelper;

$items   = isset($cart['items']) && \is_array($cart['items']) ? $cart['items'] : [];
$summary = \is_array($cart['summary'] ?? null) ? $cart['summary'] : [];

$currency   = strtoupper((string) ($summary['currency'] ?? ConfigHelper::getBaseCurrency()));
$totalCents = (int) ($summary['total_cents'] ?? 0);

$itemCount = 0;

foreach ($items as $item) {
    $itemCount += (int) ($item['qty'] ?? 0);
}

// Locale is auto-resolved by MoneyHelper (checks store override, then Joomla language)
$locale = MoneyHelper::resolveLocale();

$formatMoney = static function (int $cents) use ($currency): string {
    return MoneyHelper::format($cents, $currency);
};

$formattedTotal = $formatMoney($totalCents);
// Use xhtml=false for JSON payload; htmlspecialchars handles HTML href escaping
$cartLink       = RouteHelper::getCartRoute(false);
$checkoutLink   = RouteHelper::getCheckoutRoute(false);
$summaryLink    = Route::_('index.php?option=com_nxpeasycart&task=cart.summary&format=json', false);

if (!str_starts_with($summaryLink, 'http://') && !str_starts_with($summaryLink, 'https://')) {
    $summaryLink = rtrim(Uri::root(), '/') . '/' . ltrim($summaryLink, '/');
}

// Display mode: 'default' = inline on desktop, floating on mobile (<992px)
//               'floating' = always floating only
$displayMode = $displayMode ?? 'default';
$isCheckoutPage = $isCheckoutPage ?? false;

$payload = [
    'count'        => $itemCount,
    'total_cents'  => $totalCents,
    'currency'     => $currency,
    'display_mode' => $displayMode,
    'is_checkout'  => $isCheckoutPage,
    'links'        => [
        'cart'     => $cartLink,
        'checkout' => $checkoutLink,
    ],
    'labels'       => [
        'title'        => Text::_('MOD_NXPEASYCART_CART_TITLE'),
        'empty'        => Text::_('MOD_NXPEASYCART_CART_EMPTY'),
        'item_single'  => Text::_('MOD_NXPEASYCART_CART_ITEM_SINGLE'),
        'item_plural'  => Text::_('MOD_NXPEASYCART_CART_ITEM_PLURAL'),
        'total_label'  => Text::_('MOD_NXPEASYCART_CART_TOTAL_LABEL'),
        'view_cart'    => Text::_('MOD_NXPEASYCART_CART_VIEW_CART'),
        'checkout'     => Text::_('MOD_NXPEASYCART_CART_CHECKOUT'),
    ],
    'endpoints'    => [
        'summary' => $summaryLink,
    ],
];

$payloadJson = htmlspecialchars(
    json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);
$cssVars = '';
if (!empty($theme['css_vars']) && \is_array($theme['css_vars'])) {
    foreach ($theme['css_vars'] as $var => $value) {
        $cssVars .= $var . ':' . $value . ';';
    }
}

$countLabel = $itemCount . ' ' . ($itemCount === 1
    ? Text::_('MOD_NXPEASYCART_CART_ITEM_SINGLE')
    : Text::_('MOD_NXPEASYCART_CART_ITEM_PLURAL'));

// Build CSS classes based on display mode
$wrapperClasses = ['nxp-ec-cart-summary'];
if ($displayMode === 'floating') {
    $wrapperClasses[] = 'nxp-ec-cart-summary--floating-only';
} else {
    // Default mode: inline on desktop, auto-floating on mobile
    $wrapperClasses[] = 'nxp-ec-cart-summary--responsive';
}
if ($isCheckoutPage) {
    $wrapperClasses[] = 'nxp-ec-cart-summary--hidden-checkout';
}
?>

<section
    class="<?php echo implode(' ', $wrapperClasses); ?>"
    data-nxp-island="cart-summary"
    data-nxp-cart-summary="<?php echo $payloadJson; ?>"
    data-nxp-locale="<?php echo htmlspecialchars($locale, ENT_QUOTES, 'UTF-8'); ?>"
    data-nxp-currency="<?php echo htmlspecialchars($currency, ENT_QUOTES, 'UTF-8'); ?>"
    data-nxp-display-mode="<?php echo htmlspecialchars($displayMode, ENT_QUOTES, 'UTF-8'); ?>"
    <?php if ($cssVars !== '') : ?>style="<?php echo htmlspecialchars($cssVars, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>
>
    <!-- Inline view: shows on desktop in default mode, hidden in floating-only mode -->
    <div class="nxp-ec-cart-summary__inline nxp-ec-cart-summary__fallback">
        <?php if ($itemCount === 0) : ?>
            <p class="nxp-ec-cart-summary__empty">
                <?php echo Text::_('MOD_NXPEASYCART_CART_EMPTY'); ?>
            </p>
        <?php else : ?>
            <a href="<?php echo htmlspecialchars($cartLink, ENT_QUOTES, 'UTF-8'); ?>" class="nxp-ec-cart-summary__link">
                <span class="nxp-ec-cart-summary__count"><?php echo htmlspecialchars($countLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="nxp-ec-cart-summary__total">
                    <?php echo Text::_('MOD_NXPEASYCART_CART_TOTAL_LABEL'); ?>: <?php echo htmlspecialchars($formattedTotal, ENT_QUOTES, 'UTF-8'); ?>
                </span>
            </a>
            <div class="nxp-ec-cart-summary__actions">
                <a class="nxp-ec-btn nxp-ec-btn--ghost" href="<?php echo htmlspecialchars($cartLink, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo Text::_('MOD_NXPEASYCART_CART_VIEW_CART'); ?>
                </a>
                <a class="nxp-ec-btn nxp-ec-btn--primary" href="<?php echo htmlspecialchars($checkoutLink, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo Text::_('MOD_NXPEASYCART_CART_CHECKOUT'); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Floating bar: shows on mobile in default mode, always in floating-only mode -->
    <?php if ($itemCount > 0) : ?>
    <div class="nxp-ec-cart-summary__floating nxp-ec-cart-summary__floating-fallback">
        <div class="nxp-ec-cart-summary__floating-inner">
            <a href="<?php echo htmlspecialchars($cartLink, ENT_QUOTES, 'UTF-8'); ?>" class="nxp-ec-cart-summary__floating-info">
                <span class="nxp-ec-cart-summary__floating-badge"><?php echo $itemCount; ?></span>
                <span class="nxp-ec-cart-summary__floating-total"><?php echo htmlspecialchars($formattedTotal, ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
            <div class="nxp-ec-cart-summary__floating-actions">
                <a class="nxp-ec-btn nxp-ec-btn--ghost nxp-ec-cart-summary__floating-cta" href="<?php echo htmlspecialchars($cartLink, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo Text::_('MOD_NXPEASYCART_CART_VIEW_CART'); ?>
                </a>
                <a class="nxp-ec-btn nxp-ec-btn--primary nxp-ec-cart-summary__floating-cta" href="<?php echo htmlspecialchars($checkoutLink, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo Text::_('MOD_NXPEASYCART_CART_CHECKOUT'); ?>
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</section>
