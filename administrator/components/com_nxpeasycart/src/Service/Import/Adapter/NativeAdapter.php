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
 * Native NXP Easy Cart import adapter.
 *
 * Handles our own export format for backup/restore and migration.
 * Direct 1:1 mapping to database schema.
 *
 * @since 0.3.0
 */
class NativeAdapter extends AbstractPlatformAdapter
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'native';
    }

    /**
     * {@inheritdoc}
     */
    public function getDisplayName(): string
    {
        return 'NXP Easy Cart (Native)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultMapping(): array
    {
        return [
            // Product fields
            'product_id'      => 'product.original_id',
            'product_slug'    => 'product.slug',
            'product_title'   => 'product.title',
            'short_desc'      => 'product.short_desc',
            'long_desc'       => 'product.long_desc',
            'product_type'    => 'product.product_type',
            'featured'        => 'product.featured',
            'active'          => 'product.active',
            'categories'      => 'product.categories',
            'images'          => 'product.images',
            // Variant fields
            'variant_id'      => 'variant.original_id',
            'sku'             => 'variant.sku',
            'price'           => 'variant.price',
            'sale_price'      => 'variant.sale_price',
            'sale_start'      => 'variant.sale_start',
            'sale_end'        => 'variant.sale_end',
            'currency'        => 'variant.currency',
            'stock'           => 'variant.stock',
            'weight'          => 'variant.weight',
            'ean'             => 'variant.ean',
            'is_digital'      => 'variant.is_digital',
            'variant_active'  => 'variant.active',
            'options'         => 'variant.options',
            'original_images' => 'variant.original_images',
            'variant_images'  => 'variant.images',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getSignatureHeaders(): array
    {
        // Unique combination that identifies our native format
        return ['product_id', 'product_slug', 'variant_id', 'sku', 'is_digital'];
    }

    /**
     * {@inheritdoc}
     */
    public function normalizeRow(array $row, array $mapping): array
    {
        $normalized = $this->createEmptyNormalized();

        // Product data
        $normalized['product']['original_id'] = $this->getValue($row, 'product_id', '');
        $normalized['product']['slug'] = $this->getValue($row, 'product_slug', '');
        $normalized['product']['title'] = $this->getValue($row, 'product_title', '');
        $normalized['product']['short_desc'] = $this->sanitizeHtml($this->getValue($row, 'short_desc', ''));
        $normalized['product']['long_desc'] = $this->sanitizeHtml($this->getValue($row, 'long_desc', ''));
        $normalized['product']['product_type'] = $this->getValue($row, 'product_type', 'physical');
        $normalized['product']['featured'] = $this->parseBoolean($this->getValue($row, 'featured', '0'));
        $normalized['product']['active'] = $this->parseBoolean($this->getValue($row, 'active', '1'));
        $normalized['product']['categories'] = $this->parseList($this->getValue($row, 'categories', ''), ',');
        $normalized['product']['images'] = $this->parseImages($this->getValue($row, 'images', ''));

        // Variant data
        $normalized['variant']['original_id'] = $this->getValue($row, 'variant_id', '');
        $normalized['variant']['sku'] = $this->getValue($row, 'sku', '');
        $normalized['variant']['price'] = $this->parsePrice($this->getValue($row, 'price', '0'));
        $normalized['variant']['currency'] = strtoupper($this->getValue($row, 'currency', $this->defaultCurrency)) ?: $this->defaultCurrency;
        $normalized['variant']['stock'] = $this->parseStock($this->getValue($row, 'stock', '0'));
        $normalized['variant']['weight'] = (float) $this->getValue($row, 'weight', '0');
        $normalized['variant']['ean'] = $this->parseEan($this->getValue($row, 'ean', ''));
        $normalized['variant']['is_digital'] = $this->parseBoolean($this->getValue($row, 'is_digital', '0'));
        $normalized['variant']['active'] = $this->parseBoolean($this->getValue($row, 'variant_active', '1'));
        $normalized['variant']['original_images'] = $this->parseImages($this->getValue($row, 'original_images', ''));

        // Variant display images (for storefront image switching)
        $variantImagesStr = $this->getValue($row, 'variant_images', '');
        $normalized['variant']['images'] = $variantImagesStr !== '' ? $this->parseImages($variantImagesStr) : null;

        // Sale pricing
        $salePrice = $this->getValue($row, 'sale_price', '');

        if ($salePrice !== '') {
            $normalized['variant']['sale_price'] = $this->parsePrice($salePrice);
            $normalized['variant']['sale_start'] = $this->parseDatetime($this->getValue($row, 'sale_start', ''));
            $normalized['variant']['sale_end'] = $this->parseDatetime($this->getValue($row, 'sale_end', ''));
        }

        // Parse variant options (format: "Color:Black|Size:M")
        $optionsStr = $this->getValue($row, 'options', '');

        if ($optionsStr !== '') {
            $options = [];
            $pairs = explode('|', $optionsStr);

            foreach ($pairs as $pair) {
                $parts = explode(':', $pair, 2);

                if (\count($parts) === 2) {
                    $options[] = [
                        'name'  => trim($parts[0]),
                        'value' => trim($parts[1]),
                    ];
                }
            }

            $normalized['variant']['options'] = $options;
        }

        // Generate slug if missing
        if (empty($normalized['product']['slug']) && !empty($normalized['product']['title'])) {
            $normalized['product']['slug'] = $this->generateSlug($normalized['product']['title']);
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
        return 'kg'; // Native format stores weight in kg
    }

    /**
     * {@inheritdoc}
     */
    public function getCategorySeparator(): string
    {
        return ',';
    }
}
