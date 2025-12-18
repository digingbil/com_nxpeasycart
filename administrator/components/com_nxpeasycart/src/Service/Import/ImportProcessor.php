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

use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\Database\DatabaseInterface;

/**
 * Import processor for chunked CSV processing.
 *
 * Handles the actual import of normalized data into the database with:
 * - Chunked processing for large files
 * - Transaction wrapping per product
 * - Duplicate SKU handling
 * - Category tree creation
 * - Fallback value application
 *
 * @since 0.3.0
 */
class ImportProcessor
{
    /**
     * @var DatabaseInterface
     */
    private DatabaseInterface $db;

    /**
     * @var PlatformAdapterInterface
     */
    private PlatformAdapterInterface $adapter;

    /**
     * @var string Default currency
     */
    private string $defaultCurrency;

    /**
     * @var int Current user ID
     */
    private int $userId;

    /**
     * @var array Import options
     */
    private array $options;

    /**
     * @var array Cached category mappings (slug => id)
     */
    private array $categoryCache = [];

    /**
     * @var array Cached SKU set for duplicate detection
     */
    private array $skuCache = [];

    /**
     * @var array Processing statistics
     */
    private array $stats = [
        'products'   => 0,
        'variants'   => 0,
        'categories' => 0,
        'skipped'    => 0,
    ];

    /**
     * @var array Collected errors
     */
    private array $errors = [];

    /**
     * @var array Collected warnings
     */
    private array $warnings = [];

    /**
     * @var InputFilter HTML filter for sanitization
     */
    private InputFilter $inputFilter;

    /**
     * Constructor.
     *
     * @param DatabaseInterface        $db       Database driver
     * @param PlatformAdapterInterface $adapter  Platform adapter
     * @param string                   $currency Default currency
     * @param int                      $userId   Current user ID
     * @param array                    $options  Import options
     *
     * @since 0.3.0
     */
    public function __construct(
        DatabaseInterface $db,
        PlatformAdapterInterface $adapter,
        string $currency,
        int $userId,
        array $options = []
    ) {
        $this->db = $db;
        $this->adapter = $adapter;
        $this->defaultCurrency = strtoupper($currency);
        $this->userId = $userId;
        $this->options = array_merge([
            'create_categories' => true,
            'set_active'        => true,
            'store_images'      => true,
        ], $options);

        // Allow common safe HTML tags for product descriptions
        // Using blacklist mode (0) with empty arrays uses Joomla's default safe filtering
        $this->inputFilter = InputFilter::getInstance([], [], 0, 0);
        $this->loadSkuCache();
    }

    /**
     * Process a chunk of normalized rows.
     *
     * @param array $normalizedRows Array of normalized row data
     * @param int   $startRow       Starting row number for error reporting
     *
     * @return array{products: int, variants: int, categories: int, skipped: int, errors: array, warnings: array}
     *
     * @since 0.3.0
     */
    public function processChunk(array $normalizedRows, int $startRow = 0): array
    {
        // Group rows by product if adapter requires it
        $groups = $this->groupRowsByProduct($normalizedRows);

        foreach ($groups as $groupKey => $rows) {
            $rowNumber = $startRow + array_key_first($rows);

            try {
                $this->processProductGroup($rows, $rowNumber);
            } catch (\Throwable $e) {
                $this->errors[] = [
                    'row'     => $rowNumber,
                    'message' => $e->getMessage(),
                    'field'   => null,
                ];
                $this->stats['skipped'] += \count($rows);
            }
        }

        return [
            'products'   => $this->stats['products'],
            'variants'   => $this->stats['variants'],
            'categories' => $this->stats['categories'],
            'skipped'    => $this->stats['skipped'],
            'errors'     => $this->errors,
            'warnings'   => $this->warnings,
        ];
    }

