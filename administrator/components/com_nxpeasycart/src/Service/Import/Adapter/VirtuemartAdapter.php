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
 * VirtueMart CSV import adapter.
 *
 * Handles VirtueMart's export format with pipe-separated categories and custom fields.
 *
 * @since 0.3.0
 */
class VirtuemartAdapter extends AbstractPlatformAdapter
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'virtuemart';
    }

    /**
     * {@inheritdoc}
     */
    public function getDisplayName(): string
    {
        return 'VirtueMart';
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultMapping(): array
    {
        return [
            // Product fields
            'product_id'          => 'product.original_id',
            'product_sku'         => 'variant.sku',
            'product_name'        => 'product.title',
            'slug'                => 'product.slug',
            'product_s_desc'      => 'product.short_desc',
            'product_desc'        => 'product.long_desc',
            'published'           => 'product.active',
            'product_special'     => 'product.featured',
            'categories'          => 'product.categories',
            'category_path'       => 'product.category_path',
            'file_url'            => 'product.images',
            'file_url_thumb'      => 'product.thumbnail',
            // Variant/price fields
            'product_price'       => 'variant.price',
            'product_override_price' => 'variant.sale_price',
            'product_in_stock'    => 'variant.stock',
            'product_weight'      => 'variant.weight',
            'product_weight_uom'  => 'variant.weight_unit',
            'customfields'        => 'variant.customfields',
            'child_id'            => 'variant.original_id',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getSignatureHeaders(): array
    {
        // Unique combination that identifies VirtueMart format
        return ['product_id', 'product_sku', 'product_name', 'product_price', 'product_in_stock'];
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
        $normalized['product']['slug'] = $this->getValue($row, 'slug', '') ?: $this->generateSlug($normalized['product']['title']);
        $normalized['product']['short_desc'] = $this->sanitizeHtml($this->getValue($row, 'product_s_desc', ''));
        $normalized['product']['long_desc'] = $this->sanitizeHtml($this->getValue($row, 'product_desc', ''));
        $normalized['product']['active'] = $this->parseBoolean($this->getValue($row, 'published', '1'));
        $normalized['product']['featured'] = $this->parseBoolean($this->getValue($row, 'product_special', '0'));
        $normalized['product']['product_type'] = 'physical';

        // Categories (pipe-separated in VirtueMart)
        $categoriesStr = $this->getValue($row, 'categories', '');

        if ($categoriesStr === '') {
            $categoriesStr = $this->getValue($row, 'category_path', '');
        }

        if ($categoriesStr !== '') {
            $normalized['product']['categories'] = $this->parseList($categoriesStr, '|');
        }

        // Product images (pipe-separated)
        $imageStr = $this->getValue($row, 'file_url', '');

        if ($imageStr !== '') {
            $normalized['product']['images'] = $this->parseImages(str_replace('|', ',', $imageStr));
        }

        // Variant data
        $normalized['variant']['original_id'] = $this->getValue($row, 'child_id', '') ?: $this->getValue($row, 'product_id', '');
        $normalized['variant']['sku'] = $this->getValue($row, 'product_sku', '');
        $normalized['variant']['price'] = $this->parsePrice($this->getValue($row, 'product_price', '0'));
        $normalized['variant']['currency'] = $this->defaultCurrency;
        $normalized['variant']['stock'] = $this->parseStock($this->getValue($row, 'product_in_stock', '0'));
        $normalized['variant']['is_digital'] = $normalized['product']['product_type'] === 'digital';
        $normalized['variant']['active'] = $normalized['product']['active'];

        // Weight handling with unit
        $weight = (float) $this->getValue($row, 'product_weight', '0');
        $weightUnit = strtolower($this->getValue($row, 'product_weight_uom', 'KG'));

        // Convert to kg
        $factor = self::WEIGHT_FACTORS[$weightUnit] ?? self::WEIGHT_FACTORS['kg'];
        $normalized['variant']['weight'] = round($weight * $factor, 3);

        // Override price is sale price in VirtueMart
        $overridePrice = $this->getValue($row, 'product_override_price', '');

        if ($overridePrice !== '' && (float) $overridePrice > 0) {
            $override = $this->parsePrice($overridePrice);

            if ($override < $normalized['variant']['price']) {
                $normalized['variant']['sale_price'] = $override;
            }
        }

        // Parse custom fields for variant options (format: "color:Black|size:M")
        $customfields = $this->getValue($row, 'customfields', '');

        if ($customfields !== '') {
            $options = [];
            $pairs = explode('|', $customfields);

            foreach ($pairs as $pair) {
                $parts = explode(':', $pair, 2);

                if (\count($parts) === 2) {
                    $options[] = [
                        'name'  => ucfirst(trim($parts[0])),
                        'value' => trim($parts[1]),
                    ];
                }
            }

            $normalized['variant']['options'] = $options;
        }

        $normalized['variant']['original_images'] = [];

        // Generate SKU if missing
        if (empty($normalized['variant']['sku'])) {
            $normalized['variant']['sku'] = $this->truncate(
                $normalized['product']['slug'] . '-' . $normalized['variant']['original_id'],
                64
            );
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
        return 'kg'; // Will be converted based on product_weight_uom
    }

    /**
     * {@inheritdoc}
     */
    public function getCategorySeparator(): string
    {
        return '|';
    }
}
