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

use Joomla\CMS\Filter\OutputFilter;
use Joomla\Component\Nxpeasycart\Administrator\Service\Import\PlatformAdapterInterface;

/**
 * Abstract base class for platform adapters.
 *
 * Provides common utility methods for data normalization and validation.
 *
 * @since 0.3.0
 */
abstract class AbstractPlatformAdapter implements PlatformAdapterInterface
{
    /**
     * Weight conversion factors to kilograms.
     *
     * @var array<string, float>
     */
    protected const WEIGHT_FACTORS = [
        'g'  => 0.001,
        'kg' => 1.0,
        'lb' => 0.453592,
        'oz' => 0.0283495,
    ];

    /**
     * Default currency when not specified.
     *
     * @var string
     */
    protected string $defaultCurrency = 'EUR';

    /**
     * Set the default currency for imports.
     *
     * @param string $currency
     *
     * @return void
     *
     * @since 0.3.0
     */
    public function setDefaultCurrency(string $currency): void
    {
        $this->defaultCurrency = strtoupper($currency);
    }

    /**
     * {@inheritdoc}
     */
    public function validate(array $normalizedRow): array
    {
        $errors = [];

        // Product title is required (but we'll use fallback)
        if (empty($normalizedRow['product']['title'])) {
            $errors[] = 'Product title is empty';
        }

        // Price must be non-negative
        if (($normalizedRow['variant']['price'] ?? 0) < 0) {
            $errors[] = 'Price cannot be negative';
        }

        return $errors;
    }

    /**
     * Get mapped value from row with fallback.
     *
     * @param array<string, string> $row     CSV row data
     * @param array<string, string> $mapping Column mapping
     * @param string                $target  Target field (e.g., 'product.title')
     * @param mixed                 $default Default value if not found
     *
     * @return mixed
     *
     * @since 0.3.0
     */
    protected function getMappedValue(array $row, array $mapping, string $target, $default = '')
    {
        // Find the source column for this target
        $sourceColumn = array_search($target, $mapping, true);

        if ($sourceColumn === false) {
            return $default;
        }

        return $row[$sourceColumn] ?? $default;
    }

