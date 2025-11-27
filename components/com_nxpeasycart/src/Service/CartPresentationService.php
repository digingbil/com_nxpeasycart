<?php

namespace Joomla\Component\Nxpeasycart\Site\Service;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;
use Joomla\Component\Nxpeasycart\Administrator\Helper\ConfigHelper;
use Joomla\Component\Nxpeasycart\Administrator\Service\TaxService;
use Joomla\CMS\Uri\Uri;

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
     * Cached default tax rate.
     *
     * @var array<string, mixed>|null
     */
    private ?array $defaultTaxRate = null;

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
        $items    = $cart['data']['items'] ?? [];
        $coupon   = $cart['data']['coupon'] ?? null;
        $hydrated = $this->hydrateItems(\is_array($items) ? $items : []);

        $cart['items']   = $hydrated;
        $cart['coupon']  = $coupon;
        $cart['summary'] = $this->buildSummary($hydrated, $coupon);

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
                function ($item) use ($products, $variants, $baseCurrency) {
                    $productId = isset($item['product_id']) ? (int) $item['product_id'] : null;
                    $variantId = isset($item['variant_id']) ? (int) $item['variant_id'] : null;
                    $qty       = isset($item['qty']) ? max(1, (int) $item['qty']) : 1;

                    $product = $productId !== null && isset($products[$productId]) ? $products[$productId] : null;
                    $variant = $variantId !== null && isset($variants[$variantId]) ? $variants[$variantId] : null;

                    // SECURITY: Always use database price, never trust cart-stored prices
                    $priceCents = ($variant['price_cents'] ?? 0);

                    // SECURITY: Always use database currency
                    $currency = ($variant['currency'] ?? $baseCurrency);

                    $variantLabel = $this->buildVariantLabel($variant);
                    $baseTitle    = $product['title'] ?? ($item['title'] ?? '');

                    $displayTitle = $baseTitle !== ''
                        ? (string) $baseTitle
                        : ($item['title'] ?? ($variant['sku'] ?? ''));

                    if ($variantLabel !== '' && $baseTitle !== '') {
                        $displayTitle = $baseTitle . ' (' . $variantLabel . ')';
                    }

                    return [
                        'id'               => $variantId ?? $productId ?? spl_object_id((object) $item),
                        'product_id'       => $productId,
                        'variant_id'       => $variantId,
                        'title'            => $displayTitle,
                        'product_title'    => $product['title'] ?? $displayTitle,
                        'variant_label'    => $variantLabel !== '' ? $variantLabel : null,
                        'qty'              => $qty,
                        'unit_price_cents' => $priceCents,
                        'currency'         => $currency,
                        'total_cents'      => $priceCents * $qty,
                        'sku'              => $variant['sku']     ?? ($item['sku'] ?? null),
                        'image'            => $variant['image']   ?? ($product['image'] ?? null),
                        'options'          => $variant['options'] ?? [],
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

                        if (
                            !str_starts_with($image, 'http://')
                            && !str_starts_with($image, 'https://')
                            && !str_starts_with($image, '//')
                        ) {
                            $base     = rtrim(Uri::root(true), '/');
                            $relative = '/' . ltrim($image, '/');

                            $image = ($base === '' ? '' : $base) . $relative;
                        }
                    }
                }
            }

            $products[(int) $row->id] = [
                'id'    => (int) $row->id,
                'title' => (string) $row->title,
                'image' => $image,
            ];
        }

        return $products;
    }

    /**
     * Build a human-readable variant label from options/sku.
     */
    private function buildVariantLabel(?array $variant): string
    {
        if (!$variant) {
            return '';
        }

        $labels = [];

        if (!empty($variant['options']) && \is_array($variant['options'])) {
            foreach ($variant['options'] as $option) {
                if (!\is_array($option)) {
                    continue;
                }

                $name  = isset($option['name']) ? trim((string) $option['name']) : '';
                $value = isset($option['value']) ? trim((string) $option['value']) : '';

                if ($name !== '' && $value !== '') {
                    $labels[] = $name . ': ' . $value;
                } elseif ($value !== '') {
                    $labels[] = $value;
                }
            }
        }

        if (!$labels && !empty($variant['sku'])) {
            return (string) $variant['sku'];
        }

        return implode(', ', $labels);
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
                'id'          => (int) $row->id,
                'product_id'  => (int) $row->product_id,
                'title'       => (string) $row->sku,
                'sku'         => (string) $row->sku,
                'price_cents' => (int) $row->price_cents,
                'currency'    => strtoupper((string) $row->currency),
                'options'     => $options,
                'image'       => null,
            ];
        }

        return $variants;
    }

    /**
     * Compute subtotal and totals for the cart.
     *
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed>|null $coupon
     */
    private function buildSummary(array $items, ?array $coupon = null): array
    {
        $currency = ConfigHelper::getBaseCurrency();
        $subtotal = 0;

        foreach ($items as $item) {
            $subtotal += (int) ($item['total_cents'] ?? 0);

            if (!empty($item['currency'])) {
                $currency = strtoupper((string) $item['currency']);
            }
        }

        // Apply coupon discount
        $discountCents = 0;
        if ($coupon && isset($coupon['discount_cents'])) {
            $discountCents = (int) $coupon['discount_cents'];
        }

        // Calculate tax on subtotal after discount
        $taxableAmount = max(0, $subtotal - $discountCents);
        $tax      = $this->calculateTax($taxableAmount);
        $taxValue = $tax['amount'];
        $inclusive = $tax['inclusive'];
        $total    = $taxableAmount + ($inclusive ? 0 : $taxValue);

        return [
            'subtotal_cents' => $subtotal,
            'tax_cents'      => $taxValue,
            'tax_rate'       => $tax['rate'],
            'tax_inclusive'  => $inclusive,
            'shipping_cents' => 0,
            'discount_cents' => $discountCents,
            'total_cents'    => $total,
            'currency'       => $currency,
        ];
    }

    /**
     * Resolve default tax rate from configuration.
     */
    private function defaultTaxRate(): ?array
    {
        if ($this->defaultTaxRate !== null) {
            return $this->defaultTaxRate;
        }

        try {
            $service = new TaxService($this->db);
            $rates   = $service->paginate([], 50, 0);
            $items   = $rates['items'] ?? [];
            $global  = array_values(array_filter(
                $items,
                static function ($rate) {
                    $country = strtoupper((string) ($rate['country'] ?? ''));
                    $region  = strtolower((string) ($rate['region'] ?? ''));

                    return $country === '' && $region === '';
                }
            ));

            $this->defaultTaxRate = $global[0] ?? null;
        } catch (\Throwable $exception) {
            $this->defaultTaxRate = null;
        }

        return $this->defaultTaxRate;
    }

    /**
     * Calculate tax from the default rate.
     *
     * @return array{amount: int, rate: float, inclusive: bool}
     */
    private function calculateTax(int $subtotal): array
    {
        $rate = $this->defaultTaxRate();

        if (!$rate || empty($rate['rate'])) {
            return [
                'amount'    => 0,
                'rate'      => 0.0,
                'inclusive' => false,
            ];
        }

        $percentage = (float) $rate['rate'];
        $inclusive  = !empty($rate['inclusive']);

        $tax = $inclusive
            ? (int) round($subtotal - ($subtotal / (1 + ($percentage / 100))))
            : (int) round($subtotal * ($percentage / 100));

        return [
            'amount'    => $tax,
            'rate'      => $percentage,
            'inclusive' => $inclusive,
        ];
    }
}
