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
 * Shopify CSV import adapter.
 *
 * Handles Shopify's multi-row variant format where each variant is a separate row
 * grouped by the "Handle" column.
 *
 * @since 0.3.0
 */
class ShopifyAdapter extends AbstractPlatformAdapter
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'shopify';
    }

    /**
     * {@inheritdoc}
     */
    public function getDisplayName(): string
    {
        return 'Shopify';
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultMapping(): array
    {
        return [
            // Product fields
            'Handle'               => 'product.slug',
            'Title'                => 'product.title',
            'Body (HTML)'          => 'product.long_desc',
            'Type'                 => 'product.product_type_hint',
            'Tags'                 => 'product.tags',
            'Published'            => 'product.active',
            'Product Category'     => 'product.categories',
            'Image Src'            => 'product.images',
            'Image Alt Text'       => 'product.image_alt',
            // Variant fields
            'Variant SKU'          => 'variant.sku',
            'Variant Price'        => 'variant.price',
            'Variant Compare At Price' => 'variant.compare_price',
            'Variant Inventory Qty' => 'variant.stock',
            'Variant Grams'        => 'variant.weight',
            'Variant Barcode'      => 'variant.ean',
            'Option1 Name'         => 'variant.option1_name',
            'Option1 Value'        => 'variant.option1_value',
            'Option2 Name'         => 'variant.option2_name',
            'Option2 Value'        => 'variant.option2_value',
            'Option3 Name'         => 'variant.option3_name',
            'Option3 Value'        => 'variant.option3_value',
            'Variant Image'        => 'variant.original_images',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getSignatureHeaders(): array
    {
        // Unique combination that identifies Shopify format
        return ['Handle', 'Title', 'Variant SKU', 'Option1 Name', 'Option1 Value'];
    }

    /**
     * {@inheritdoc}
     */
    public function normalizeRow(array $row, array $mapping): array
    {
        $normalized = $this->createEmptyNormalized();

        // Product data (only present on first row of each product)
        $normalized['product']['original_id'] = $this->getValue($row, 'Handle', '');
        $normalized['product']['slug'] = $this->generateSlug($this->getValue($row, 'Handle', ''));
        $normalized['product']['title'] = $this->getValue($row, 'Title', '');
        $normalized['product']['long_desc'] = $this->sanitizeHtml($this->getValue($row, 'Body (HTML)', ''));
        $normalized['product']['active'] = $this->parseBoolean($this->getValue($row, 'Published', 'true'));
        $normalized['product']['product_type'] = 'physical'; // Shopify doesn't have digital flag
        $normalized['product']['featured'] = false;

        // Categories from Product Category column
        $categoryPath = $this->getValue($row, 'Product Category', '');
        $normalized['product']['categories'] = $this->parseCategoryPath($categoryPath, '>');

        // Product images
        $imageSrc = $this->getValue($row, 'Image Src', '');

        if ($imageSrc !== '' && filter_var($imageSrc, FILTER_VALIDATE_URL)) {
            $normalized['product']['images'] = [$imageSrc];
        }

        // Variant data
        $normalized['variant']['sku'] = $this->getValue($row, 'Variant SKU', '');
        $normalized['variant']['price'] = $this->parsePrice($this->getValue($row, 'Variant Price', '0'));
        $normalized['variant']['currency'] = $this->defaultCurrency;
        $normalized['variant']['stock'] = $this->parseStock($this->getValue($row, 'Variant Inventory Qty', '0'));
        $normalized['variant']['weight'] = $this->parseWeight($this->getValue($row, 'Variant Grams', '0'));
        $normalized['variant']['ean'] = $this->parseEan($this->getValue($row, 'Variant Barcode', ''));
        $normalized['variant']['is_digital'] = false;
        $normalized['variant']['active'] = true;
        $normalized['variant']['original_id'] = $this->getValue($row, 'Variant SKU', '');

        // Compare at price becomes sale price (inverted logic)
        // In Shopify, "Compare At Price" is the original price, "Price" is the sale price
        $comparePrice = $this->parsePrice($this->getValue($row, 'Variant Compare At Price', ''));

        if ($comparePrice > 0 && $comparePrice > $normalized['variant']['price']) {
            // Swap: compare price is the original, current price is the sale
            $salePrice = $normalized['variant']['price'];
            $normalized['variant']['price'] = $comparePrice;
            $normalized['variant']['sale_price'] = $salePrice;
            // No sale dates in Shopify export
        }

        // Variant image
        $variantImage = $this->getValue($row, 'Variant Image', '');

        if ($variantImage !== '' && filter_var($variantImage, FILTER_VALIDATE_URL)) {
            $normalized['variant']['original_images'] = [$variantImage];
        }

        // Variant options (up to 3 in Shopify)
        $options = [];

        for ($i = 1; $i <= 3; $i++) {
            $optionName = $this->getValue($row, "Option{$i} Name", '');
            $optionValue = $this->getValue($row, "Option{$i} Value", '');

            if ($optionName !== '' && $optionValue !== '' && strtolower($optionName) !== 'title') {
                $options[] = [
                    'name'  => $optionName,
                    'value' => $optionValue,
                ];
            }
        }

        $normalized['variant']['options'] = $options;

        // Generate SKU from handle + options if missing
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
        return true; // Multiple rows per product
    }

    /**
     * {@inheritdoc}
     */
    public function getGroupingColumn(): ?string
    {
        return 'Handle';
    }

    /**
     * {@inheritdoc}
     */
    public function getWeightUnit(): string
    {
        return 'g'; // Shopify uses grams
    }

    /**
     * {@inheritdoc}
     */
    public function getCategorySeparator(): string
    {
        return '>';
    }
}
