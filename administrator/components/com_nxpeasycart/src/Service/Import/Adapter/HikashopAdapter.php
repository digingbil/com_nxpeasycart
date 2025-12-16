<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Administrator\Service\Import\Adapter;

\defined('_JEXEC') or die;

/**
 * HikaShop CSV import adapter.
 *
 * Handles HikaShop's export format with characteristic columns for variant options.
 * HikaShop uses characteristic_N columns without explicit names, requiring inference.
 *
 * @since 0.3.0
 */
class HikashopAdapter extends AbstractPlatformAdapter
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'hikashop';
    }

    /**
     * {@inheritdoc}
     */
    public function getDisplayName(): string
    {
        return 'HikaShop';
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultMapping(): array
    {
        return [
            // Product fields
            'product_id'          => 'product.original_id',
            'product_code'        => 'variant.sku',
            'product_name'        => 'product.title',
            'product_alias'       => 'product.slug',
            'product_description' => 'product.long_desc',
            'product_meta_description' => 'product.short_desc',
            'product_published'   => 'product.active',
            'product_categories'  => 'product.categories',
            'product_images'      => 'product.images',
            'product_type'        => 'product.type',
            // Variant/price fields
            'product_price'       => 'variant.price',
            'variant_price'       => 'variant.variant_price',
            'product_sale_price'  => 'variant.sale_price',
            'product_sale_start'  => 'variant.sale_start',
            'product_sale_end'    => 'variant.sale_end',
            'product_quantity'    => 'variant.stock',
            'variant_quantity'    => 'variant.variant_stock',
            'product_weight'      => 'variant.weight',
            'variant_code'        => 'variant.variant_sku',
            'characteristic_1'    => 'variant.char1',
            'characteristic_2'    => 'variant.char2',
            'characteristic_3'    => 'variant.char3',
            'variant_id'          => 'variant.original_id',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getSignatureHeaders(): array
    {
        // Unique combination that identifies HikaShop format
        return ['product_id', 'product_code', 'product_name', 'product_price', 'product_quantity'];
    }

    /**
     * {@inheritdoc}
     */
    public function normalizeRow(array $row, array $mapping): array
    {
        $normalized = $this->createEmptyNormalized();

        // Product data
        $normalized['product']['original_id'] = $this->getValue($row, 'product_id', '');
        $normalized['product']['title'] = $this->getValue($row, 'product_name', '');
        $normalized['product']['slug'] = $this->getValue($row, 'product_alias', '') ?: $this->generateSlug($normalized['product']['title']);
        $normalized['product']['short_desc'] = $this->sanitizeHtml($this->getValue($row, 'product_meta_description', ''));
        $normalized['product']['long_desc'] = $this->sanitizeHtml($this->getValue($row, 'product_description', ''));
        $normalized['product']['active'] = $this->parseBoolean($this->getValue($row, 'product_published', '1'));
        $normalized['product']['featured'] = false;

        // Determine product type
        $hikashopType = strtolower($this->getValue($row, 'product_type', 'main'));
        $normalized['product']['product_type'] = ($hikashopType === 'file' || $hikashopType === 'digital') ? 'digital' : 'physical';

        // Categories (comma-separated)
        $categoriesStr = $this->getValue($row, 'product_categories', '');

        if ($categoriesStr !== '') {
            $normalized['product']['categories'] = $this->parseList($categoriesStr, ',');
        }

        // Product images (comma-separated)
        $imagesStr = $this->getValue($row, 'product_images', '');

        if ($imagesStr !== '') {
            $normalized['product']['images'] = $this->parseImages($imagesStr);
        }

        // Variant data - check for variant-specific fields first
        $variantSku = $this->getValue($row, 'variant_code', '');
        $productSku = $this->getValue($row, 'product_code', '');
        $normalized['variant']['sku'] = $variantSku !== '' ? $variantSku : $productSku;

        $normalized['variant']['original_id'] = $this->getValue($row, 'variant_id', '') ?: $this->getValue($row, 'product_id', '');

        // Price - variant price overrides product price
        $variantPrice = $this->getValue($row, 'variant_price', '');
        $productPrice = $this->getValue($row, 'product_price', '0');
        $normalized['variant']['price'] = $this->parsePrice($variantPrice !== '' ? $variantPrice : $productPrice);
        $normalized['variant']['currency'] = $this->defaultCurrency;

        // Stock - variant quantity overrides product quantity
        $variantQty = $this->getValue($row, 'variant_quantity', '');
        $productQty = $this->getValue($row, 'product_quantity', '0');
        $normalized['variant']['stock'] = $this->parseStock($variantQty !== '' ? $variantQty : $productQty);

        // Weight (HikaShop typically uses kg)
        $normalized['variant']['weight'] = (float) $this->getValue($row, 'product_weight', '0');

        // Digital flag
        $normalized['variant']['is_digital'] = $normalized['product']['product_type'] === 'digital';
        $normalized['variant']['active'] = $normalized['product']['active'];

        // Sale pricing
        $salePrice = $this->getValue($row, 'product_sale_price', '');

        if ($salePrice !== '' && (float) $salePrice > 0) {
            $normalized['variant']['sale_price'] = $this->parsePrice($salePrice);
            $normalized['variant']['sale_start'] = $this->parseDatetime($this->getValue($row, 'product_sale_start', ''));
            $normalized['variant']['sale_end'] = $this->parseDatetime($this->getValue($row, 'product_sale_end', ''));
        }

        // Characteristic columns for variant options
        // HikaShop doesn't provide explicit names, we infer them
        $options = [];

        for ($i = 1; $i <= 5; $i++) {
            $charValue = $this->getValue($row, "characteristic_{$i}", '');

            if ($charValue !== '') {
                $options[] = [
                    'name'  => $this->inferOptionName($charValue),
                    'value' => $charValue,
                ];
            }
        }

        // De-duplicate option names (add numbers if needed)
        $nameCounts = [];

        foreach ($options as &$opt) {
            $baseName = $opt['name'];

            if (!isset($nameCounts[$baseName])) {
                $nameCounts[$baseName] = 0;
            }

            $nameCounts[$baseName]++;

            if ($nameCounts[$baseName] > 1) {
                $opt['name'] = $baseName . ' ' . $nameCounts[$baseName];
            }
        }

        $normalized['variant']['options'] = $options;
        $normalized['variant']['original_images'] = [];

        // Generate SKU if missing
        if (empty($normalized['variant']['sku'])) {
            $skuParts = [$normalized['product']['slug']];

            foreach ($options as $opt) {
                $skuParts[] = $this->generateSlug($opt['value']);
            }

            $normalized['variant']['sku'] = $this->truncate(implode('-', $skuParts), 64);
        }

        return $normalized;
    }

    /**
     * {@inheritdoc}
     */
    public function shouldGroupVariants(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getGroupingColumn(): ?string
    {
        return 'product_id';
    }

    /**
     * {@inheritdoc}
     */
    public function getWeightUnit(): string
    {
        return 'kg';
    }

    /**
     * {@inheritdoc}
     */
    public function getCategorySeparator(): string
    {
        return ',';
    }
}
