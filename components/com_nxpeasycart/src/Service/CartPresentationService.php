<?php

namespace Nxp\EasyCart\Site\Service;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;
use Nxp\EasyCart\Admin\Administrator\Helper\ConfigHelper;

/**
 * Hydrates cart payloads with product and variant metadata for storefront views.
 */
class CartPresentationService
{
    /**
     * @var DatabaseInterface
     */
    private DatabaseInterface $db;

    /**
     * CartPresentationService constructor.
     */
    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Hydrate a stored cart payload with presentation data.
     *
     * @param array<string, mixed> $cart
     *
     * @return array<string, mixed>
     */
    public function hydrate(array $cart): array
    {
        $items = $cart['data']['items'] ?? [];
        $hydrated = $this->hydrateItems(\is_array($items) ? $items : []);

        $cart['items'] = $hydrated;
        $cart['summary'] = $this->buildSummary($hydrated);

        return $cart;
    }

    /**
     * Join cart line items with product + variant metadata.
     *
     * @param array<int, array<string, mixed>> $items
     *
     * @return array<int, array<string, mixed>>
     */
    private function hydrateItems(array $items): array
    {
        if (!$items) {
            return [];
        }

        $productIds = array_unique(
            array_filter(
                array_map(static fn ($item) => isset($item['product_id']) ? (int) $item['product_id'] : 0, $items)
            )
        );

        $variantIds = array_unique(
            array_filter(
                array_map(static fn ($item) => isset($item['variant_id']) ? (int) $item['variant_id'] : 0, $items)
            )
        );

        $products = $productIds ? $this->fetchProducts($productIds) : [];
        $variants = $variantIds ? $this->fetchVariants($variantIds) : [];

        $baseCurrency = ConfigHelper::getBaseCurrency();

        return array_values(
            array_map(
                static function ($item) use ($products, $variants, $baseCurrency) {
                    $productId = isset($item['product_id']) ? (int) $item['product_id'] : null;
                    $variantId = isset($item['variant_id']) ? (int) $item['variant_id'] : null;
                    $qty = isset($item['qty']) ? max(1, (int) $item['qty']) : 1;

                    $product = $productId !== null && isset($products[$productId]) ? $products[$productId] : null;
                    $variant = $variantId !== null && isset($variants[$variantId]) ? $variants[$variantId] : null;

                    $priceCents = isset($item['unit_price_cents'])
                        ? (int) $item['unit_price_cents']
                        : ($variant['price_cents'] ?? 0);

                    $currency = isset($item['currency'])
                        ? strtoupper((string) $item['currency'])
                        : ($variant['currency'] ?? $baseCurrency);

                    $title = isset($item['title'])
                        ? (string) $item['title']
                        : ($variant['title'] ?? ($product['title'] ?? ''));

                    return [
                        'id' => $variantId ?? $productId ?? spl_object_id((object) $item),
                        'product_id' => $productId,
                        'variant_id' => $variantId,
                        'title' => $title,
                        'product_title' => $product['title'] ?? $title,
                        'qty' => $qty,
                        'unit_price_cents' => $priceCents,
                        'currency' => $currency,
                        'total_cents' => $priceCents * $qty,
                        'sku' => $variant['sku'] ?? ($item['sku'] ?? null),
                        'image' => $variant['image'] ?? ($product['image'] ?? null),
                        'options' => $variant['options'] ?? [],
                    ];
                },
                $items
            )
        );
    }

    /**
     * Fetch product metadata for cart rows.
     *
     * @param array<int> $ids
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchProducts(array $ids): array
    {
        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('title'),
                $this->db->quoteName('images'),
            ])
            ->from($this->db->quoteName('#__nxp_easycart_products'))
            ->where($this->db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $ids)) . ')');

        $this->db->setQuery($query);
        $rows = $this->db->loadObjectList() ?: [];

        $products = [];

        foreach ($rows as $row) {
            $image = null;

            if (!empty($row->images)) {
                $decoded = json_decode((string) $row->images, true);

                if (json_last_error() === JSON_ERROR_NONE && \is_array($decoded) && isset($decoded[0])) {
                    $candidate = $decoded[0];

                    if (\is_string($candidate) && trim($candidate) !== '') {
                        $image = trim($candidate);
                    }
                }
            }

            $products[(int) $row->id] = [
                'id' => (int) $row->id,
                'title' => (string) $row->title,
                'image' => $image,
            ];
        }

        return $products;
    }

    /**
     * Fetch variant metadata for cart rows.
     *
     * @param array<int> $ids
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchVariants(array $ids): array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__nxp_easycart_variants'))
            ->where($this->db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $ids)) . ')');

        $this->db->setQuery($query);
        $rows = $this->db->loadObjectList() ?: [];

        $variants = [];

        foreach ($rows as $row) {
            $options = [];

            if (!empty($row->options)) {
                $decoded = json_decode($row->options, true);

                if (json_last_error() === JSON_ERROR_NONE && \is_array($decoded)) {
                    $options = array_filter($decoded, static fn ($option) => \is_array($option));
                }
            }

            $variants[(int) $row->id] = [
                'id' => (int) $row->id,
                'product_id' => (int) $row->product_id,
                'title' => (string) $row->sku,
                'sku' => (string) $row->sku,
                'price_cents' => (int) $row->price_cents,
                'currency' => strtoupper((string) $row->currency),
                'options' => $options,
                'image' => null,
            ];
        }

        return $variants;
    }

    /**
     * Compute subtotal and totals for the cart.
     *
     * @param array<int, array<string, mixed>> $items
     */
    private function buildSummary(array $items): array
    {
        $currency = ConfigHelper::getBaseCurrency();
        $subtotal = 0;

        foreach ($items as $item) {
            $subtotal += (int) ($item['total_cents'] ?? 0);

            if (!empty($item['currency'])) {
                $currency = strtoupper((string) $item['currency']);
            }
        }

        return [
            'subtotal_cents' => $subtotal,
            'tax_cents' => 0,
            'shipping_cents' => 0,
            'discount_cents' => 0,
            'total_cents' => $subtotal,
            'currency' => $currency,
        ];
    }
}
