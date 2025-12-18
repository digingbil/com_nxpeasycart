<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Administrator\Service\Import;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;

/**
 * Export processor for generating CSV exports.
 *
 * Generates CSV files in native format or compatible formats for other platforms.
 * Uses streaming writes to handle large datasets.
 *
 * @since 0.3.0
 */
class ExportProcessor
{
    /**
     * @var DatabaseInterface
     */
    private DatabaseInterface $db;

    /**
     * Export formats.
     */
    public const FORMAT_NATIVE = 'native';
    public const FORMAT_SHOPIFY = 'shopify';
    public const FORMAT_WOOCOMMERCE = 'woocommerce';

    /**
     * @var array Export filters
     */
    private array $filters = [];

    /**
     * @var int Chunk size for database reads
     */
    private int $chunkSize = 100;

    /**
     * Constructor.
     *
     * @param DatabaseInterface $db Database driver
     *
     * @since 0.3.0
     */
    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Set export filters.
     *
     * @param array $filters Filter configuration
     *
     * @return self
     *
     * @since 0.3.0
     */
    public function setFilters(array $filters): self
    {
        $this->filters = $filters;

        return $this;
    }

    /**
     * Generate export to file.
     *
     * @param string $filePath Output file path
     * @param string $format   Export format (native, shopify, woocommerce)
     *
     * @return array{total: int, file: string}
     *
     * @throws \RuntimeException If file cannot be created
     *
     * @since 0.3.0
     */
    public function export(string $filePath, string $format = self::FORMAT_NATIVE): array
    {
        $handle = fopen($filePath, 'w');

        if ($handle === false) {
            throw new \RuntimeException('Cannot create export file: ' . $filePath);
        }

        // Add BOM for UTF-8 Excel compatibility
        fwrite($handle, "\xEF\xBB\xBF");

        // Write headers
        $headers = $this->getHeaders($format);
        fputcsv($handle, $headers);

        // Stream products
        $total = 0;
        $offset = 0;

        do {
            $products = $this->fetchProductChunk($offset, $this->chunkSize);
            $count = \count($products);

            foreach ($products as $product) {
                $variants = $this->fetchVariants((int) $product->id);

                foreach ($variants as $variant) {
                    $row = $this->formatRow($product, $variant, $format);
                    fputcsv($handle, $row);
                    $total++;
                }
            }

            $offset += $this->chunkSize;
        } while ($count === $this->chunkSize);

        fclose($handle);

        return [
            'total' => $total,
            'file'  => $filePath,
        ];
    }

    /**
     * Get CSV headers for format.
     *
     * @param string $format Export format
     *
     * @return array<int, string>
     *
     * @since 0.3.0
     */
    public function getHeaders(string $format): array
    {
        return match ($format) {
            self::FORMAT_SHOPIFY => [
                'Handle', 'Title', 'Body (HTML)', 'Vendor', 'Product Category', 'Type', 'Tags',
                'Published', 'Option1 Name', 'Option1 Value', 'Option2 Name', 'Option2 Value',
                'Option3 Name', 'Option3 Value', 'Variant SKU', 'Variant Grams', 'Variant Inventory Qty',
                'Variant Price', 'Variant Compare At Price', 'Variant Requires Shipping', 'Variant Taxable',
                'Variant Barcode', 'Image Src', 'Variant Image',
            ],
            self::FORMAT_WOOCOMMERCE => [
                'ID', 'Type', 'SKU', 'Name', 'Published', 'Is featured?', 'Short description',
                'Description', 'Sale price', 'Regular price', 'Categories', 'Images',
                'Stock', 'Weight (kg)', 'Attribute 1 name', 'Attribute 1 value(s)',
                'Attribute 2 name', 'Attribute 2 value(s)', 'Attribute 3 name', 'Attribute 3 value(s)',
            ],
            default => [ // Native format
                'product_id', 'product_slug', 'product_title', 'short_desc', 'long_desc',
                'product_type', 'featured', 'active', 'categories', 'images',
                'variant_id', 'sku', 'price', 'sale_price', 'sale_start', 'sale_end',
                'currency', 'stock', 'weight', 'ean', 'is_digital', 'variant_active',
                'options', 'original_images', 'variant_images',
            ],
        };
    }

