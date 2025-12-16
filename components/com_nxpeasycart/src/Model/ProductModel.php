<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\ParameterType;
use Joomla\Component\Nxpeasycart\Administrator\Helper\ConfigHelper;
use Joomla\Component\Nxpeasycart\Administrator\Helper\MoneyHelper;
use Joomla\Component\Nxpeasycart\Administrator\Helper\PriceHelper;
use Joomla\Component\Nxpeasycart\Administrator\Helper\ProductStatus;
use Joomla\Component\Nxpeasycart\Site\Helper\CategoryPathHelper;

/**
 * Frontend product model.
 *
 * @since 0.1.5
 */
class ProductModel extends BaseDatabaseModel
{
    /**
     * Cached product payload.
     *
     * @var array<string, mixed>|null
     *
     * @since 0.1.5
     */
    protected ?array $item = null;

    /**
     * {@inheritDoc}
     *
     * @since 0.1.5
     */
    protected function populateState($ordering = null, $direction = null)
    {
        $app   = Factory::getApplication();
        $input = $app->input;

        $this->setState('product.id', $input->getInt('id'));
        $this->setState('product.slug', $input->getCmd('slug', ''));

        // Store the category path from the URL for breadcrumb fallback
        $this->setState('product.category_path', $input->getString('category_path', ''));
    }

    /**
     * Retrieve the current product payload.
     *
     * @since 0.1.5
     */
    public function getItem(): ?array
    {
        if ($this->item !== null) {
            return $this->item;
        }

        $db    = $this->getDatabase();
        $activeStatus = ProductStatus::ACTIVE;
        $outOfStockStatus = ProductStatus::OUT_OF_STOCK;
        $query = $db->getQuery(true)
            ->select('p.*')
            ->from($db->quoteName('#__nxp_easycart_products', 'p'))
            ->where(
                $db->quoteName('p.active') . ' IN (:activeStatus, :outOfStockStatus)'
            )
            ->bind(':activeStatus', $activeStatus, ParameterType::INTEGER)
            ->bind(':outOfStockStatus', $outOfStockStatus, ParameterType::INTEGER);

        $id   = (int) $this->getState('product.id');
        $slug = (string) $this->getState('product.slug');

        if ($id > 0) {
            $query->where($db->quoteName('p.id') . ' = :productId')
                ->bind(':productId', $id, ParameterType::INTEGER);
        } elseif ($slug !== '') {
            $query->where($db->quoteName('p.slug') . ' = :productSlug')
                ->bind(':productSlug', $slug, ParameterType::STRING);
        } else {
            return null;
        }

        $db->setQuery($query);
        $product = $db->loadObject();

        // Fallback to allow rendering an unavailable product page when explicitly requested.
        if (!$product && $slug !== '') {
            $fallbackQuery = $db->getQuery(true)
                ->select('p.*')
                ->from($db->quoteName('#__nxp_easycart_products', 'p'))
                ->where($db->quoteName('p.slug') . ' = :productSlug')
                ->bind(':productSlug', $slug, ParameterType::STRING)
                ->setLimit(1);

            $db->setQuery($fallbackQuery);
            $product = $db->loadObject();
        }

        if (!$product) {
            return null;
        }

        $images       = $this->decodeImages($product->images ?? '');
        $variants     = $this->fetchVariants((int) $product->id);
        $primaryCategoryId = $product->primary_category_id !== null ? (int) $product->primary_category_id : null;
        $categories   = $this->fetchCategories((int) $product->id, $primaryCategoryId);

        if ($primaryCategoryId === null && !empty($categories)) {
            $primaryCategoryId = $categories[0]['id'] ?? null;
        }

        $primaryCategoryPath = $primaryCategoryId !== null
            ? CategoryPathHelper::getPath($db, (int) $primaryCategoryId)
            : [];

        // Fall back to URL category path if product doesn't have a primary category
        if (empty($primaryCategoryPath)) {
            $urlCategoryPath = (string) $this->getState('product.category_path', '');

            if ($urlCategoryPath !== '') {
                $primaryCategoryPath = array_filter(
                    array_map('trim', explode('/', $urlCategoryPath))
                );
            }
        }

        $primaryCategorySlug = !empty($primaryCategoryPath) ? (string) end($primaryCategoryPath) : null;
        $priceSummary = $this->summarisePrices($variants);
        $stockTotals  = $this->summariseStock($variants);
        $status       = ProductStatus::normalise($product->active ?? ProductStatus::INACTIVE);
        $available    = ProductStatus::isPurchasable($status) && $stockTotals['total'] > 0;

        $this->item = [
            'id'         => (int) $product->id,
            'slug'       => (string) $product->slug,
            'title'      => (string) $product->title,
            'short_desc' => (string) ($product->short_desc ?? ''),
            'long_desc'  => (string) ($product->long_desc ?? ''),
            'status'     => $status,
            'active'     => ProductStatus::isPurchasable($status),
            'out_of_stock' => ProductStatus::isOutOfStock($status),
            'featured'   => (bool) ($product->featured ?? 0),
            'images'     => $images,
            'variants'   => $variants,
            'categories' => $categories,
            'primary_category_id' => $primaryCategoryId,
            'primary_category_slug' => $primaryCategorySlug,
            'primary_category_path' => !empty($primaryCategoryPath) ? implode('/', $primaryCategoryPath) : null,
            'price'      => $priceSummary,
            'stock'      => $stockTotals,
            'available'  => $available,
            'created'    => (string) ($product->created ?? ''),
            'modified'   => $product->modified !== null ? (string) $product->modified : null,
        ];

        return $this->item;
    }