    /**
     * Group normalized rows by product.
     *
     * @param array $rows Normalized rows
     *
     * @return array<string, array>
     *
     * @since 0.3.0
     */
    private function groupRowsByProduct(array $rows): array
    {
        if (!$this->adapter->shouldGroupVariants()) {
            // Each row is its own group
            $groups = [];

            foreach ($rows as $idx => $row) {
                $groups[$idx] = [$idx => $row];
            }

            return $groups;
        }

        // First pass: collect parent product data (e.g., WooCommerce "variable" rows)
        $parentData = [];

        foreach ($rows as $idx => $row) {
            if (!empty($row['_is_parent']) && !empty($row['_parent_sku'])) {
                $parentData[$row['_parent_sku']] = [
                    'title'      => $row['product']['title'] ?? '',
                    'slug'       => $row['product']['slug'] ?? '',
                    'short_desc' => $row['product']['short_desc'] ?? '',
                    'long_desc'  => $row['product']['long_desc'] ?? '',
                    'images'     => $row['product']['images'] ?? [],
                    'categories' => $row['product']['categories'] ?? [],
                ];
            }
        }

        // Second pass: group variations and apply parent data
        $groups = [];

        foreach ($rows as $idx => $row) {
            // Skip rows marked for skipping
            if (!empty($row['_skip'])) {
                $this->stats['skipped']++;

                if (!empty($row['_skip_reason'])) {
                    $this->warnings[] = [
                        'row'     => $idx,
                        'message' => $row['_skip_reason'],
                        'field'   => null,
                    ];
                }

                continue;
            }

            // Skip parent rows - they don't create variants, just provide product info
            if (!empty($row['_is_parent'])) {
                continue;
            }

            $key = $row['product']['original_id'] ?: $row['product']['slug'] ?: $row['product']['title'];

            // Apply parent data to this row if available (for title, descriptions, etc.)
            if (isset($parentData[$key])) {
                $parent = $parentData[$key];

                // Use parent's clean title instead of variation name
                if (!empty($parent['title'])) {
                    $row['product']['title'] = $parent['title'];
                    $row['product']['slug'] = $parent['slug'] ?: $row['product']['slug'];
                }

                // Use parent's descriptions if variation doesn't have them
                if (empty($row['product']['short_desc']) && !empty($parent['short_desc'])) {
                    $row['product']['short_desc'] = $parent['short_desc'];
                }

                if (empty($row['product']['long_desc']) && !empty($parent['long_desc'])) {
                    $row['product']['long_desc'] = $parent['long_desc'];
                }

                // Use parent's images if variation doesn't have them
                if (empty($row['product']['images']) && !empty($parent['images'])) {
                    $row['product']['images'] = $parent['images'];
                }

                // Use parent's categories if variation doesn't have them
                if (empty($row['product']['categories']) && !empty($parent['categories'])) {
                    $row['product']['categories'] = $parent['categories'];
                }
            }

            if (!isset($groups[$key])) {
                $groups[$key] = [];
            }

            $groups[$key][$idx] = $row;
        }

        return $groups;
    }

    /**
     * Process a group of rows belonging to the same product.
     *
     * @param array $rows      Normalized rows for this product
     * @param int   $rowNumber Base row number for error reporting
     *
     * @return void
     *
     * @since 0.3.0
     */
    private function processProductGroup(array $rows, int $rowNumber): void
    {
        if (empty($rows)) {
            return;
        }

        // Use first row for product data
        $firstRow = reset($rows);

        $this->db->transactionStart();

        try {
            // Create or get product
            $productId = $this->saveProduct($firstRow['product'], $rowNumber);

            // Create variants for each row
            foreach ($rows as $idx => $row) {
                $this->saveVariant($row['variant'], $productId, $rowNumber + $idx);
            }

            $this->db->transactionCommit();
            $this->stats['products']++;
        } catch (\Throwable $e) {
            $this->db->transactionRollback();
            throw $e;
        }
    }

