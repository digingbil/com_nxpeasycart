<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Nxpeasycart\Administrator\Helper\ConfigHelper;
use Joomla\Component\Nxpeasycart\Site\Helper\RouteHelper;

$items   = isset($cart['items']) && \is_array($cart['items']) ? $cart['items'] : [];
$summary = \is_array($cart['summary'] ?? null) ? $cart['summary'] : [];

$currency   = strtoupper((string) ($summary['currency'] ?? ConfigHelper::getBaseCurrency()));
$totalCents = (int) ($summary['total_cents'] ?? 0);

$itemCount = 0;

foreach ($items as $item) {
    $itemCount += (int) ($item['qty'] ?? 0);
}

$language = Factory::getApplication()->getLanguage();
$locale   = str_replace('-', '_', $language->getTag() ?: 'en_GB');

$formatMoney = static function (int $cents) use ($currency, $locale): string {
    $amount = $cents / 100;

    if (class_exists('NumberFormatter', false)) {
        try {
            $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
            $formatted = $formatter->formatCurrency($amount, $currency);

            if ($formatted !== false) {
                return (string) $formatted;
            }
        } catch (\Throwable $exception) {
            // Continue to fallback.
        }
    }

    return sprintf('%s %.2f', $currency, $amount);
};

$formattedTotal = $formatMoney($totalCents);
$cartLink       = RouteHelper::getCartRoute();
$checkoutLink   = RouteHelper::getCheckoutRoute();
$summaryLink    = Route::_('index.php?option=com_nxpeasycart&task=cart.summary&format=json', false);

if (!str_starts_with($summaryLink, 'http://') && !str_starts_with($summaryLink, 'https://')) {
    $summaryLink = rtrim(Uri::root(), '/') . '/' . ltrim($summaryLink, '/');
}

$payload = [
    'count'      => $itemCount,
    'total_cents' => $totalCents,
    'currency'   => $currency,
    'links'      => [
        'cart'     => $cartLink,
        'checkout' => $checkoutLink,
    ],
    'labels'     => [
        'title'        => Text::_('MOD_NXPEASYCART_CART_TITLE'),
        'empty'        => Text::_('MOD_NXPEASYCART_CART_EMPTY'),
        'items_single' => Text::_('MOD_NXPEASYCART_CART_ITEM_SINGLE'),
        'items_plural' => Text::_('MOD_NXPEASYCART_CART_ITEM_PLURAL'),
        'total_label'  => Text::_('MOD_NXPEASYCART_CART_TOTAL_LABEL'),
        'view_cart'    => Text::_('MOD_NXPEASYCART_CART_VIEW_CART'),
        'checkout'     => Text::_('MOD_NXPEASYCART_CART_CHECKOUT'),
    ],
    'endpoints'  => [
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

$countLabel = $itemCount === 1
    ? Text::_('MOD_NXPEASYCART_CART_ITEM_SINGLE')
    : Text::sprintf('MOD_NXPEASYCART_CART_ITEM_PLURAL', $itemCount);
?>

<section
    class="nxp-ec-cart-summary"
    data-nxp-island="cart-summary"
    data-nxp-cart-summary="<?php echo $payloadJson; ?>"
    data-nxp-locale="<?php echo htmlspecialchars($locale, ENT_QUOTES, 'UTF-8'); ?>"
    data-nxp-currency="<?php echo htmlspecialchars($currency, ENT_QUOTES, 'UTF-8'); ?>"
    <?php if ($cssVars !== '') : ?>style="<?php echo htmlspecialchars($cssVars, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>
>
    <!-- Static fallback: shows until Vue island mounts, or when JS fails to load -->
    <div class="nxp-ec-cart-summary__inner nxp-ec-cart-summary__fallback">
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
</section>
