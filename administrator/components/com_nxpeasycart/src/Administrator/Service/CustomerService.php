<?php

namespace Nxp\EasyCart\Admin\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use RuntimeException;

/**
 * Aggregates customer data from orders.
 */
class CustomerService
{
    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Paginate customers derived from orders.
     *
     * @return array{items: array<int, array<string, mixed>>, pagination: array<string, int>}
     */
    public function paginate(array $filters = [], int $limit = 20, int $start = 0): array
    {
        $limit  = $limit > 0 ? $limit : 20;
        $start  = $start >= 0 ? $start : 0;
        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';

        $query = $this->db->getQuery(true)
            ->select([
                'MIN(id) AS id',
                'email',
                'COUNT(*) AS orders_count',
                'SUM(total_cents) AS total_spent_cents',
                'MAX(created) AS last_order',
                'MAX(currency) AS currency',
            ])
            ->from($this->db->quoteName('#__nxp_easycart_orders'))
            ->where($this->db->quoteName('email') . " != ''")
            ->group($this->db->quoteName('email'))
            ->order($this->db->quoteName('last_order') . ' DESC');

        if ($search !== '') {
            $param = '%' . $search . '%';
            $query->having($this->db->quoteName('email') . ' LIKE :search')
                ->bind(':search', $param, ParameterType::STRING);
        }

        $countQuery = clone $query;
        $countQuery->clear('order')
            ->clear('select')
            ->select('COUNT(*)');

        $this->db->setQuery($countQuery);
        $total = (int) $this->db->loadResult();

        $query->setLimit($limit, $start);
        $this->db->setQuery($query);
        $rows = $this->db->loadObjectList() ?: [];

        $items = array_map(fn ($row) => $this->mapCustomerRow($row), $rows);

        $pages   = $limit > 0 ? (int) ceil($total / $limit) : 1;
        $current = $limit > 0 ? (int) floor($start / $limit) + 1 : 1;

        return [
            'items'      => $items,
            'pagination' => [
                'total'   => $total,
                'limit'   => $limit,
                'pages'   => max(1, $pages),
                'current' => max(1, $current),
                'start'   => $start,
            ],
        ];
    }

    /**
     * Retrieve a single customer by email.
     */
    public function get(string $email): ?array
    {
        $email = trim($email);

        if ($email === '') {
            return null;
        }

        $query = $this->db->getQuery(true)
            ->select([
                'MIN(id) AS id',
                'email',
                'COUNT(*) AS orders_count',
                'SUM(total_cents) AS total_spent_cents',
                'MAX(created) AS last_order',
                'MAX(currency) AS currency',
            ])
            ->from($this->db->quoteName('#__nxp_easycart_orders'))
            ->where($this->db->quoteName('email') . ' = :email')
            ->group($this->db->quoteName('email'))
            ->bind(':email', $email, ParameterType::STRING);

        $this->db->setQuery($query);
        $row = $this->db->loadObject();

        if (!$row) {
            return null;
        }

        $customer           = $this->mapCustomerRow($row, true);
        $customer['orders'] = $this->getOrdersForEmail($email);

        return $customer;
    }

    private function mapCustomerRow(object $row, bool $withAddress = false): array
    {
        $email = (string) $row->email;

        $profile = [
            'id'                => (int) ($row->id ?? 0),
            'email'             => $email,
            'orders_count'      => (int) $row->orders_count,
            'total_spent_cents' => (int) $row->total_spent_cents,
            'currency'          => (string) $row->currency,
            'last_order'        => (string) $row->last_order,
            'meta'              => $this->getProfileMeta($email, $withAddress),
        ];

        return $profile;
    }

    /**
     * Fetch representative billing/shipping data for the customer.
     */
    private function getProfileMeta(string $email, bool $includeShipping = false): array
    {
        $query = $this->db->getQuery(true)
            ->select(['billing', 'shipping'])
            ->from($this->db->quoteName('#__nxp_easycart_orders'))
            ->where($this->db->quoteName('email') . ' = :email')
            ->order($this->db->quoteName('created') . ' DESC')
            ->setLimit(1)
            ->bind(':email', $email, ParameterType::STRING);

        $this->db->setQuery($query);
        $row = $this->db->loadObject();

        if (!$row) {
            return [
                'name'     => '',
                'billing'  => [],
                'shipping' => [],
            ];
        }

        $billing  = $this->decodeJson($row->billing ?? '{}');
        $shipping = $includeShipping && $row->shipping !== null ? $this->decodeJson($row->shipping) : [];
        $name     = trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? ''));

        return [
            'name'     => $name,
            'billing'  => $billing,
            'shipping' => $shipping,
        ];
    }

    private function getOrdersForEmail(string $email): array
    {
        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('order_no'),
                $this->db->quoteName('total_cents'),
                $this->db->quoteName('currency'),
                $this->db->quoteName('state'),
                $this->db->quoteName('created'),
            ])
            ->from($this->db->quoteName('#__nxp_easycart_orders'))
            ->where($this->db->quoteName('email') . ' = :email')
            ->order($this->db->quoteName('created') . ' DESC')
            ->bind(':email', $email, ParameterType::STRING);

        $this->db->setQuery($query);

        $rows = $this->db->loadObjectList() ?: [];

        return array_map(static function ($row) {
            return [
                'id'          => (int) $row->id,
                'order_no'    => (string) $row->order_no,
                'total_cents' => (int) $row->total_cents,
                'currency'    => (string) $row->currency,
                'state'       => (string) $row->state,
                'created'     => (string) $row->created,
            ];
        }, $rows);
    }

    private function decodeJson(?string $payload): array
    {
        if ($payload === null || $payload === '') {
            return [];
        }

        $decoded = json_decode($payload, true);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
    }
}