    /**
     * Save a product to the database.
     *
     * @param array $productData Normalized product data
     * @param int   $rowNumber   Row number for error reporting
     *
     * @return int Product ID
     *
     * @since 0.3.0
     */
    private function saveProduct(array $productData, int $rowNumber): int
    {
        // Apply fallbacks
        $title = trim($productData['title']);

        if ($title === '') {
            $title = 'Imported Product ' . $rowNumber;
            $this->warnings[] = [
                'row'     => $rowNumber,
                'message' => 'Missing product title, using fallback',
                'field'   => 'title',
            ];
        }

        $slug = $productData['slug'] ?: OutputFilter::stringURLSafe($title);
        $slug = $this->ensureUniqueSlug($slug, 'products');

        // Handle categories
        $categoryIds = [];

        if (!empty($productData['categories']) && $this->options['create_categories']) {
            foreach ($productData['categories'] as $categoryName) {
                $categoryId = $this->getOrCreateCategory($categoryName);

                if ($categoryId) {
                    $categoryIds[] = $categoryId;
                }
            }
        }

        // Fallback to "Imported" category
        if (empty($categoryIds) && $this->options['create_categories']) {
            $categoryIds[] = $this->getOrCreateCategory('Imported');
        }

        $primaryCategoryId = !empty($categoryIds) ? $categoryIds[0] : null;

        // Sanitize HTML content
        $shortDesc = $this->sanitizeHtml($productData['short_desc'] ?? '');
        $longDesc = $this->sanitizeHtml($productData['long_desc'] ?? '');

        // Build images JSON
        $images = null;

        if (!empty($productData['images']) && $this->options['store_images']) {
            $images = json_encode($productData['images'], JSON_UNESCAPED_SLASHES);
        }

        // Insert product
        $now = (new \DateTime())->format('Y-m-d H:i:s');

        $product = (object) [
            'slug'                => $slug,
            'title'               => $this->truncate($title, 255),
            'short_desc'          => $shortDesc,
            'long_desc'           => $longDesc,
            'images'              => $images,
            'source_images'       => $images, // Keep original URLs
            'imported_from'       => $this->adapter->getName(),
            'original_id'         => $productData['original_id'] ?? null,
            'featured'            => (int) ($productData['featured'] ?? false),
            'active'              => (int) ($this->options['set_active'] ? ($productData['active'] ?? true) : ($productData['active'] ?? false)),
            'product_type'        => $productData['product_type'] ?? 'physical',
            'primary_category_id' => $primaryCategoryId,
            'created'             => $now,
            'created_by'          => $this->userId,
        ];

        $this->db->insertObject('#__nxp_easycart_products', $product);
        $productId = (int) $this->db->insertid();

        // Link categories
        foreach ($categoryIds as $catId) {
            $link = (object) [
                'product_id'  => $productId,
                'category_id' => $catId,
            ];

            try {
                $this->db->insertObject('#__nxp_easycart_product_categories', $link);
            } catch (\Throwable $e) {
                // Ignore duplicate key errors
            }
        }

        return $productId;
    }

