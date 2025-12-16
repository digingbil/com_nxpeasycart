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

/**
 * Interface for platform-specific import adapters.
 *
 * Each adapter handles CSV format differences for a specific e-commerce platform,
 * normalizing data to a common format for import processing.
 *
 * @since 0.3.0
 */
interface PlatformAdapterInterface
{
    /**
     * Platform identifier (lowercase, no spaces).
     *
     * @return string e.g., 'shopify', 'woocommerce', 'virtuemart', 'hikashop', 'native'
     *
     * @since 0.3.0
     */
    public function getName(): string;

    /**
     * Human-readable display name.
     *
     * @return string e.g., 'Shopify', 'WooCommerce', 'VirtueMart', 'HikaShop', 'Native'
     *
     * @since 0.3.0
     */
    public function getDisplayName(): string;

    /**
     * Get default column mappings for this platform.
     *
     * Returns an associative array mapping source CSV headers to target fields.
     * Keys are CSV header names, values are target field identifiers.
     *
     * @return array<string, string> e.g., ['Title' => 'product.title', 'Variant SKU' => 'variant.sku']
     *
     * @since 0.3.0
     */
    public function getDefaultMapping(): array;

    /**
     * Get headers that uniquely identify this platform's CSV export.
     *
     * Used for auto-detection of platform from uploaded CSV.
     * Returns headers that, if ALL present, indicate this platform.
     *
     * @return array<int, string> e.g., ['Handle', 'Variant SKU', 'Option1 Name']
     *
     * @since 0.3.0
     */
    public function getSignatureHeaders(): array;

    /**
     * Transform a source CSV row to normalized format.
     *
     * @param array<string, string> $row     Raw CSV row data (header => value)
     * @param array<string, string> $mapping Column mapping configuration
     *
     * @return array{
     *     product: array{
     *         title: string,
     *         slug: string,
     *         short_desc: string,
     *         long_desc: string,
     *         active: bool,
     *         featured: bool,
     *         product_type: string,
     *         categories: array<int, string>,
     *         images: array<int, string>,
     *         original_id: string
     *     },
     *     variant: array{
     *         sku: string,
     *         price: float,
     *         sale_price: ?float,
     *         sale_start: ?string,
     *         sale_end: ?string,
     *         currency: string,
     *         stock: int,
     *         weight: float,
     *         ean: ?string,
     *         is_digital: bool,
     *         active: bool,
     *         options: array<int, array{name: string, value: string}>,
     *         original_images: array<int, string>,
     *         original_id: string
     *     }
     * }
     *
     * @since 0.3.0
     */
    public function normalizeRow(array $row, array $mapping): array;

    /**
     * Validate a normalized row for required fields.
     *
     * Returns an array of error messages. Empty array means valid.
     *
     * @param array $normalizedRow Output from normalizeRow()
     *
     * @return array<int, string> Error messages, empty if valid
     *
     * @since 0.3.0
     */
    public function validate(array $normalizedRow): array;

    /**
     * Whether this platform uses multi-row variant format.
     *
     * If true, multiple CSV rows belong to the same product (e.g., Shopify).
     * If false, each row is a single variant (e.g., WooCommerce simple export).
     *
     * @return bool
     *
     * @since 0.3.0
     */
    public function shouldGroupVariants(): bool;

    /**
     * Get the column used for grouping variants.
     *
     * Only relevant if shouldGroupVariants() returns true.
     * Rows with the same value in this column belong to the same product.
     *
     * @return string|null e.g., 'Handle' for Shopify, null if not applicable
     *
     * @since 0.3.0
     */
    public function getGroupingColumn(): ?string;

    /**
     * Get the weight unit used by this platform.
     *
     * Used to convert weights to kg for storage.
     *
     * @return string One of: 'g', 'kg', 'lb', 'oz'
     *
     * @since 0.3.0
     */
    public function getWeightUnit(): string;

    /**
     * Get category path separator used by this platform.
     *
     * @return string e.g., '>', '|', ','
     *
     * @since 0.3.0
     */
    public function getCategorySeparator(): string;
}
