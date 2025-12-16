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
 * WooCommerce CSV import adapter.
 *
 * Handles WooCommerce's default product CSV export format.
 * Note: Skip rows with Type="variable" (parent products), only import "simple" and "variation".
 *
 * @since 0.3.0
 */
class WoocommerceAdapter extends AbstractPlatformAdapter
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'woocommerce';
    }

    /**
     * {@inheritdoc}
     */
    public function getDisplayName(): string
    {
        return 'WooCommerce';
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultMapping(): array
    {
        return [
            // Product fields
            'ID'                      => 'product.original_id',
            'Name'                    => 'product.title',
            'Short description'       => 'product.short_desc',
            'Description'             => 'product.long_desc',
            'Published'               => 'product.active',
            'Is featured?'            => 'product.featured',
            'Categories'              => 'product.categories',
            'Images'                  => 'product.images',
            'Type'                    => 'product.type',
            // Variant fields
            'SKU'                     => 'variant.sku',
            'Regular price'           => 'variant.price',
            'Sale price'              => 'variant.sale_price',
            'Date sale price starts'  => 'variant.sale_start',
            'Date sale price ends'    => 'variant.sale_end',
            'Stock'                   => 'variant.stock',
            'In stock?'               => 'variant.in_stock',
            'Weight (kg)'             => 'variant.weight',
            'Attribute 1 name'        => 'variant.attr1_name',
            'Attribute 1 value(s)'    => 'variant.attr1_value',
            'Attribute 2 name'        => 'variant.attr2_name',
            'Attribute 2 value(s)'    => 'variant.attr2_value',
            'Attribute 3 name'        => 'variant.attr3_name',
            'Attribute 3 value(s)'    => 'variant.attr3_value',
            'Parent'                  => 'variant.parent_sku',
            'Download 1 URL'          => 'variant.download_url',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getSignatureHeaders(): array
    {
        // Unique combination that identifies WooCommerce format
        return ['ID', 'Type', 'SKU', 'Name', 'Published', 'Regular price'];
    }

    /**
     * {@inheritdoc}
     */
    public function normalizeRow(array $row, array $mapping): array
    {
        $normalized = $this->createEmptyNormalized();

        $productType = strtolower($this->getValue($row, 'Type', 'simple'));
        $rawName = $this->getValue($row, 'Name', '');
        $sku = $this->getValue($row, 'SKU', '');

        // Handle variable parent products - extract all product data but mark as parent (no variant)
        if ($productType === 'variable') {
            $normalized['_is_parent'] = true;
            $normalized['_parent_sku'] = $sku;
            $normalized['product']['original_id'] = $sku;
            $normalized['product']['title'] = $rawName;
            $normalized['product']['slug'] = $this->generateSlug($rawName);
            $normalized['product']['short_desc'] = $this->sanitizeHtml($this->getValue($row, 'Short description', ''));
            $normalized['product']['long_desc'] = $this->sanitizeHtml($this->getValue($row, 'Description', ''));
            $normalized['product']['images'] = $this->parseImages($this->getValue($row, 'Images', ''));

            // Categories (hierarchical, separated by >)
            $categoriesStr = $this->getValue($row, 'Categories', '');

            if ($categoriesStr !== '') {
                $categoryPaths = $this->parseList($categoriesStr, ',');
                $allCategories = [];

                foreach ($categoryPaths as $path) {
                    $pathParts = $this->parseCategoryPath($path, '>');

                    if (!empty($pathParts)) {
                        $allCategories[] = end($pathParts);
                    }
                }

                $normalized['product']['categories'] = array_unique($allCategories);
            }

            return $normalized;
        }

        // Product data for variations and simple products
        $parentSku = $this->getValue($row, 'Parent', '');

        // Group by Parent SKU for variations, own ID for simple products
        $normalized['product']['original_id'] = $parentSku ?: $this->getValue($row, 'ID', '');
        $normalized['product']['title'] = $rawName;
        $normalized['product']['slug'] = $this->generateSlug($rawName);

        // Store the full variant name for reference
        $normalized['variant']['full_name'] = $rawName;
        $normalized['product']['short_desc'] = $this->sanitizeHtml($this->getValue($row, 'Short description', ''));
        $normalized['product']['long_desc'] = $this->sanitizeHtml($this->getValue($row, 'Description', ''));
        $normalized['product']['active'] = $this->parseBoolean($this->getValue($row, 'Published', '1'));
        $normalized['product']['featured'] = $this->parseBoolean($this->getValue($row, 'Is featured?', '0'));

        // Determine if digital product
        $downloadUrl = $this->getValue($row, 'Download 1 URL', '');
        $isDownloadable = $this->parseBoolean($this->getValue($row, 'Downloadable', '0'));
        $isVirtual = $this->parseBoolean($this->getValue($row, 'Virtual', '0'));
        $normalized['product']['product_type'] = ($isDownloadable || $isVirtual || $downloadUrl !== '') ? 'digital' : 'physical';

        // Categories (hierarchical, separated by >)
        $categoriesStr = $this->getValue($row, 'Categories', '');

        if ($categoriesStr !== '') {
            // WooCommerce uses comma to separate multiple categories, > for hierarchy
            $categoryPaths = $this->parseList($categoriesStr, ',');
            $allCategories = [];

            foreach ($categoryPaths as $path) {
                $pathParts = $this->parseCategoryPath($path, '>');

                if (!empty($pathParts)) {
                    // Take the deepest category from each path
                    $allCategories[] = end($pathParts);
                }
            }

            $normalized['product']['categories'] = array_unique($allCategories);
        }

        // Product images (comma-separated URLs)
        $normalized['product']['images'] = $this->parseImages($this->getValue($row, 'Images', ''));

        // Variant data
        $normalized['variant']['original_id'] = $this->getValue($row, 'ID', '');
        $normalized['variant']['sku'] = $this->getValue($row, 'SKU', '');
        $normalized['variant']['price'] = $this->parsePrice($this->getValue($row, 'Regular price', '0'));
        $normalized['variant']['currency'] = $this->defaultCurrency;
        $normalized['variant']['is_digital'] = $normalized['product']['product_type'] === 'digital';
        $normalized['variant']['active'] = $normalized['product']['active'];

        // Stock handling
        $inStock = $this->parseBoolean($this->getValue($row, 'In stock?', '1'));
        $stockQty = $this->getValue($row, 'Stock', '');

        if ($stockQty !== '') {
            $normalized['variant']['stock'] = $this->parseStock($stockQty);
        } else {
            $normalized['variant']['stock'] = $inStock ? 999 : 0;
        }

        // Weight (WooCommerce default is kg)
        $normalized['variant']['weight'] = (float) $this->getValue($row, 'Weight (kg)', '0');

        // Sale pricing
        $salePrice = $this->getValue($row, 'Sale price', '');

        if ($salePrice !== '') {
            $normalized['variant']['sale_price'] = $this->parsePrice($salePrice);
            $normalized['variant']['sale_start'] = $this->parseDatetime($this->getValue($row, 'Date sale price starts', ''));
            $normalized['variant']['sale_end'] = $this->parseDatetime($this->getValue($row, 'Date sale price ends', ''));
        }

        // Variant options/attributes (up to 3)
        $options = [];

        for ($i = 1; $i <= 3; $i++) {
            $attrName = $this->getValue($row, "Attribute {$i} name", '');
            $attrValue = $this->getValue($row, "Attribute {$i} value(s)", '');

            if ($attrName !== '' && $attrValue !== '') {
                // WooCommerce may have multiple values pipe-separated, take first
                $values = explode('|', $attrValue);
                $options[] = [
                    'name'  => $attrName,
                    'value' => trim($values[0]),
                ];
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
        return true; // Group variations by Parent SKU
    }

    /**
     * {@inheritdoc}
     */
    public function getGroupingColumn(): ?string
    {
        return 'Parent'; // Variations grouped by parent product SKU
    }

    /**
     * {@inheritdoc}
     */
    public function getWeightUnit(): string
    {
        return 'kg'; // WooCommerce default
    }

    /**
     * {@inheritdoc}
     */
    public function getCategorySeparator(): string
    {
        return '>';
    }
}