    /**
     * Save a variant to the database.
     *
     * @param array $variantData Normalized variant data
     * @param int   $productId   Parent product ID
     * @param int   $rowNumber   Row number for error reporting
     *
     * @return int Variant ID
     *
     * @since 0.3.0
     */
    private function saveVariant(array $variantData, int $productId, int $rowNumber): int
    {
        // Handle SKU
        $sku = trim($variantData['sku'] ?? '');

        if ($sku === '') {
            // Generate SKU
            $sku = $this->generateSku($productId, $variantData['options'] ?? []);
        }

        $originalSku = $sku;
        $sku = $this->ensureUniqueSku($sku);

        if ($sku !== $originalSku) {
            $this->warnings[] = [
                'row'      => $rowNumber,
                'message'  => "Duplicate SKU '{$originalSku}' renamed to '{$sku}'",
                'field'    => 'sku',
                'original' => $originalSku,
                'new'      => $sku,
            ];
        }

        // Handle price
        $price = (float) ($variantData['price'] ?? 0);

        if ($price < 0) {
            $price = 0;
            $this->warnings[] = [
                'row'     => $rowNumber,
                'message' => 'Negative price converted to 0',
                'field'   => 'price',
            ];
        }

        $priceCents = (int) round($price * 100);

        // Handle sale price
        $salePriceCents = null;
        $saleStart = null;
        $saleEnd = null;

        if (isset($variantData['sale_price']) && $variantData['sale_price'] !== null) {
            $salePrice = (float) $variantData['sale_price'];

            if ($salePrice > 0 && $salePrice < $price) {
                $salePriceCents = (int) round($salePrice * 100);
                $saleStart = $variantData['sale_start'] ?? null;
                $saleEnd = $variantData['sale_end'] ?? null;
            }
        }

        // Currency validation
        $currency = strtoupper($variantData['currency'] ?? $this->defaultCurrency);

        if ($currency !== $this->defaultCurrency) {
            $this->warnings[] = [
                'row'     => $rowNumber,
                'message' => "Currency '{$currency}' does not match store currency '{$this->defaultCurrency}'",
                'field'   => 'currency',
            ];
            $currency = $this->defaultCurrency;
        }

        // EAN validation
        $ean = $variantData['ean'] ?? null;

        if ($ean !== null && $ean !== '') {
            // Validate EAN format
            if (!$this->isValidEan($ean)) {
                $this->warnings[] = [
                    'row'     => $rowNumber,
                    'message' => "Invalid EAN '{$ean}' (must be 8 or 13 digits with valid checksum)",
                    'field'   => 'ean',
                ];
                $ean = null;
            }
        } else {
            $ean = null;
        }

        // Build options JSON
        $options = null;

        if (!empty($variantData['options'])) {
            $options = json_encode($variantData['options'], JSON_UNESCAPED_UNICODE);
        }

        // Build original images JSON
        $originalImages = null;

        if (!empty($variantData['original_images']) && $this->options['store_images']) {
            $originalImages = json_encode($variantData['original_images'], JSON_UNESCAPED_SLASHES);
        }

        // Build variant display images JSON (for storefront image switching)
        $variantImages = null;

        if (!empty($variantData['images']) && \is_array($variantData['images']) && $this->options['store_images']) {
            // Filter to valid non-empty strings only
            $filteredImages = array_values(array_filter($variantData['images'], function ($img) {
                return \is_string($img) && trim($img) !== '';
            }));

            if (!empty($filteredImages)) {
                $variantImages = json_encode($filteredImages, JSON_UNESCAPED_SLASHES);
            }
        }

        // Insert variant
        $variant = (object) [
            'product_id'       => $productId,
            'sku'              => $this->truncate($sku, 64),
            'ean'              => $ean,
            'price_cents'      => $priceCents,
            'sale_price_cents' => $salePriceCents,
            'sale_start'       => $saleStart,
            'sale_end'         => $saleEnd,
            'currency'         => $currency,
            'stock'            => max(0, (int) ($variantData['stock'] ?? 0)),
            'options'          => $options,
            'images'           => $variantImages,
            'weight'           => round((float) ($variantData['weight'] ?? 0), 3),
            'active'           => (int) ($this->options['set_active'] ? ($variantData['active'] ?? true) : ($variantData['active'] ?? false)),
            'imported_from'    => $this->adapter->getName(),
            'original_id'      => $variantData['original_id'] ?? null,
            'original_images'  => $originalImages,
        ];

        $this->db->insertObject('#__nxp_easycart_variants', $variant);
        $variantId = (int) $this->db->insertid();

        // Track SKU
        $this->skuCache[strtolower($sku)] = true;
        $this->stats['variants']++;

        return $variantId;
    }

    /**
     * Get or create a category by name.
     *
     * @param string $name Category name
     *
     * @return int|null Category ID or null on failure
     *
     * @since 0.3.0
     */
    private function getOrCreateCategory(string $name): ?int
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        $slug = OutputFilter::stringURLSafe($name);
        $cacheKey = strtolower($slug);

        if (isset($this->categoryCache[$cacheKey])) {
            return $this->categoryCache[$cacheKey];
        }

        // Check if exists
        $query = $this->db->getQuery(true)
            ->select('id')
            ->from($this->db->quoteName('#__nxp_easycart_categories'))
            ->where($this->db->quoteName('slug') . ' = ' . $this->db->quote($slug));

        $this->db->setQuery($query);
        $existing = $this->db->loadResult();

        if ($existing) {
            $this->categoryCache[$cacheKey] = (int) $existing;

            return (int) $existing;
        }

        // Create new category
        $slug = $this->ensureUniqueSlug($slug, 'categories');

        $category = (object) [
            'slug'          => $slug,
            'title'         => $this->truncate($name, 255),
            'sort'          => 0,
            'imported_from' => $this->adapter->getName(),
        ];