    /**
     * Fetch a chunk of products.
     *
     * @param int $offset Offset
     * @param int $limit  Limit
     *
     * @return array
     *
     * @since 0.3.0
     */
    private function fetchProductChunk(int $offset, int $limit): array
    {
        $query = $this->db->getQuery(true)
            ->select([
                'p.*',
                'GROUP_CONCAT(DISTINCT c.title SEPARATOR \',\') AS category_names',
            ])
            ->from($this->db->quoteName('#__nxp_easycart_products', 'p'))
            ->leftJoin(
                $this->db->quoteName('#__nxp_easycart_product_categories', 'pc') .
                ' ON pc.product_id = p.id'
            )
            ->leftJoin(
                $this->db->quoteName('#__nxp_easycart_categories', 'c') .
                ' ON c.id = pc.category_id'
            )
            ->group('p.id')
            ->order('p.id ASC');

        // Apply filters
        if (!empty($this->filters['active_only'])) {
            $query->where('p.active = 1');
        }

        if (!empty($this->filters['imported_from'])) {
            $query->where($this->db->quoteName('p.imported_from') . ' = ' . $this->db->quote($this->filters['imported_from']));
        }

        if (!empty($this->filters['category_ids'])) {
            $query->where('pc.category_id IN (' . implode(',', array_map('intval', $this->filters['category_ids'])) . ')');
        }

        $this->db->setQuery($query, $offset, $limit);

        return $this->db->loadObjectList() ?: [];
    }

    /**
     * Fetch variants for a product.
     *
     * @param int $productId Product ID
     *
     * @return array
     *
     * @since 0.3.0
     */
    private function fetchVariants(int $productId): array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__nxp_easycart_variants'))
            ->where($this->db->quoteName('product_id') . ' = ' . (int) $productId)
            ->order('id ASC');

        // Filter by stock if requested
        if (!empty($this->filters['in_stock_only'])) {
            $query->where('stock > 0');
        }

        $this->db->setQuery($query);

