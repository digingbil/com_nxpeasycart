<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/** @var array<string, mixed> $this->cart */
$cart = $this->cart ?? ['items' => [], 'summary' => []];

$items   = $cart['items']   ?? [];
$summary = $cart['summary'] ?? [];

$cartJson = htmlspecialchars(
    json_encode([
        'items'   => $items,
        'summary' => $summary,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);
?>

<section
    class="nxp-ec-cart"
    data-nxp-island="cart"
    data-nxp-cart="<?php echo $cartJson; ?>"
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
                <a class="nxp-ec-btn" href="<?php echo htmlspecialchars(\Joomla\CMS\Router\Route::_('index.php?option=com_nxpeasycart&view=category'), ENT_QUOTES, 'UTF-8'); ?>">
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
                            <th scope="col"><?php echo Text::_('COM_NXPEASYCART_CART_HEADING_QTY'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_NXPEASYCART_CART_HEADING_TOTAL'); ?></th>
                            <th scope="col" class="nxp-ec-cart__actions"></th>
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
                                <td data-label="<?php echo Text::_('COM_NXPEASYCART_CART_HEADING_QTY'); ?>">
                                    <?php echo (int) $item['qty']; ?>
                                </td>
                                <td data-label="<?php echo Text::_('COM_NXPEASYCART_CART_HEADING_TOTAL'); ?>">
                                    <?php echo htmlspecialchars($item['currency'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    <?php echo number_format(((int) $item['total_cents']) / 100, 2); ?>
                                </td>
                                <td class="nxp-ec-cart__actions">
                                    <button
                                        type="button"
                                        class="nxp-ec-link-button"
                                        data-nxp-remove="<?php echo (int) ($item['variant_id'] ?? $item['product_id']); ?>"
                                    >
                                        <?php echo Text::_('COM_NXPEASYCART_CART_REMOVE'); ?>
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
                        <div>
                            <dt><?php echo Text::_('COM_NXPEASYCART_CART_TOTAL'); ?></dt>
                            <dd class="nxp-ec-cart__summary-total">
                                <?php echo htmlspecialchars($summary['currency'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                <?php echo number_format(((int) ($summary['total_cents'] ?? 0)) / 100, 2); ?>
                            </dd>
                        </div>
                    </dl>

                    <a class="nxp-ec-btn nxp-ec-btn--primary" href="<?php echo htmlspecialchars(\Joomla\CMS\Router\Route::_('index.php?option=com_nxpeasycart&view=checkout'), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo Text::_('COM_NXPEASYCART_CART_TO_CHECKOUT'); ?>
                    </a>
                </aside>
            </div>
        <?php endif; ?>
    </noscript>
</section>