    /**
     * Get value directly from row by column name.
     *
     * @param array<string, string> $row     CSV row data
     * @param string                $column  Column name
     * @param mixed                 $default Default value
     *
     * @return mixed
     *
     * @since 0.3.0
     */
    protected function getValue(array $row, string $column, $default = '')
    {
        // First try exact match
        if (isset($row[$column])) {
            return $row[$column];
        }

        // Try case-insensitive match
        $columnLower = strtolower($column);
        foreach ($row as $key => $value) {
            if (strtolower($key) === $columnLower) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * Parse a price string to float.
     *
     * Handles various formats: "24.99", "€24,99", "$1,234.56", etc.
     *
     * @param string $value Price string
     *
     * @return float
     *
     * @since 0.3.0
     */
    protected function parsePrice(string $value): float
    {
        if ($value === '') {
            return 0.0;
        }

        // Remove currency symbols and whitespace
        $cleaned = preg_replace('/[^\d.,\-]/', '', $value);

        // Handle European format (comma as decimal separator)
        // If there's a comma after the last period, it's a decimal separator
        if (preg_match('/\d+\.\d+,\d+$/', $cleaned)) {
            // Format like 1.234,56 - remove dots, replace comma with dot
            $cleaned = str_replace('.', '', $cleaned);
            $cleaned = str_replace(',', '.', $cleaned);
        } elseif (preg_match('/\d+,\d{1,2}$/', $cleaned) && strpos($cleaned, '.') === false) {
            // Format like 24,99 - just comma as decimal
            $cleaned = str_replace(',', '.', $cleaned);
        } else {
            // Format like 1,234.56 - remove commas (thousand separators)
            $cleaned = str_replace(',', '', $cleaned);
        }

        return (float) $cleaned;
    }

    /**
     * Parse a stock quantity to integer.
     *
     * @param string $value Stock value
     *
     * @return int
     *
     * @since 0.3.0
     */
    protected function parseStock(string $value): int
    {
        if ($value === '' || $value === 'null' || $value === 'N/A') {
            return 0;
        }

        return max(0, (int) $value);
    }

    /**
     * Parse weight and convert to kilograms.
     *
     * @param string $value Weight value
     *
     * @return float Weight in kg
     *
     * @since 0.3.0
     */
    protected function parseWeight(string $value): float
    {
        if ($value === '') {
            return 0.0;
        }

        $weight = (float) preg_replace('/[^\d.]/', '', $value);
        $factor = self::WEIGHT_FACTORS[$this->getWeightUnit()] ?? 1.0;

        return round($weight * $factor, 3);
    }

    /**
     * Parse a boolean value from various string formats.
     *
     * @param string $value Boolean-like value
     *
     * @return bool
     *
     * @since 0.3.0
     */
    protected function parseBoolean(string $value): bool
    {
        $value = strtolower(trim($value));

        return \in_array($value, ['1', 'true', 'yes', 'on', 'published', 'active', 'enabled'], true);
    }

    /**
     * Generate a URL-safe slug from a string.
     *
     * @param string $value Input string
     *
     * @return string
     *
     * @since 0.3.0
     */
    protected function generateSlug(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return OutputFilter::stringURLSafe($value);
    }

    /**
     * Parse a comma/pipe separated string into array.
     *
     * @param string $value     Input string
     * @param string $separator Separator character
     *
     * @return array<int, string>
     *
     * @since 0.3.0
     */
    protected function parseList(string $value, string $separator = ','): array
    {
        if ($value === '') {
            return [];
        }

        $items = explode($separator, $value);

        return array_values(array_filter(array_map('trim', $items)));
    }

    /**
     * Parse hierarchical category path.
     *
     * @param string $value     Category path (e.g., "Clothing > T-Shirts")
     * @param string $separator Path separator
     *
     * @return array<int, string> Array of category names from root to leaf
     *
     * @since 0.3.0
     */
    protected function parseCategoryPath(string $value, string $separator = '>'): array
    {
        if ($value === '') {
            return [];
        }

        // Normalize separators
        $normalized = str_replace(['|', '/'], $separator, $value);
        $parts = explode($separator, $normalized);

        return array_values(array_filter(array_map('trim', $parts)));
    }

    /**
     * Parse image URLs from various formats.
     *
     * @param string $value Image URLs (comma or newline separated)
     *
     * @return array<int, string>
     *
     * @since 0.3.0
     */
    protected function parseImages(string $value): array
    {
        if ($value === '') {
            return [];
        }

        // Replace newlines with commas
        $value = str_replace(["\r\n", "\r", "\n"], ',', $value);

        $urls = $this->parseList($value, ',');

        // Filter to only valid URLs
        return array_values(array_filter($urls, function (string $url): bool {
            return filter_var($url, FILTER_VALIDATE_URL) !== false;
        }));
    }

    /**
     * Parse and validate EAN barcode.
     *
     * @param string $value EAN value
     *
     * @return string|null Valid EAN or null if invalid
     *
     * @since 0.3.0
     */
    protected function parseEan(string $value): ?string
    {
        // Remove any whitespace or dashes
        $ean = preg_replace('/[\s\-]/', '', $value);

        // Must be digits only
        if (!ctype_digit($ean)) {
            return null;
        }

        // Must be 8 or 13 digits
        $length = \strlen($ean);

        if ($length !== 8 && $length !== 13) {
            return null;
        }

        // Validate checksum
        if (!$this->validateEanChecksum($ean)) {
            return null;
        }

        return $ean;
    }

    /**
     * Validate EAN checksum using GS1 algorithm.
     *
     * @param string $ean EAN-8 or EAN-13 string
     *
     * @return bool
     *
     * @since 0.3.0
     */
    protected function validateEanChecksum(string $ean): bool
    {
        $digits = array_map('intval', str_split($ean));
        $checkDigit = array_pop($digits);

        $sum = 0;
        $length = \count($digits);

        foreach ($digits as $i => $digit) {
            // GS1 standard: positions 1,3,5... (odd, 1-indexed) multiply by 1,
            // positions 2,4,6... (even, 1-indexed) multiply by 3
            // From the right side perspective of the check digit position
            $multiplier = (($length - $i) % 2 === 0) ? 3 : 1;
            $sum += $digit * $multiplier;
        }

        $calculatedCheck = (10 - ($sum % 10)) % 10;

        return $calculatedCheck === $checkDigit;
    }

    /**
     * Parse datetime string.
     *
     * @param string $value Datetime value
     *
     * @return string|null ISO 8601 datetime or null
     *
     * @since 0.3.0
     */
    protected function parseDatetime(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        try {
            $date = new \DateTime($value);

            return $date->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Clean and sanitize HTML content.
     *
     * @param string $value HTML content
     *
     * @return string
     *
     * @since 0.3.0
     */
    protected function sanitizeHtml(string $value): string
    {
        // Basic cleanup - full sanitization happens in ImportProcessor
        $value = trim($value);

        // Convert common HTML entities
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $value;
    }

    /**
     * Truncate string to max length.
     *
     * @param string $value     Input string
     * @param int    $maxLength Maximum length
     *
     * @return string
     *
     * @since 0.3.0
     */
    protected function truncate(string $value, int $maxLength): string
    {
        if (mb_strlen($value) <= $maxLength) {
            return $value;
        }

        return mb_substr($value, 0, $maxLength - 3) . '...';
    }

    /**
     * Create empty normalized structure.
     *
     * @return array
     *
     * @since 0.3.0
     */
    protected function createEmptyNormalized(): array
    {
        return [
            'product' => [
                'title'        => '',
                'slug'         => '',
                'short_desc'   => '',
                'long_desc'    => '',
                'active'       => true,
                'featured'     => false,
                'product_type' => 'physical',
                'categories'   => [],
                'images'       => [],
                'original_id'  => '',
            ],
            'variant' => [
                'sku'             => '',
                'price'           => 0.0,
                'sale_price'      => null,
                'sale_start'      => null,
                'sale_end'        => null,
                'currency'        => $this->defaultCurrency,
                'stock'           => 0,
                'weight'          => 0.0,
                'ean'             => null,
                'is_digital'      => false,
                'active'          => true,
                'options'         => [],
                'original_images' => [],
                'original_id'     => '',
            ],
        ];
    }

    /**
     * Infer option name from value using heuristics.
     *
     * Used when platform doesn't provide explicit option names.
     *
     * @param string $value Option value
     *
     * @return string Inferred name
     *
     * @since 0.3.0
     */
    protected function inferOptionName(string $value): string
    {
        $value = trim($value);
        $lower = strtolower($value);

        // Common color names
        $colors = [
            'black', 'white', 'red', 'blue', 'green', 'yellow', 'orange', 'purple',
            'pink', 'brown', 'gray', 'grey', 'beige', 'navy', 'teal', 'maroon',
            'silver', 'gold', 'bronze', 'cream', 'ivory', 'tan', 'coral', 'cyan',
        ];

        if (\in_array($lower, $colors, true)) {
            return 'Color';
        }

        // Common size patterns
        $sizes = ['xs', 's', 'm', 'l', 'xl', 'xxl', 'xxxl', '2xl', '3xl', '4xl'];

        if (\in_array($lower, $sizes, true) || preg_match('/^\d{1,3}(cm|mm|in|inch|"|\')?$/i', $value)) {
            return 'Size';
        }

        // Numeric only might be size or quantity
        if (is_numeric($value)) {
            return 'Size';
        }

        return 'Option';
    }
}