        try {
            $this->db->insertObject('#__nxp_easycart_categories', $category);
            $categoryId = (int) $this->db->insertid();

            $this->categoryCache[$cacheKey] = $categoryId;
            $this->stats['categories']++;

            return $categoryId;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Ensure a slug is unique in a table.
     *
     * @param string $slug  Base slug
     * @param string $table Table name suffix (products, categories)
     *
     * @return string Unique slug
     *
     * @since 0.3.0
     */
    private function ensureUniqueSlug(string $slug, string $table): string
    {
        $tableName = '#__nxp_easycart_' . $table;
        $originalSlug = $slug;
        $counter = 1;

        do {
            $query = $this->db->getQuery(true)
                ->select('COUNT(*)')
                ->from($this->db->quoteName($tableName))
                ->where($this->db->quoteName('slug') . ' = ' . $this->db->quote($slug));

            $this->db->setQuery($query);
            $exists = (int) $this->db->loadResult() > 0;

            if ($exists) {
                $counter++;
                $slug = $originalSlug . '-' . $counter;
            }
        } while ($exists && $counter < 100);

        return $slug;
    }

    /**
     * Ensure a SKU is unique.
     *
     * @param string $sku Base SKU
     *
     * @return string Unique SKU
     *
     * @since 0.3.0
     */
    private function ensureUniqueSku(string $sku): string
    {
        $originalSku = $sku;
        $counter = 0;

        while ($this->skuExists($sku)) {
            $counter++;
            $suffix = '-' . str_pad((string) $counter, 3, '0', STR_PAD_LEFT);
            $sku = $this->truncate($originalSku, 64 - \strlen($suffix)) . $suffix;
        }

        return $sku;
    }

    /**
     * Check if a SKU exists.
     *
     * @param string $sku SKU to check
     *
     * @return bool
     *
     * @since 0.3.0
     */
    private function skuExists(string $sku): bool
    {
        $key = strtolower($sku);

        return isset($this->skuCache[$key]);
    }

    /**
     * Generate a SKU from product ID and options.
     *
     * @param int   $productId Product ID
     * @param array $options   Variant options
     *
     * @return string
     *
     * @since 0.3.0
     */
    private function generateSku(int $productId, array $options): string
    {
        $parts = ['IMPORT-' . $productId];

        foreach ($options as $opt) {
            if (isset($opt['value'])) {
                $parts[] = OutputFilter::stringURLSafe($opt['value']);
            }
        }

        return $this->truncate(strtoupper(implode('-', $parts)), 64);
    }

    /**
     * Load existing SKUs into cache.
     *
     * @return void
     *
     * @since 0.3.0
     */
    private function loadSkuCache(): void
    {
        $query = $this->db->getQuery(true)
            ->select('LOWER(sku)')
            ->from($this->db->quoteName('#__nxp_easycart_variants'));

        $this->db->setQuery($query);
        $skus = $this->db->loadColumn();

        foreach ($skus as $sku) {
            $this->skuCache[$sku] = true;
        }
    }

    /**
     * Validate EAN checksum.
     *
     * @param string $ean EAN string
     *
     * @return bool
     *
     * @since 0.3.0
     */
    private function isValidEan(string $ean): bool
    {
        $ean = preg_replace('/[\s\-]/', '', $ean);

        if (!ctype_digit($ean)) {
            return false;
        }

        $length = \strlen($ean);

        if ($length !== 8 && $length !== 13) {
            return false;
        }

        $digits = array_map('intval', str_split($ean));
        $checkDigit = array_pop($digits);

        $sum = 0;
        $count = \count($digits);

        foreach ($digits as $i => $digit) {
            $multiplier = (($count - 1 - $i) % 2 === 0) ? 1 : 3;
            $sum += $digit * $multiplier;
        }

        return ((10 - ($sum % 10)) % 10) === $checkDigit;
    }

    /**
     * Sanitize HTML content.
     *
     * @param string $html HTML string
     *
     * @return string
     *
     * @since 0.3.0
     */
    private function sanitizeHtml(string $html): string
    {
        if ($html === '') {
            return '';
        }

        return $this->inputFilter->clean($html, 'html');
    }

    /**
     * Truncate string to length.
     *
     * @param string $value  String to truncate
     * @param int    $length Max length
     *
     * @return string
     *
     * @since 0.3.0
     */
    private function truncate(string $value, int $length): string
    {
        if (mb_strlen($value) <= $length) {
            return $value;
        }

        return mb_substr($value, 0, $length);
    }

    /**
     * Reset statistics for a new import.
     *
     * @return void
     *
     * @since 0.3.0
     */
    public function resetStats(): void
    {
        $this->stats = [
            'products'   => 0,
            'variants'   => 0,
            'categories' => 0,
            'skipped'    => 0,
        ];
        $this->errors = [];
        $this->warnings = [];
    }

    /**
     * Get current statistics.
     *
     * @return array
     *
     * @since 0.3.0
     */
    public function getStats(): array
    {
        return $this->stats;
    }
}
