<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\Component\Nxpeasycart\Administrator\Helper\ConfigHelper;
use Joomla\Component\Nxpeasycart\Site\Helper\RouteHelper;

/** @var array<string, mixed> $this->cart */
$cart = $this->cart ?? ['items' => [], 'summary' => []];
$theme = $this->theme ?? [];

$items   = $cart['items']   ?? [];
$summary = $cart['summary'] ?? [];
$summaryEndpoint = Route::_('index.php?option=com_nxpeasycart&task=cart.summary&format=json', false);
$removeEndpoint  = Route::_('index.php?option=com_nxpeasycart&task=cart.remove&format=json', false);
$updateEndpoint  = Route::_('index.php?option=com_nxpeasycart&task=cart.update&format=json', false);
$browseLink     = RouteHelper::getLandingRoute(false);
$checkoutLink   = RouteHelper::getCheckoutRoute(false);

$cartJson = htmlspecialchars(
    json_encode([
        'items'   => $items,
        'summary' => $summary,
        'endpoints' => [
            'summary' => $summaryEndpoint,
            'remove'  => $removeEndpoint,
            'update'  => $updateEndpoint,
        ],
        'links' => [
            'browse' => $browseLink,
            'checkout' => $checkoutLink,
        ],
        'token' => \Joomla\CMS\Session\Session::getFormToken(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);
$labels = [
    'title'         => Text::_('COM_NXPEASYCART_CART_TITLE'),
    'lead'          => Text::_('COM_NXPEASYCART_CART_LEAD'),
    'empty'         => Text::_('COM_NXPEASYCART_CART_EMPTY'),
    'continue'      => Text::_('COM_NXPEASYCART_CART_CONTINUE_BROWSING'),
    'product'       => Text::_('COM_NXPEASYCART_CART_HEADING_PRODUCT'),
    'price'         => Text::_('COM_NXPEASYCART_CART_HEADING_PRICE'),
    'qty'           => Text::_('COM_NXPEASYCART_CART_HEADING_QTY'),
    'total'         => Text::_('COM_NXPEASYCART_CART_HEADING_TOTAL'),
    'actions'       => Text::_('COM_NXPEASYCART_CART_ACTIONS'),
    'remove'        => Text::_('COM_NXPEASYCART_CART_REMOVE'),
    'summary'       => Text::_('COM_NXPEASYCART_CART_SUMMARY'),
    'subtotal'      => Text::_('COM_NXPEASYCART_CART_SUBTOTAL'),
    'tax'           => Text::_('COM_NXPEASYCART_CART_TAX'),
    'shipping'      => Text::_('COM_NXPEASYCART_CART_SHIPPING'),
    'shipping_note' => Text::_('COM_NXPEASYCART_CART_CALCULATED_AT_CHECKOUT'),
    'total_label'   => Text::_('COM_NXPEASYCART_CART_TOTAL'),
    'checkout'      => Text::_('COM_NXPEASYCART_CART_TO_CHECKOUT'),
];
$labelsJson = htmlspecialchars(
    json_encode($labels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);
$locale   = \Joomla\CMS\Factory::getApplication()->getLanguage()->getTag();
$currency = strtoupper((string) ($summary['currency'] ?? ConfigHelper::getBaseCurrency()));
$cssVars = '';
foreach (($theme['css_vars'] ?? []) as $var => $value) {
    $cssVars .= $var . ':' . $value . ';';
}
?>

<section
    class="nxp-ec-cart"
    data-nxp-island="cart"
    data-nxp-cart="<?php echo $cartJson; ?>"
    data-nxp-labels="<?php echo $labelsJson; ?>"
    data-nxp-locale="<?php echo htmlspecialchars($locale, ENT_QUOTES, 'UTF-8'); ?>"
    data-nxp-currency="<?php echo htmlspecialchars($currency, ENT_QUOTES, 'UTF-8'); ?>"
    <?php if ($cssVars !== '') : ?>style="<?php echo htmlspecialchars($cssVars, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>
>
    <noscript>
        <header class="nxp-ec-cart__header">
            <h1 class="nxp-ec-cart__title"><?php echo Text::_('COM_NXPEASYCART_CART_TITLE'); ?></h1>
            <p class="nxp-ec-cart__lead">
                <?php echo Text::_('COM_NXPEASYCART_CART_LEAD'); ?>
            </p>
        </header>

        <?php if (empty($items)) : ?>
            <div class="nxp-ec-cart__empty">
                <p><?php echo Text::_('COM_NXPEASYCART_CART_EMPTY'); ?></p>
                <a class="nxp-ec-btn" href="<?php echo htmlspecialchars($browseLink, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo Text::_('COM_NXPEASYCART_CART_CONTINUE_BROWSING'); ?>
                </a>
            </div>
        <?php else : ?>
            <div class="nxp-ec-cart__content">
                <table class="nxp-ec-cart__table">
                    <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('COM_NXPEASYCART_CART_HEADING_PRODUCT'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_NXPEASYCART_CART_HEADING_PRICE'); ?></th>
                            <th scope="col" class="nxp-ec-cart__qty"><?php echo Text::_('COM_NXPEASYCART_CART_HEADING_QTY'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_NXPEASYCART_CART_HEADING_TOTAL'); ?></th>
                            <th scope="col" class="nxp-ec-cart__actions">
                                <span class="nxp-ec-sr-only"><?php echo Text::_('COM_NXPEASYCART_CART_ACTIONS'); ?></span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item) : ?>
                            <tr>
                                <td data-label="<?php echo Text::_('COM_NXPEASYCART_CART_HEADING_PRODUCT'); ?>">
                                    <strong><?php echo htmlspecialchars($item['product_title'] ?? $item['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <?php if (!empty($item['options'])) : ?>
                                        <ul class="nxp-ec-cart__options">
                                            <?php foreach ($item['options'] as $option) : ?>
                                                <?php if (!isset($option['name'], $option['value'])) : ?>
                                                    <?php continue; ?>
                                                <?php endif; ?>
                                                <li>
                                                    <span><?php echo htmlspecialchars((string) $option['name'], ENT_QUOTES, 'UTF-8'); ?>:</span>
                                                    <?php echo htmlspecialchars((string) $option['value'], ENT_QUOTES, 'UTF-8'); ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </td>
                                <td data-label="<?php echo Text::_('COM_NXPEASYCART_CART_HEADING_PRICE'); ?>">
                                    <?php echo htmlspecialchars($item['currency'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    <?php echo number_format(((int) $item['unit_price_cents']) / 100, 2); ?>
                                </td>
                                <td class="nxp-ec-cart__qty" data-label="<?php echo Text::_('COM_NXPEASYCART_CART_HEADING_QTY'); ?>">
                                    <?php echo (int) $item['qty']; ?>
                                </td>
                                <td data-label="<?php echo Text::_('COM_NXPEASYCART_CART_HEADING_TOTAL'); ?>">
                                    <?php echo htmlspecialchars($item['currency'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    <?php echo number_format(((int) $item['total_cents']) / 100, 2); ?>
                                </td>
                                <td class="nxp-ec-cart__actions">
                                    <button
                                        type="button"
                                        class="nxp-ec-cart__remove"
                                        data-nxp-remove="<?php echo (int) ($item['variant_id'] ?? $item['product_id']); ?>"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            aria-hidden="true"
                                        >
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                            <path d="M4 7h16"></path>
                                            <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path>
                                            <path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path>
                                            <path d="M10 12l4 4m0 -4l-4 4"></path>
                                        </svg>
                                        <span class="nxp-ec-sr-only">
                                            <?php echo Text::_('COM_NXPEASYCART_CART_REMOVE'); ?>
                                        </span>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <aside class="nxp-ec-cart__summary">
                    <h2><?php echo Text::_('COM_NXPEASYCART_CART_SUMMARY'); ?></h2>
                    <dl>
                        <div>
                            <dt><?php echo Text::_('COM_NXPEASYCART_CART_SUBTOTAL'); ?></dt>
                            <dd>
                                <?php echo htmlspecialchars($summary['currency'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                <?php echo number_format(((int) ($summary['subtotal_cents'] ?? 0)) / 100, 2); ?>
                            </dd>
                        </div>
                        <div>
                            <dt><?php echo Text::_('COM_NXPEASYCART_CART_SHIPPING'); ?></dt>
                            <dd>
                                <?php echo Text::_('COM_NXPEASYCART_CART_CALCULATED_AT_CHECKOUT'); ?>
                            </dd>
                        </div>
                        <?php if (!empty($summary['tax_cents'])) : ?>
                            <div>
                                <dt><?php echo Text::_('COM_NXPEASYCART_CART_TAX'); ?></dt>
                                <dd>
                                    <?php echo htmlspecialchars($summary['currency'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    <?php echo number_format(((int) ($summary['tax_cents'] ?? 0)) / 100, 2); ?>
                                </dd>
                            </div>
                        <?php endif; ?>
                        <div>
                            <dt><?php echo Text::_('COM_NXPEASYCART_CART_TOTAL'); ?></dt>
                            <dd class="nxp-ec-cart__summary-total">
                                <?php echo htmlspecialchars($summary['currency'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                <?php echo number_format(((int) ($summary['total_cents'] ?? 0)) / 100, 2); ?>
                            </dd>
                        </div>
                    </dl>

                    <a class="nxp-ec-btn nxp-ec-btn--primary" href="<?php echo htmlspecialchars($checkoutLink, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo Text::_('COM_NXPEASYCART_CART_TO_CHECKOUT'); ?>
                    </a>
                </aside>
            </div>
        <?php endif; ?>
    </noscript>
</section>
