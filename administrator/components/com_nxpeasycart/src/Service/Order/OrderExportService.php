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

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Order export service.
 *
 * Handles CSV export of orders with filtering support.
 *
 * @since 0.3.2
 */
class OrderExportService
{
    public const ORDER_STATES = ['cart', 'pending', 'paid', 'fulfilled', 'refunded', 'canceled'];

    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Export orders to CSV format.
     *
     * @param array    $filters       Filter options (state, search, date_from, date_to)
     * @param callable $getOrderItems Callback to fetch order items map: fn(array $orderIds): array
     *
     * @return array{filename: string, content: string}
     *
     * @since 0.1.5
     */
    public function exportToCsv(array $filters, callable $getOrderItems): array
    {
        $query = $this->db->getQuery(true)
            ->select('o.*')
            ->from($this->db->quoteName('#__nxp_easycart_orders', 'o'))
            ->order($this->db->quoteName('o.created') . ' DESC');

        $state = isset($filters['state']) ? strtolower(trim((string) $filters['state'])) : '';

        if ($state !== '' && \in_array($state, self::ORDER_STATES, true)) {
            $query->where($this->db->quoteName('o.state') . ' = :stateFilter');
            $query->bind(':stateFilter', $state, ParameterType::STRING);
        }

        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';

        if ($search !== '') {
            $searchParam = '%' . $search . '%';
            $query->where(
                '(' . $this->db->quoteName('o.order_no') . ' LIKE :search '
                . 'OR ' . $this->db->quoteName('o.email') . ' LIKE :search)'
            );
            $query->bind(':search', $searchParam, ParameterType::STRING);
        }

        $dateFrom = isset($filters['date_from']) ? trim((string) $filters['date_from']) : '';
        $dateTo   = isset($filters['date_to']) ? trim((string) $filters['date_to']) : '';

        if ($dateFrom !== '') {
            $query->where($this->db->quoteName('o.created') . ' >= :dateFrom');
            $query->bind(':dateFrom', $dateFrom, ParameterType::STRING);
        }

        if ($dateTo !== '') {
            $dateToEnd = $dateTo . ' 23:59:59';
            $query->where($this->db->quoteName('o.created') . ' <= :dateTo');
            $query->bind(':dateTo', $dateToEnd, ParameterType::STRING);
        }

        $this->db->setQuery($query);
        $rows = $this->db->loadObjectList() ?: [];

        // Fetch all order items in one query
        $orderIds = array_map(static fn ($row) => (int) $row->id, $rows);
        $itemsMap = !empty($orderIds) ? $getOrderItems($orderIds) : [];

        // Build CSV with BOM for Excel UTF-8 compatibility
        $output = chr(0xEF) . chr(0xBB) . chr(0xBF);

        // CSV header row
        $headers = [
            'Order Number',
            'Date',
            'Status',
            'Customer Email',
            'Billing Name',
            'Billing Address',
            'Billing City',
            'Billing Postcode',
            'Billing Country',
            'Shipping Name',
            'Shipping Address',
            'Shipping City',
            'Shipping Postcode',
            'Shipping Country',
            'Items',
            'Subtotal',
            'Tax',
            'Shipping Cost',
            'Discount',
            'Total',
            'Currency',
            'Carrier',
            'Tracking Number',
        ];

        $output .= $this->csvLine($headers);

        foreach ($rows as $row) {
            $billing  = $this->decodeJson($row->billing ?? '{}');
            $shipping = $row->shipping !== null ? $this->decodeJson($row->shipping) : [];
            $items    = $itemsMap[(int) $row->id] ?? [];

            // Format items as "SKU x Qty, SKU x Qty"
            $itemsSummary = implode(', ', array_map(
                static fn ($item) => ($item['sku'] ?? 'N/A') . ' x ' . ($item['qty'] ?? 1),
                $items
            ));

            $line = [
                $row->order_no,
                $row->created,
                ucfirst($row->state),
                $row->email,
                trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? '')),
                $billing['address'] ?? '',
                $billing['city'] ?? '',
                $billing['postcode'] ?? '',
                $billing['country'] ?? '',
                $shipping ? trim(($shipping['first_name'] ?? '') . ' ' . ($shipping['last_name'] ?? '')) : '',
                $shipping['address'] ?? '',
                $shipping['city'] ?? '',
                $shipping['postcode'] ?? '',
                $shipping['country'] ?? '',
                $itemsSummary,
                number_format((int) $row->subtotal_cents / 100, 2, '.', ''),
                number_format((int) $row->tax_cents / 100, 2, '.', ''),
                number_format((int) $row->shipping_cents / 100, 2, '.', ''),
                number_format((int) $row->discount_cents / 100, 2, '.', ''),
                number_format((int) $row->total_cents / 100, 2, '.', ''),
                $row->currency,
                $row->carrier ?? '',
                $row->tracking_number ?? '',
            ];

            $output .= $this->csvLine($line);
        }

        $filename = 'orders-export-' . date('Y-m-d-His') . '.csv';

        return [
            'filename' => $filename,
            'content'  => $output,
        ];
    }

    /**
     * Encode a row as CSV line with proper escaping.
     *
     * @param array $fields
     * @return string
     *
     * @since 0.1.5
     */
    private function csvLine(array $fields): string
    {
        $escaped = array_map(static function ($value) {
            $value = (string) $value;
            // Escape double quotes and wrap in quotes if contains comma, quote, or newline
            if (strpos($value, '"') !== false || strpos($value, ',') !== false || strpos($value, "\n") !== false) {
                return '"' . str_replace('"', '""', $value) . '"';
            }
            return $value;
        }, $fields);

        return implode(',', $escaped) . "\r\n";
    }

    /**
     * Decode JSON safely.
     *
     * @param string|null $json
     * @return array
     */
    private function decodeJson(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return \is_array($decoded) ? $decoded : [];
    }
}