    /**
     * Decode image payload.
     *
     * @return array<int, string>
     *
     * @since 0.1.5
     */
    private function decodeImages(string $images): array
    {
        if ($images === '') {
            return [];
        }

        $decoded = json_decode($images, true);

        if (json_last_error() !== JSON_ERROR_NONE || !\is_array($decoded)) {
            return [];
        }

        $filtered = array_filter(
            array_map(
                static function ($url) {
                    if (!\is_string($url)) {
                        return null;
                    }

                    $trimmed = trim($url);

                    if ($trimmed === '') {
                        return null;
                    }

                    if (
                        !str_starts_with($trimmed, 'http://')
                        && !str_starts_with($trimmed, 'https://')
                        && !str_starts_with($trimmed, '//')
                    ) {
                        $base     = rtrim(Uri::root(true), '/');
                        $relative = '/' . ltrim($trimmed, '/');

                        $trimmed = ($base === '' ? '' : $base) . $relative;
                    }

                    return $trimmed;
                },
                $decoded
            )
        );

        return array_values($filtered);
    }

    /**
     * Fetch product variants for the storefront.
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 0.1.5
     */
    private function fetchVariants(int $productId): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__nxp_easycart_variants'))
            ->where($db->quoteName('product_id') . ' = :productId')
            ->where($db->quoteName('active') . ' = 1')
            ->order($db->quoteName('id') . ' ASC')
            ->bind(':productId', $productId, ParameterType::INTEGER);

        $db->setQuery($query);

        $rows         = $db->loadObjectList() ?: [];
        $baseCurrency = ConfigHelper::getBaseCurrency();

        $variants = [];

        foreach ($rows as $row) {
            $options = [];

            if (!empty($row->options)) {
                $decoded = json_decode($row->options, true);

                if (json_last_error() === JSON_ERROR_NONE && \is_array($decoded)) {
                    $options = array_filter($decoded, static fn ($option) => \is_array($option));
                }
            }

            // Decode variant-specific images (null means inherit from product)
            $variantImages = $this->decodeVariantImages($row->images ?? null);

            // Single-currency MVP: always use configured base currency
            $currency = $baseCurrency;

            // Resolve effective price considering sale pricing
            $priceResolution = PriceHelper::resolve($row);

            $variants[] = [
                'id'                    => (int) $row->id,
                'sku'                   => (string) $row->sku,
                'price_cents'           => (int) $row->price_cents,
                'price'                 => MoneyHelper::format((int) $row->price_cents, $currency),
                'sale_price_cents'      => $row->sale_price_cents !== null ? (int) $row->sale_price_cents : null,
                'sale_price'            => $row->sale_price_cents !== null ? MoneyHelper::format((int) $row->sale_price_cents, $currency) : null,
                'sale_start'            => $row->sale_start ?? null,
                'sale_end'              => $row->sale_end ?? null,
                'effective_price_cents' => $priceResolution['effective_price_cents'],
                'effective_price'       => MoneyHelper::format($priceResolution['effective_price_cents'], $currency),
                'is_on_sale'            => $priceResolution['is_on_sale'],
                'sale_active'           => $priceResolution['sale_active'],
                'discount_percent'      => $priceResolution['discount_percent'],
                'currency'              => $currency,
                'stock'                 => (int) $row->stock,
                'weight'                => $row->weight !== null ? (float) $row->weight : null,
                'options'               => $options,
                'images'                => $variantImages,
            ];
        }

