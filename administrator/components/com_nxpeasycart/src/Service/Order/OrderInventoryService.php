<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Administrator\Service\Order;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Component\Nxpeasycart\Administrator\Helper\ProductStatus;
use RuntimeException;

/**
 * Order inventory service.
 *
 * Handles stock reservation, decrement, release, and auto-disabling of depleted products.
 *
 * @since 0.3.2
 */
class OrderInventoryService
{
    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Reserve stock for order items (atomic decrement with validation).
     *
     * @param array $items Order items with variant_id, product_id, qty, is_digital
     *
     * @throws RuntimeException If insufficient stock
     */
    public function reserveStockForItems(array $items): void
    {
        $variantTotals = [];
        $productIds    = [];

        foreach ($items as $item) {
            if (!empty($item['is_digital'])) {
                continue;
            }

            $variantId = isset($item['variant_id']) ? (int) $item['variant_id'] : 0;
            $productId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
            $qty       = isset($item['qty']) ? max(1, (int) $item['qty']) : 1;

            if ($variantId > 0) {
                $variantTotals[$variantId] = ($variantTotals[$variantId] ?? 0) + $qty;
            }

            if ($productId > 0) {
                $productIds[] = $productId;
            }
        }

        if (empty($variantTotals)) {
            return;
        }

        // Atomic decrements with guard to prevent oversell
        foreach ($variantTotals as $variantId => $requestedQty) {
            $qty        = (int) $requestedQty;
            $variantKey = (int) $variantId;

            $update = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__nxp_easycart_variants'))
                ->set($this->db->quoteName('stock') . ' = ' . $this->db->quoteName('stock') . ' - :qty')
                ->where($this->db->quoteName('id') . ' = :variantId')
                ->where($this->db->quoteName('active') . ' = 1')
                ->where($this->db->quoteName('stock') . ' >= :qty')
                ->bind(':qty', $qty, ParameterType::INTEGER)
                ->bind(':variantId', $variantKey, ParameterType::INTEGER);

            $this->db->setQuery($update);
            $this->db->execute();

            if ((int) $this->db->getAffectedRows() === 0) {
                throw new RuntimeException(Text::_('COM_NXPEASYCART_PRODUCT_OUT_OF_STOCK'));
            }
        }

        $this->autoDisableDepletedProducts(array_unique($productIds));
    }

