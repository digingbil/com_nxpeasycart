<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

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

$price      = $product['price'] ?? ['currency' => 'USD', 'min_cents' => 0, 'max_cents' => 0];
$currency   = strtoupper((string) ($price['currency'] ?? 'USD'));
$images     = $product['images']     ?? [];
$variants   = $product['variants']   ?? [];
$categories = $product['categories'] ?? [];

$language = Factory::getApplication()->getLanguage();
$locale   = str_replace('-', '_', $language->getTag() ?: 'en_GB');

$formatMoney = static function (int $cents, string $currency) use ($locale): string {
    $amount = $cents / 100;

    if (class_exists('NumberFormatter', false)) {
        try {
            $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
            $formatted = $formatter->formatCurrency($amount, $currency);

            if ($formatted !== false) {
                return (string) $formatted;
            }
        } catch (\Throwable $exception) {
            // Fallback below.
        }
    }

    return sprintf('%s %.2f', $currency, $amount);
};

$primaryImage = $images[0] ?? '';
$priceMin     = (int) ($price['min_cents'] ?? 0);
$priceMax     = (int) ($price['max_cents'] ?? 0);
$priceLabel   = $priceMin === $priceMax
    ? $formatMoney($priceMin, $currency)
    : Text::sprintf('COM_NXPEASYCART_PRODUCT_PRICE_RANGE', $formatMoney($priceMin, $currency), $formatMoney($priceMax, $currency));

$preparedLongDescription = $product['long_desc'] !== ''
    ? HTMLHelper::_('content.prepare', $product['long_desc'], '', 'com_nxpeasycart.product')
    : '';

$variantPayload = array_map(
    static function (array $variant) use ($formatMoney): array {
        $priceCents = (int) ($variant['price_cents'] ?? 0);
        $currency   = strtoupper((string) ($variant['currency'] ?? 'USD'));

        return [
            'id'           => (int) ($variant['id'] ?? 0),
            'sku'          => (string) ($variant['sku'] ?? ''),
            'price_cents'  => $priceCents,
            'currency'     => $currency,
            'price_label'  => $formatMoney($priceCents, $currency),
            'stock'        => (int) ($variant['stock'] ?? 0),
            'options'      => $variant['options'] ?? [],
            'weight'       => $variant['weight'] ?? null,
        ];
    },
    $variants
);

$payload = [
    'product' => [
        'id'          => (int) ($product['id'] ?? 0),
        'slug'        => (string) ($product['slug'] ?? ''),
        'title'       => (string) ($product['title'] ?? ''),
        'short_desc'  => (string) ($product['short_desc'] ?? ''),
        'long_desc'   => (string) ($product['long_desc'] ?? ''),
        'long_desc_html' => $preparedLongDescription,
        'images'      => $images,
        'categories'  => $categories,
        'price'       => [
            'currency'   => $currency,
            'min_cents'  => $priceMin,
            'max_cents'  => $priceMax,
            'label'      => $priceLabel,
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
    ],
    'links' => [
        'cart'     => Route::_('index.php?option=com_nxpeasycart&view=cart'),
        'checkout' => Route::_('index.php?option=com_nxpeasycart&view=checkout'),
    ],
    'endpoints' => [
        'add'      => Route::_('index.php?option=com_nxpeasycart&task=cart.add&format=json', false),
        'summary'  => Route::_('index.php?option=com_nxpeasycart&task=cart.summary&format=json', false),
    ],
    'token' => Session::getFormToken(),
    'primary_alt' => Text::sprintf('COM_NXPEASYCART_PRODUCT_PRIMARY_IMAGE_ALT', $product['title']),
];

$payloadJson = htmlspecialchars(
    json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);
?>

<article
    class="nxp-ec-product"
    data-nxp-island="product"
    data-nxp-product="<?php echo $payloadJson; ?>"
    <?php if ($cssVars !== '') : ?>style="<?php echo htmlspecialchars($cssVars, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>
>
    <div class="nxp-ec-product__media">
        <?php if ($primaryImage) : ?>
            <figure class="nxp-ec-product__figure">
                <img
                    src="<?php echo htmlspecialchars($primaryImage, ENT_QUOTES, 'UTF-8'); ?>"
                    alt="<?php echo htmlspecialchars(Text::sprintf('COM_NXPEASYCART_PRODUCT_PRIMARY_IMAGE_ALT', $product['title']), ENT_QUOTES, 'UTF-8'); ?>"
                    loading="lazy"
                />
            </figure>
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

        <div class="nxp-ec-product__price">
            <?php echo htmlspecialchars($priceLabel, ENT_QUOTES, 'UTF-8'); ?>
        </div>

        <?php if (!empty($product['short_desc'])) : ?>
            <p class="nxp-ec-product__intro">
                <?php echo htmlspecialchars($product['short_desc'], ENT_QUOTES, 'UTF-8'); ?>
            </p>
        <?php endif; ?>

        <button class="<?php echo htmlspecialchars($primaryBtnClass, ENT_QUOTES, 'UTF-8'); ?> nxp-ec-product__buy" type="button">
            <?php echo Text::_('COM_NXPEASYCART_PRODUCT_ADD_TO_CART'); ?>
        </button>
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
                    <?php foreach ($variants as $variant) : ?>
                        <tr>
                            <td><?php echo htmlspecialchars($variant['sku'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($formatMoney((int) $variant['price_cents'], $variant['currency']), ENT_QUOTES, 'UTF-8'); ?></td>
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
