<?php

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

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

$formatMoney = static function (int $cents, string $currency): string {
    $amount = $cents / 100;

    if (class_exists('NumberFormatter', false)) {
        try {
            $formatter = new \NumberFormatter(null, \NumberFormatter::CURRENCY);
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

$preparedLongDescription = $product['long_desc'] !== '' ? HTMLHelper::_('content.prepare', $product['long_desc'], '', 'com_nxpeasycart.product') : '';
?>

<article class="nxp-ec-product">
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

        <button class="nxp-ec-btn nxp-ec-btn--primary nxp-ec-product__buy" type="button">
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
                                    <span class="nxp-ec-product__variant-none">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>
</article>
