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
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Component\Nxpeasycart\Administrator\Helper\MoneyHelper;
use Joomla\Component\Nxpeasycart\Administrator\Helper\ConfigHelper;
use Joomla\Component\Nxpeasycart\Site\Helper\RouteHelper;

$theme          = $this->theme ?? [];
$primaryBtnClass = trim('nxp-ec-btn nxp-ec-btn--primary ' . ($theme['button_primary_extra'] ?? ''));
$cssVars        = '';
foreach (($theme['css_vars'] ?? []) as $var => $value) {
    $cssVars .= $var . ':' . $value . ';';
}

/** @var array<string, mixed> $product */
$product       = $this->product       ?? [];
$isPlaceholder = $this->isPlaceholder ?? empty($product);

if ($isPlaceholder) : ?>

    <div class="nxp-ec-product-placeholder">
        <h1 class="nxp-ec-product-placeholder__title">
            <?php echo Text::_('COM_NXPEASYCART_PRODUCT_PLACEHOLDER'); ?>
        </h1>
        <p class="nxp-ec-product-placeholder__lead">
            <?php echo Text::_('COM_NXPEASYCART_PRODUCT_PLACEHOLDER_LEAD'); ?>
        </p>
    </div>

    <?php return; ?>

<?php endif;

$price      = $product['price'] ?? ['currency' => ConfigHelper::getBaseCurrency(), 'min_cents' => 0, 'max_cents' => 0];
$currency   = ConfigHelper::getBaseCurrency();
$images     = array_values(array_filter(array_map(
    static function ($image) {
        if (!\is_string($image)) {
            return null;
        }

        $trimmed = trim($image);

        return $trimmed !== '' ? $trimmed : null;
    },
    $product['images'] ?? []
)));
$variants   = $product['variants']   ?? [];
$categories = $product['categories'] ?? [];
$isOutOfStock = !empty($product['out_of_stock']);

// Locale is auto-resolved by MoneyHelper (checks store override, then Joomla language)
$locale = MoneyHelper::resolveLocale();

$primaryImage    = $images[0] ?? '';
// Regular prices (for strikethrough when on sale)
$regularMin      = (int) ($price['min_cents'] ?? 0);
$regularMax      = (int) ($price['max_cents'] ?? 0);
// Effective prices (sale price when active, otherwise regular)
$effectiveMin    = (int) ($price['effective_min_cents'] ?? $regularMin);
$effectiveMax    = (int) ($price['effective_max_cents'] ?? $regularMax);
$hasSale         = !empty($price['any_sale_active']);
$regularPriceLabel = null;

// Format regular price label (for strikethrough)
$regularPriceFormatted = $regularMin === $regularMax
    ? MoneyHelper::format($regularMin, $currency)
    : Text::sprintf(
        'COM_NXPEASYCART_PRODUCT_PRICE_RANGE',
        MoneyHelper::format($regularMin, $currency),
        MoneyHelper::format($regularMax, $currency)
    );

// Format effective/sale price label
$priceLabel = $effectiveMin === $effectiveMax
    ? MoneyHelper::format($effectiveMin, $currency)
    : Text::sprintf(
        'COM_NXPEASYCART_PRODUCT_PRICE_RANGE',
        MoneyHelper::format($effectiveMin, $currency),
        MoneyHelper::format($effectiveMax, $currency)
    );

// If any variant has an active sale, set the regular price label for strikethrough
if ($hasSale) {
    $regularPriceLabel = $regularPriceFormatted;
}

$preparedLongDescription = '';

if ($product['long_desc'] !== '') {
    $rawLongDescription = HTMLHelper::_('content.prepare', $product['long_desc'], '', 'com_nxpeasycart.product');

    $safeHtmlFilter        = InputFilter::getInstance(
        ['a', 'p', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'blockquote'],
        ['href', 'title', 'target', 'rel'],
        1,
        1,
        1
    );
    $preparedLongDescription = $safeHtmlFilter->clean($rawLongDescription);
}