    /**
     * Decrement inventory for a completed order.
     */
    public function decrementInventory(int $orderId): void
    {
        $affectedProducts = [];

        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('variant_id'),
                $this->db->quoteName('product_id'),
                $this->db->quoteName('qty'),
            ])
            ->from($this->db->quoteName('#__nxp_easycart_order_items'))
            ->where($this->db->quoteName('order_id') . ' = :orderId')
            ->where($this->db->quoteName('variant_id') . ' IS NOT NULL')
            ->where($this->db->quoteName('is_digital') . ' = 0')
            ->bind(':orderId', $orderId, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $rows = $this->db->loadObjectList() ?: [];

        foreach ($rows as $row) {
            $variantId = (int) $row->variant_id;
            $qty       = max(0, (int) $row->qty);

            if ($variantId <= 0 || $qty <= 0) {
                continue;
            }

            $affectedProducts[] = (int) ($row->product_id ?? 0);

            $update = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__nxp_easycart_variants'))
                ->set($this->db->quoteName('stock') . ' = GREATEST(' . $this->db->quoteName('stock') . ' - :qty, 0)')
                ->where($this->db->quoteName('id') . ' = :variantId')
                ->bind(':qty', $qty, ParameterType::INTEGER)
                ->bind(':variantId', $variantId, ParameterType::INTEGER);

            $this->db->setQuery($update);
            $this->db->execute();
        }

        $this->autoDisableDepletedProducts(array_unique(array_filter($affectedProducts)));
    }

    /**
     * Release reserved stock for a canceled order.
     */
    public function releaseStockForOrder(int $orderId): void
    {
        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('variant_id'),
                $this->db->quoteName('qty'),
            ])
            ->from($this->db->quoteName('#__nxp_easycart_order_items'))
            ->where($this->db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $items = $this->db->loadObjectList();

        foreach ($items as $item) {
            if ((int) $item->variant_id <= 0) {
                continue;
            }

            $variantId = (int) $item->variant_id;
            $qty       = (int) $item->qty;

            $update = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__nxp_easycart_variants'))
                ->set($this->db->quoteName('stock') . ' = ' . $this->db->quoteName('stock') . ' + :qty')
                ->where($this->db->quoteName('id') . ' = :variantId')
                ->bind(':qty', $qty, ParameterType::INTEGER)
                ->bind(':variantId', $variantId, ParameterType::INTEGER);

            $this->db->setQuery($update);
            $this->db->execute();
        }
    }

    /**
     * Disable products that have no remaining stock across active variants.
     */
    public function autoDisableDepletedProducts(array $productIds): void
    {
        if (empty($productIds)) {
            return;
        }

        foreach ($productIds as $productId) {
            if ($productId <= 0) {
                continue;
            }

            $stockQuery = $this->db->getQuery(true)
                ->select('SUM(' . $this->db->quoteName('stock') . ')')
                ->from($this->db->quoteName('#__nxp_easycart_variants'))
                ->where($this->db->quoteName('product_id') . ' = :productId')
                ->where($this->db->quoteName('active') . ' = 1')
                ->bind(':productId', $productId, ParameterType::INTEGER);

            $this->db->setQuery($stockQuery);

            $remaining = (int) $this->db->loadResult();

            if ($remaining > 0) {
                continue;
            }

            $disable = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__nxp_easycart_products'))
                ->set($this->db->quoteName('active') . ' = :outOfStock')
                ->where($this->db->quoteName('id') . ' = :productId')
                ->where($this->db->quoteName('active') . ' = :activeStatus')
                ->bind(':productId', $productId, ParameterType::INTEGER);

            $outOfStock   = ProductStatus::OUT_OF_STOCK;
            $activeStatus = ProductStatus::ACTIVE;

            $disable->bind(':outOfStock', $outOfStock, ParameterType::INTEGER);
            $disable->bind(':activeStatus', $activeStatus, ParameterType::INTEGER);

            $this->db->setQuery($disable);
            $this->db->execute();
        }
    }

    /**
     * Load product types for a set of product IDs.
     *
     * @return array<int, string> Keyed by product ID
     */
    public function loadProductTypes(array $productIds): array
    {
        $productIds = array_values(array_unique(array_filter($productIds)));

        if (empty($productIds)) {
            return [];
        }

        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('product_type'),
            ])
            ->from($this->db->quoteName('#__nxp_easycart_products'))
            ->whereIn($this->db->quoteName('id'), $productIds);

        $this->db->setQuery($query);
        $rows = $this->db->loadObjectList() ?: [];

        $types = [];

        foreach ($rows as $row) {
            $types[(int) $row->id] = isset($row->product_type)
                ? strtolower((string) $row->product_type)
                : 'physical';
        }

        return $types;
    }

    /**
     * Load variant digital flags (derived from product_type).
     *
     * @return array<int, bool> Keyed by variant ID
     */
    public function loadVariantDigitalFlags(array $variantIds): array
    {
        $variantIds = array_values(array_unique(array_filter($variantIds)));

        if (empty($variantIds)) {
            return [];
        }

        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('v.id'),
                $this->db->quoteName('p.product_type'),
            ])
            ->from($this->db->quoteName('#__nxp_easycart_variants', 'v'))
            ->join('LEFT', $this->db->quoteName('#__nxp_easycart_products', 'p') . ' ON ' . $this->db->quoteName('v.product_id') . ' = ' . $this->db->quoteName('p.id'))
            ->whereIn($this->db->quoteName('v.id'), $variantIds);

        $this->db->setQuery($query);
        $rows = $this->db->loadObjectList() ?: [];

        $flags = [];

        foreach ($rows as $row) {
            $productType = isset($row->product_type) ? strtolower((string) $row->product_type) : 'physical';
            $flags[(int) $row->id] = $productType === 'digital';
        }

        return $flags;
    }
}