        return $variants;
    }

    /**
     * Decode variant images JSON to an array of URLs.
     *
     * @param string|null $json Raw JSON from database
     *
     * @return array<int, string>|null Array of image URLs or null to inherit from product
     *
     * @since 0.3.1
     */
    private function decodeVariantImages(?string $json): ?array
    {
        if ($json === null || $json === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE || !\is_array($decoded)) {
            return null;
        }

        $filtered = array_filter(
            array_map(
                static function ($url) {
                    if (!\is_string($url)) {
                        return null;
                    }

                    $trimmed = trim($url);

                    if ($trimmed === '') {
                        return null;
                    }

                    // Normalise relative paths to root-relative URLs
                    if (
                        !str_starts_with($trimmed, 'http://')
                        && !str_starts_with($trimmed, 'https://')
                        && !str_starts_with($trimmed, '//')
                    ) {
                        $base     = rtrim(Uri::root(true), '/');
                        $relative = '/' . ltrim($trimmed, '/');

                        $trimmed = ($base === '' ? '' : $base) . $relative;
                    }

                    return $trimmed;
                },
                $decoded
            )
        );

        $result = array_values($filtered);

        return empty($result) ? null : $result;
    }

    /**
     * Summarise variant stock totals.
     *
     * @param array<int, array<string, mixed>> $variants
     *
     * @since 0.1.5
     */
    private function summariseStock(array $variants): array
    {
        $total = 0;

        foreach ($variants as $variant) {
            $total += (int) ($variant['stock'] ?? 0);
        }

        return [
            'total' => $total,
            'low'   => $total > 0 && $total <= 5,
            'zero'  => $total <= 0,
        ];
    }

    /**
     * Fetch categories attached to the product.
     *
     * @return array<int, array<string, mixed>>
     *
     * @since 0.1.5
     */
    private function fetchCategories(int $productId, ?int $primaryCategoryId = null): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('c.id'),
                $db->quoteName('c.title'),
                $db->quoteName('c.slug'),
            ])
            ->from($db->quoteName('#__nxp_easycart_categories', 'c'))
            ->innerJoin(
                $db->quoteName('#__nxp_easycart_product_categories', 'pc')
                . ' ON ' . $db->quoteName('pc.category_id') . ' = ' . $db->quoteName('c.id')
            )
            ->where($db->quoteName('pc.product_id') . ' = :productId')
            ->order($db->quoteName('c.title') . ' ASC')
            ->bind(':productId', $productId, ParameterType::INTEGER);

        $db->setQuery($query);

        $rows = $db->loadObjectList() ?: [];

        return array_map(
            static fn ($row) => [
                'id'    => (int) $row->id,
                'title' => (string) $row->title,
                'slug'  => (string) $row->slug,
                'primary' => $primaryCategoryId !== null && (int) $row->id === $primaryCategoryId,
            ],
            $rows
        );
    }

    /**
     * Build price summary from variants.
     *
     * Returns both regular prices and effective prices (sale price when active).
     *
     * @param array<int, array<string, mixed>> $variants
     *
     * @return array<string, mixed>
     *
     * @since 0.1.5
     */
    private function summarisePrices(array $variants): array
    {
        if (empty($variants)) {
            $currency = ConfigHelper::getBaseCurrency();

            return [
                'currency'             => $currency,
                'min_cents'            => 0,
                'max_cents'            => 0,
                'effective_min_cents'  => 0,
                'effective_max_cents'  => 0,
                'has_sale'             => false,
                'any_sale_active'      => false,
            ];
        }

        $regularMin      = null;
        $regularMax      = null;
        $effectiveMin    = null;
        $effectiveMax    = null;
        $hasSale         = false;
        $anySaleActive   = false;
        $currency        = $variants[0]['currency'] ?? ConfigHelper::getBaseCurrency();

        foreach ($variants as $variant) {
            // Regular price (always the original price)
            $regularPrice = (int) ($variant['price_cents'] ?? 0);
            // Effective price (sale price when active, regular otherwise)
            $effectivePrice = (int) ($variant['effective_price_cents'] ?? $regularPrice);

            if ($regularMin === null || $regularPrice < $regularMin) {
                $regularMin = $regularPrice;
            }
            if ($regularMax === null || $regularPrice > $regularMax) {
                $regularMax = $regularPrice;
            }

            if ($effectiveMin === null || $effectivePrice < $effectiveMin) {
                $effectiveMin = $effectivePrice;
            }
            if ($effectiveMax === null || $effectivePrice > $effectiveMax) {
                $effectiveMax = $effectivePrice;
            }

            if (!empty($variant['is_on_sale'])) {
                $hasSale = true;
            }

            if (!empty($variant['sale_active'])) {
                $anySaleActive = true;
            }
        }

        return [
            'currency'             => $currency,
            'min_cents'            => (int) $regularMin,
            'max_cents'            => (int) $regularMax,
            'effective_min_cents'  => (int) $effectiveMin,
            'effective_max_cents'  => (int) $effectiveMax,
            'has_sale'             => $hasSale,
            'any_sale_active'      => $anySaleActive,
        ];
    }

}
