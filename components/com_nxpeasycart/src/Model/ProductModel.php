<?php

namespace Joomla\Component\Nxpeasycart\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\ParameterType;
use Joomla\Component\Nxpeasycart\Administrator\Helper\ConfigHelper;

/**
 * Frontend product model.
 */
class ProductModel extends BaseDatabaseModel
{
    /**
     * Cached product payload.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $item = null;

    /**
     * {@inheritDoc}
     */
    protected function populateState($ordering = null, $direction = null)
    {
        $app   = Factory::getApplication();
        $input = $app->input;

        $this->setState('product.id', $input->getInt('id'));
        $this->setState('product.slug', $input->getCmd('slug', ''));
    }

    /**
     * Retrieve the current product payload.
     */
    public function getItem(): ?array
    {
        if ($this->item !== null) {
            return $this->item;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('p.*')
            ->from($db->quoteName('#__nxp_easycart_products', 'p'))
            ->where($db->quoteName('p.active') . ' = 1');

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
        $categories   = $this->fetchCategories((int) $product->id);
        $priceSummary = $this->summarisePrices($variants);
        $stockTotals  = $this->summariseStock($variants);
        $available    = ((bool) $product->active) && $stockTotals['total'] > 0;

        $this->item = [
            'id'         => (int) $product->id,
            'slug'       => (string) $product->slug,
            'title'      => (string) $product->title,
            'short_desc' => (string) ($product->short_desc ?? ''),
            'long_desc'  => (string) ($product->long_desc ?? ''),
            'active'     => (bool) $product->active,
            'featured'   => (bool) ($product->featured ?? 0),
            'images'     => $images,
            'variants'   => $variants,
            'categories' => $categories,
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

            $currency = strtoupper((string) ($row->currency ?? $baseCurrency));

            $variants[] = [
                'id'          => (int) $row->id,
                'sku'         => (string) $row->sku,
                'price_cents' => (int) $row->price_cents,
                'price'       => $this->formatMoney((int) $row->price_cents, $currency),
                'currency'    => $currency,
                'stock'       => (int) $row->stock,
                'weight'      => $row->weight !== null ? (float) $row->weight : null,
                'options'     => $options,
            ];
        }

        return $variants;
    }

    /**
     * Summarise variant stock totals.
     *
     * @param array<int, array<string, mixed>> $variants
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
     */
    private function fetchCategories(int $productId): array
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
            ],
            $rows
        );
    }

    /**
     * Build price summary from variants.
     *
     * @param array<int, array<string, mixed>> $variants
     *
     * @return array<string, mixed>
     */
    private function summarisePrices(array $variants): array
    {
        if (empty($variants)) {
            $currency = ConfigHelper::getBaseCurrency();

            return [
                'currency'  => $currency,
                'min_cents' => 0,
                'max_cents' => 0,
            ];
        }

        $min      = null;
        $max      = null;
        $currency = $variants[0]['currency'] ?? ConfigHelper::getBaseCurrency();

        foreach ($variants as $variant) {
            $price = (int) ($variant['price_cents'] ?? 0);

            if ($min === null || $price < $min) {
                $min = $price;
            }

            if ($max === null || $price > $max) {
                $max = $price;
            }
        }

        return [
            'currency'  => $currency,
            'min_cents' => (int) $min,
            'max_cents' => (int) $max,
        ];
    }

    /**
     * Format a monetary amount.
     */
    private function formatMoney(int $cents, string $currency): string
    {
        $amount = $cents / 100;

        if (class_exists(\NumberFormatter::class, false)) {
            try {
                $language = Factory::getApplication()->getLanguage();
                $locale   = str_replace('-', '_', $language->getTag() ?: 'en_GB');

                $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);

                $formatted = $formatter->formatCurrency($amount, $currency);

                if ($formatted !== false) {
                    return (string) $formatted;
                }
            } catch (\Throwable $exception) {
                // Fallback below.
            }
        }

        return sprintf('%s %.2f', $currency, $amount);
    }
}