$galleryPayload = [
    'images' => $images,
    'title'  => (string) ($product['title'] ?? ''),
];
$galleryJson = htmlspecialchars(
    json_encode($galleryPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);

$variantPayload = array_map(
    static function (array $variant): array {
        $priceCents         = (int) ($variant['price_cents'] ?? 0);
        $salePriceCents     = $variant['sale_price_cents'] ?? null;
        $effectivePriceCents = (int) ($variant['effective_price_cents'] ?? $priceCents);
        $saleActive         = !empty($variant['sale_active']);
        $discountPercent    = $variant['discount_percent'] ?? null;
        $currency           = ConfigHelper::getBaseCurrency();

        return [
            'id'                    => (int) ($variant['id'] ?? 0),
            'sku'                   => (string) ($variant['sku'] ?? ''),
            'ean'                   => isset($variant['ean']) ? (string) $variant['ean'] : null,
            'price_cents'           => $priceCents,
            'sale_price_cents'      => $salePriceCents,
            'effective_price_cents' => $effectivePriceCents,
            'sale_active'           => $saleActive,
            'discount_percent'      => $discountPercent,
            'currency'              => $currency,
            'price_label'           => MoneyHelper::format($effectivePriceCents, $currency),
            'regular_price_label'   => $saleActive ? MoneyHelper::format($priceCents, $currency) : null,
            'stock'                 => (int) ($variant['stock'] ?? 0),
            'options'               => $variant['options'] ?? [],
            'weight'                => $variant['weight'] ?? null,
        ];
    },
    $variants
);

$payload = [
    'product' => [
        'id'             => (int) ($product['id'] ?? 0),
        'title'          => (string) ($product['title'] ?? ''),
        'short_desc'     => (string) ($product['short_desc'] ?? ''),
        'long_desc_html' => $preparedLongDescription,
        'status'         => (int) ($product['status'] ?? ($product['active'] ? 1 : 0)),
        'out_of_stock'   => $isOutOfStock,
        'images'         => $images,
        'categories'     => $categories,
        'price'          => [
            'currency'       => $currency,
            'min_cents'      => $priceMin,
            'max_cents'      => $priceMax,
            'label'          => $priceLabel,
            'regular_label'  => $regularPriceLabel,
            'has_sale'       => $hasSale,
        ],
    ],
    'variants' => $variantPayload,
    'labels' => [
        'add_to_cart'      => Text::_('COM_NXPEASYCART_PRODUCT_ADD_TO_CART'),
        'select_variant'   => Text::_('COM_NXPEASYCART_PRODUCT_SELECT_VARIANT'),
        'out_of_stock'     => Text::_('COM_NXPEASYCART_PRODUCT_OUT_OF_STOCK'),
        'added'            => Text::_('COM_NXPEASYCART_PRODUCT_ADDED_TO_CART'),
        'view_cart'        => Text::_('COM_NXPEASYCART_PRODUCT_VIEW_CART'),
        'qty_label'        => Text::_('COM_NXPEASYCART_PRODUCT_QUANTITY_LABEL'),
        'error_generic'    => Text::_('COM_NXPEASYCART_PRODUCT_ADD_TO_CART_ERROR'),
        'variants_heading' => Text::_('COM_NXPEASYCART_PRODUCT_VARIANTS_HEADING'),
        'variant_sku'      => Text::_('COM_NXPEASYCART_PRODUCT_VARIANT_SKU_LABEL'),
        'variant_price'    => Text::_('COM_NXPEASYCART_PRODUCT_VARIANT_PRICE_LABEL'),
        'variant_stock'    => Text::_('COM_NXPEASYCART_PRODUCT_VARIANT_STOCK_LABEL'),
        'variant_options'  => Text::_('COM_NXPEASYCART_PRODUCT_VARIANT_OPTIONS_LABEL'),
        'variant_none'     => Text::_('COM_NXPEASYCART_PRODUCT_VARIANT_NONE'),
        'sale_badge'       => Text::_('COM_NXPEASYCART_PRODUCT_SALE_BADGE'),
        'discount_off'     => Text::_('COM_NXPEASYCART_PRODUCT_DISCOUNT_OFF'),
    ],
    'links' => [
        'cart'     => RouteHelper::getCartRoute(false),
        'checkout' => RouteHelper::getCheckoutRoute(false),
    ],
    'endpoints' => [
        'add'      => Route::_('index.php?option=com_nxpeasycart&task=cart.add&format=json', false),
        'summary'  => Route::_('index.php?option=com_nxpeasycart&task=cart.summary&format=json', false),
    ],
    'token' => Session::getFormToken(),
    'primary_alt' => Text::sprintf('COM_NXPEASYCART_PRODUCT_PRIMARY_IMAGE_ALT', $product['title']),
];

$payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$payloadJsonAttr = htmlspecialchars($payloadJson, ENT_QUOTES, 'UTF-8');
?>

<article
    class="nxp-ec-product"
    <?php if ($cssVars !== '') : ?>style="<?php echo htmlspecialchars($cssVars, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>
>
    <div class="nxp-ec-product__media" data-nxp-gallery="<?php echo $galleryJson; ?>">
        <?php if ($primaryImage) : ?>
            <button
                type="button"
                class="nxp-ec-product__figure"
                data-nxp-gallery-trigger
                aria-label="<?php echo htmlspecialchars(Text::sprintf('COM_NXPEASYCART_PRODUCT_PRIMARY_IMAGE_ALT', $product['title']), ENT_QUOTES, 'UTF-8'); ?>"
            >
                <img
                    src="<?php echo htmlspecialchars($primaryImage, ENT_QUOTES, 'UTF-8'); ?>"
                    alt="<?php echo htmlspecialchars(Text::sprintf('COM_NXPEASYCART_PRODUCT_PRIMARY_IMAGE_ALT', $product['title']), ENT_QUOTES, 'UTF-8'); ?>"
                    loading="lazy"
                    data-nxp-gallery-main
                />
            </button>
        <?php endif; ?>

        <?php if (count($images) > 1) : ?>
            <div class="nxp-ec-product__thumbs" aria-label="<?php echo htmlspecialchars(Text::_('COM_NXPEASYCART_PRODUCT_GALLERY_ARIA'), ENT_QUOTES, 'UTF-8'); ?>">
                <?php foreach ($images as $index => $image) : ?>
                    <button
                        type="button"
                        class="nxp-ec-product__thumb"
                        data-nxp-gallery-thumb="<?php echo (int) $index; ?>"
                        aria-label="<?php echo htmlspecialchars(Text::sprintf('COM_NXPEASYCART_PRODUCT_PRIMARY_IMAGE_ALT', $product['title']), ENT_QUOTES, 'UTF-8'); ?>"
                    >
                        <img
                            src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>"
                            alt="<?php echo htmlspecialchars($product['title'], ENT_QUOTES, 'UTF-8'); ?>"
                            loading="lazy"
                        />
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="nxp-ec-product__summary">
        <h1 class="nxp-ec-product__title">
            <?php echo htmlspecialchars($product['title'], ENT_QUOTES, 'UTF-8'); ?>
        </h1>

        <?php if (!empty($categories)) : ?>
            <ul class="nxp-ec-product__categories">
                <?php foreach ($categories as $category) : ?>
                    <li>
                        <?php echo htmlspecialchars($category['title'], ENT_QUOTES, 'UTF-8'); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <div class="nxp-ec-product__price<?php echo $hasSale ? ' nxp-ec-product__price--sale' : ''; ?>">
            <?php if ($hasSale && $regularPriceLabel !== null) : ?>
                <span class="nxp-ec-product__sale-badge"><?php echo Text::_('COM_NXPEASYCART_PRODUCT_SALE_BADGE'); ?></span>
                <span class="nxp-ec-product__regular-price"><?php echo htmlspecialchars($regularPriceLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="nxp-ec-product__sale-price"><?php echo htmlspecialchars($priceLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php else : ?>
                <?php echo htmlspecialchars($priceLabel, ENT_QUOTES, 'UTF-8'); ?>
            <?php endif; ?>
        </div>

        <?php if ($isOutOfStock) : ?>
            <noscript>
                <p class="nxp-ec-product__message nxp-ec-product__message--alert">
                    <?php echo Text::_('COM_NXPEASYCART_PRODUCT_OUT_OF_STOCK'); ?>
                </p>
            </noscript>
        <?php endif; ?>

        <?php if (!empty($product['short_desc'])) : ?>
            <p class="nxp-ec-product__intro">
                <?php echo htmlspecialchars($product['short_desc'], ENT_QUOTES, 'UTF-8'); ?>
            </p>
        <?php endif; ?>
        <div
            class="nxp-ec-product__actions"
            data-nxp-island="product"
            data-nxp-product="<?php echo $payloadJsonAttr; ?>"
            data-nxp-locale="<?php echo htmlspecialchars($locale, ENT_QUOTES, 'UTF-8'); ?>"
            data-nxp-currency="<?php echo htmlspecialchars($currency, ENT_QUOTES, 'UTF-8'); ?>"
        >
            <button class="<?php echo htmlspecialchars($primaryBtnClass, ENT_QUOTES, 'UTF-8'); ?> nxp-ec-product__buy<?php echo $isOutOfStock ? ' is-disabled is-out-of-stock' : ''; ?>" type="button" <?php echo $isOutOfStock ? 'disabled' : ''; ?>>
                <?php echo Text::_('COM_NXPEASYCART_PRODUCT_ADD_TO_CART'); ?>
            </button>
        </div>
    </div>

    <?php if ($preparedLongDescription !== '') : ?>
        <section class="nxp-ec-product__description">
            <?php echo $preparedLongDescription; ?>
        </section>
    <?php endif; ?>

    <?php if (!empty($variants)) : ?>
        <section class="nxp-ec-product__variants">
            <h2 class="nxp-ec-product__variants-title">
                <?php echo Text::_('COM_NXPEASYCART_PRODUCT_VARIANTS_HEADING'); ?>
            </h2>

            <table class="nxp-ec-product__variants-table">
                <thead>
                    <tr>
                        <th scope="col"><?php echo Text::_('COM_NXPEASYCART_PRODUCT_VARIANT_SKU_LABEL'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_NXPEASYCART_PRODUCT_VARIANT_PRICE_LABEL'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_NXPEASYCART_PRODUCT_VARIANT_STOCK_LABEL'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_NXPEASYCART_PRODUCT_VARIANT_OPTIONS_LABEL'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($variants as $variant) :
                        $variantSaleActive = !empty($variant['sale_active']);
                        $variantEffectivePrice = (int) ($variant['effective_price_cents'] ?? $variant['price_cents']);
                        $variantRegularPrice = (int) $variant['price_cents'];
                    ?>
                        <tr<?php echo $variantSaleActive ? ' class="nxp-ec-product__variant-row--sale"' : ''; ?>>
                            <td><?php echo htmlspecialchars($variant['sku'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="nxp-ec-product__variant-price<?php echo $variantSaleActive ? ' nxp-ec-product__variant-price--sale' : ''; ?>">
                                <?php if ($variantSaleActive) : ?>
                                    <span class="nxp-ec-product__variant-regular-price"><?php echo htmlspecialchars(MoneyHelper::format($variantRegularPrice, ConfigHelper::getBaseCurrency(), $locale), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="nxp-ec-product__variant-sale-price"><?php echo htmlspecialchars(MoneyHelper::format($variantEffectivePrice, ConfigHelper::getBaseCurrency(), $locale), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php if (!empty($variant['discount_percent'])) : ?>
                                        <span class="nxp-ec-product__variant-discount">-<?php echo (int) $variant['discount_percent']; ?>%</span>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <?php echo htmlspecialchars(MoneyHelper::format($variantRegularPrice, ConfigHelper::getBaseCurrency(), $locale), ENT_QUOTES, 'UTF-8'); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo (int) $variant['stock']; ?></td>
                            <td>
                                <?php if (!empty($variant['options'])) : ?>
                                    <ul class="nxp-ec-product__variant-options">
                                        <?php foreach ($variant['options'] as $option) : ?>
                                            <?php if (!isset($option['name'], $option['value'])) : ?>
                                                <?php continue; ?>
                                            <?php endif; ?>
                                            <li>
                                                <strong><?php echo htmlspecialchars((string) $option['name'], ENT_QUOTES, 'UTF-8'); ?>:</strong>
                                                <?php echo htmlspecialchars((string) $option['value'], ENT_QUOTES, 'UTF-8'); ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else : ?>
                                    <span class="nxp-ec-product__variant-none">
                                        <?php echo Text::_('COM_NXPEASYCART_PRODUCT_VARIANT_NONE'); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>
</article>
<?php
// Schema.org Product structured data with gtin13 when EAN is available
$schemaOffers = [];
foreach ($variants as $variant) {
    // Use effective price (considers sale pricing)
    $schemaEffectivePrice = (int) ($variant['effective_price_cents'] ?? $variant['price_cents'] ?? 0);
    $schemaRegularPrice   = (int) ($variant['price_cents'] ?? 0);
    $schemaSaleActive     = !empty($variant['sale_active']);

    $offer = [
        '@type'         => 'Offer',
        'sku'           => (string) ($variant['sku'] ?? ''),
        'price'         => number_format($schemaEffectivePrice / 100, 2, '.', ''),
        'priceCurrency' => $currency,
        'availability'  => ((int) ($variant['stock'] ?? 0)) > 0
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock',
    ];

    // Add priceSpecification for sale price display with strikethrough regular price
    if ($schemaSaleActive && $schemaRegularPrice > $schemaEffectivePrice) {
        $offer['priceSpecification'] = [
            '@type'                 => 'UnitPriceSpecification',
            'price'                 => number_format($schemaEffectivePrice / 100, 2, '.', ''),
            'priceCurrency'         => $currency,
            'priceType'             => 'https://schema.org/SalePrice',
        ];
    }

    // Add gtin13 if EAN-13 is present (13 digits)
    $ean = $variant['ean'] ?? null;
    if ($ean !== null && strlen((string) $ean) === 13) {
        $offer['gtin13'] = (string) $ean;
    } elseif ($ean !== null && strlen((string) $ean) === 8) {
        // EAN-8 can be expressed as gtin8
        $offer['gtin8'] = (string) $ean;
    }

    $schemaOffers[] = $offer;
}

$schemaProduct = [
    '@context'    => 'https://schema.org',
    '@type'       => 'Product',
    'name'        => (string) ($product['title'] ?? ''),
    'description' => (string) ($product['short_desc'] ?? ''),
];

if (!empty($primaryImage)) {
    $schemaProduct['image'] = $primaryImage;
}

if (!empty($product['sku'])) {
    $schemaProduct['sku'] = (string) $product['sku'];
}

// If there's only one variant with EAN, add gtin at product level too
if (count($variants) === 1 && !empty($variants[0]['ean'])) {
    $singleEan = (string) $variants[0]['ean'];
    if (strlen($singleEan) === 13) {
        $schemaProduct['gtin13'] = $singleEan;
    } elseif (strlen($singleEan) === 8) {
        $schemaProduct['gtin8'] = $singleEan;
    }
}

if (!empty($schemaOffers)) {
    $schemaProduct['offers'] = count($schemaOffers) === 1 ? $schemaOffers[0] : $schemaOffers;
}

$schemaJson = json_encode($schemaProduct, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
<script type="application/ld+json">
<?php echo $schemaJson; ?>
</script>