        return $this->db->loadObjectList() ?: [];
    }

    /**
     * Format a row for the specified format.
     *
     * @param object $product Product data
     * @param object $variant Variant data
     * @param string $format  Export format
     *
     * @return array
     *
     * @since 0.3.0
     */
    private function formatRow(object $product, object $variant, string $format): array
    {
        return match ($format) {
            self::FORMAT_SHOPIFY => $this->formatShopifyRow($product, $variant),
            self::FORMAT_WOOCOMMERCE => $this->formatWoocommerceRow($product, $variant),
            default => $this->formatNativeRow($product, $variant),
        };
    }

    /**
     * Format a row in native format.
     *
     * @param object $product Product data
     * @param object $variant Variant data
     *
     * @return array
     *
     * @since 0.3.0
     */
    private function formatNativeRow(object $product, object $variant): array
    {
        $options = $this->parseJson($variant->options ?? null);
        $optionsStr = '';

        if (!empty($options)) {
            $pairs = [];

            foreach ($options as $opt) {
                $pairs[] = ($opt['name'] ?? 'Option') . ':' . ($opt['value'] ?? '');
            }

            $optionsStr = implode('|', $pairs);
        }

        $images = $this->parseJson($product->images ?? null);
        $originalImages = $this->parseJson($variant->original_images ?? null);
        $variantImages = $this->parseJson($variant->images ?? null);

        return [
            $product->id,
            $product->slug,
            $product->title,
            $product->short_desc ?? '',
            $product->long_desc ?? '',
            $product->product_type ?? 'physical',
            (int) $product->featured,
            (int) $product->active,
            $product->category_names ?? '',
            !empty($images) ? implode(',', $images) : '',
            $variant->id,
            $variant->sku,
            $this->centsToDollars($variant->price_cents),
            $variant->sale_price_cents ? $this->centsToDollars($variant->sale_price_cents) : '',
            $variant->sale_start ?? '',
            $variant->sale_end ?? '',
            $variant->currency,
            $variant->stock,
            $variant->weight ?? '0.000',
            $variant->ean ?? '',
            (int) (strtolower($product->product_type ?? 'physical') === 'digital'),
            (int) $variant->active,
            $optionsStr,
            !empty($originalImages) ? implode(',', $originalImages) : '',
            !empty($variantImages) ? implode(',', $variantImages) : '',
        ];
    }

    /**
     * Format a row in Shopify format.
     *
     * @param object $product Product data
     * @param object $variant Variant data
     *
     * @return array
     *
     * @since 0.3.0
     */
    private function formatShopifyRow(object $product, object $variant): array
    {
        $options = $this->parseJson($variant->options ?? null);
        $images = $this->parseJson($product->images ?? null);
        // Use variant display images if available, fall back to original_images
        $variantImages = $this->parseJson($variant->images ?? null);
        if (empty($variantImages)) {
            $variantImages = $this->parseJson($variant->original_images ?? null);
        }

        // Extract up to 3 options
        $opt1Name = $options[0]['name'] ?? '';
        $opt1Value = $options[0]['value'] ?? '';
        $opt2Name = $options[1]['name'] ?? '';
        $opt2Value = $options[1]['value'] ?? '';
        $opt3Name = $options[2]['name'] ?? '';
        $opt3Value = $options[2]['value'] ?? '';

        // Calculate prices for Shopify format
        // Shopify: Price = current price, Compare At = original price if on sale
        $price = $this->centsToDollars($variant->price_cents);
        $compareAt = '';

        if ($variant->sale_price_cents && $variant->sale_price_cents < $variant->price_cents) {
            $compareAt = $price;
            $price = $this->centsToDollars($variant->sale_price_cents);
        }

        return [
            $product->slug,                              // Handle
            $product->title,                             // Title
            $product->long_desc ?? '',                   // Body (HTML)
            '',                                          // Vendor
            $product->category_names ?? '',              // Product Category
            $product->product_type === 'digital' ? 'Digital' : '', // Type
            '',                                          // Tags
            $product->active ? 'TRUE' : 'FALSE',         // Published
            $opt1Name,                                   // Option1 Name
            $opt1Value,                                  // Option1 Value
            $opt2Name,                                   // Option2 Name
            $opt2Value,                                  // Option2 Value
            $opt3Name,                                   // Option3 Name
            $opt3Value,                                  // Option3 Value
            $variant->sku,                               // Variant SKU
            (int) (($variant->weight ?? 0) * 1000),      // Variant Grams (kg to g)
            $variant->stock,                             // Variant Inventory Qty
            $price,                                      // Variant Price
            $compareAt,                                  // Variant Compare At Price
            $product->product_type === 'physical' ? 'TRUE' : 'FALSE', // Requires Shipping
            'TRUE',                                      // Variant Taxable
            $variant->ean ?? '',                         // Variant Barcode
            !empty($images) ? $images[0] : '',           // Image Src
            !empty($variantImages) ? $variantImages[0] : '', // Variant Image
        ];
    }

    /**
     * Format a row in WooCommerce format.
     *
     * @param object $product Product data
     * @param object $variant Variant data
     *
     * @return array
     *
     * @since 0.3.0
     */
    private function formatWoocommerceRow(object $product, object $variant): array
    {
        $options = $this->parseJson($variant->options ?? null);
        $images = $this->parseJson($product->images ?? null);

        // Calculate prices
        $regularPrice = $this->centsToDollars($variant->price_cents);
        $salePrice = '';

        if ($variant->sale_price_cents && $variant->sale_price_cents < $variant->price_cents) {
            $salePrice = $this->centsToDollars($variant->sale_price_cents);
        }

        // Extract up to 3 attributes
        $attr1Name = $options[0]['name'] ?? '';
        $attr1Value = $options[0]['value'] ?? '';
        $attr2Name = $options[1]['name'] ?? '';
        $attr2Value = $options[1]['value'] ?? '';
        $attr3Name = $options[2]['name'] ?? '';
        $attr3Value = $options[2]['value'] ?? '';

        return [
            $variant->id,                                // ID
            'simple',                                    // Type
            $variant->sku,                               // SKU
            $product->title,                             // Name
            $product->active ? '1' : '0',                // Published
            $product->featured ? '1' : '0',              // Is featured?
            $product->short_desc ?? '',                  // Short description
            $product->long_desc ?? '',                   // Description
            $salePrice,                                  // Sale price
            $regularPrice,                               // Regular price
            $product->category_names ?? '',              // Categories
            !empty($images) ? implode(',', $images) : '', // Images
            $variant->stock,                             // Stock
            $variant->weight ?? '0',                     // Weight (kg)
            $attr1Name,                                  // Attribute 1 name
            $attr1Value,                                 // Attribute 1 value(s)
            $attr2Name,                                  // Attribute 2 name
            $attr2Value,                                 // Attribute 2 value(s)
            $attr3Name,                                  // Attribute 3 name
            $attr3Value,                                 // Attribute 3 value(s)
        ];
    }

    /**
     * Parse JSON string to array.
     *
     * @param string|null $json JSON string
     *
     * @return array
     *
     * @since 0.3.0
     */
    private function parseJson(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * Convert cents to dollars string.
     *
     * @param int $cents Amount in cents
     *
     * @return string
     *
     * @since 0.3.0
     */
    private function centsToDollars(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /**
     * Count total rows that will be exported.
     *
     * @return int
     *
     * @since 0.3.0
     */
    public function countTotal(): int
    {
        $query = $this->db->getQuery(true)
            ->select('COUNT(v.id)')
            ->from($this->db->quoteName('#__nxp_easycart_variants', 'v'))
            ->innerJoin(
                $this->db->quoteName('#__nxp_easycart_products', 'p') .
                ' ON p.id = v.product_id'
            );

        // Apply filters
        if (!empty($this->filters['active_only'])) {
            $query->where('p.active = 1');
        }

        if (!empty($this->filters['in_stock_only'])) {
            $query->where('v.stock > 0');
        }

        if (!empty($this->filters['imported_from'])) {
            $query->where($this->db->quoteName('p.imported_from') . ' = ' . $this->db->quote($this->filters['imported_from']));
        }

        if (!empty($this->filters['category_ids'])) {
            $query->leftJoin(
                $this->db->quoteName('#__nxp_easycart_product_categories', 'pc') .
                ' ON pc.product_id = p.id'
            );
            $query->where('pc.category_id IN (' . implode(',', array_map('intval', $this->filters['category_ids'])) . ')');
        }

        $this->db->setQuery($query);

        return (int) $this->db->loadResult();
    }
}
