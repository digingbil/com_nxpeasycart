<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Service;

\defined('_JEXEC') or die;

use DateTimeImmutable;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use RuntimeException;

/**
 * Coupon management service.
 *
 * @since 0.1.5
 */
class CouponService
{
    private const TYPES = ['percent', 'fixed'];

    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Paginate coupons with optional search.
     *
     * @return array{items: array<int, array<string, mixed>>, pagination: array<string, int>}
     *
     * @since 0.1.5
     */
    public function paginate(array $filters = [], int $limit = 20, int $start = 0): array
    {
        $limit  = $limit > 0 ? $limit : 20;
        $start  = $start >= 0 ? $start : 0;
        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__nxp_easycart_coupons'))
            ->order($this->db->quoteName('id') . ' DESC');

        if ($search !== '') {
            $param = '%' . $search . '%';
            $query->where($this->db->quoteName('code') . ' LIKE :search')
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

        $items = array_map([$this, 'mapRow'], $rows);

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

    public function create(array $data): array
    {
        $payload = $this->normalisePayload($data);

        $coupon = (object) $payload;
        $this->db->insertObject('#__nxp_easycart_coupons', $coupon);

        $id = (int) $this->db->insertid();

        return $this->get($id) ?? [];
    }

    public function update(int $id, array $data): array
    {
        if ($id <= 0) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_COUPON_NOT_FOUND'), 404);
        }

        $payload = $this->normalisePayload($data, $id);

        $coupon = (object) array_merge(['id' => $id], $payload);
        $this->db->updateObject('#__nxp_easycart_coupons', $coupon, 'id');

        return $this->get($id) ?? [];
    }

    public function delete(array $ids): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));

        if (!$ids) {
            return 0;
        }

        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__nxp_easycart_coupons'))
            ->whereIn($this->db->quoteName('id'), $ids);

        $this->db->setQuery($query);
        $this->db->execute();

        return $this->db->getAffectedRows();
    }

    public function get(int $id): ?array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__nxp_easycart_coupons'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $row = $this->db->loadObject();

        return $row ? $this->mapRow($row) : null;
    }

    /**
     * Find a coupon by code.
     *
     * @since 0.1.5
     */
    public function getByCode(string $code): ?array
    {
        $code = strtoupper(trim($code));

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__nxp_easycart_coupons'))
            ->where($this->db->quoteName('code') . ' = :code')
            ->bind(':code', $code, ParameterType::STRING);

        $this->db->setQuery($query);
        $row = $this->db->loadObject();

        return $row ? $this->mapRow($row) : null;
    }

    /**
     * Validate a coupon for a given order total.
     *
     * @param string $code Coupon code
     * @param int $subtotalCents Order subtotal in cents
     * @return array{valid: bool, coupon: ?array, error: ?string, discount_cents: int}
     *
     * @since 0.1.5
     */
    public function validate(string $code, int $subtotalCents): array
    {
        $coupon = $this->getByCode($code);

        if (!$coupon) {
            return [
                'valid'          => false,
                'coupon'         => null,
                'error'          => Text::_('COM_NXPEASYCART_ERROR_COUPON_NOT_FOUND'),
                'discount_cents' => 0,
            ];
        }

        // Check if active
        if (!$coupon['active']) {
            return [
                'valid'          => false,
                'coupon'         => $coupon,
                'error'          => Text::_('COM_NXPEASYCART_ERROR_COUPON_INACTIVE'),
                'discount_cents' => 0,
            ];
        }

        // Check start date
        if ($coupon['start']) {
            $start = new DateTimeImmutable($coupon['start']);
            $now   = new DateTimeImmutable();

            if ($now < $start) {
                return [
                    'valid'          => false,
                    'coupon'         => $coupon,
                    'error'          => Text::_('COM_NXPEASYCART_ERROR_COUPON_NOT_STARTED'),
                    'discount_cents' => 0,
                ];
            }
        }

        // Check end date
        if ($coupon['end']) {
            $end = new DateTimeImmutable($coupon['end']);
            $now = new DateTimeImmutable();

            if ($now > $end) {
                return [
                    'valid'          => false,
                    'coupon'         => $coupon,
                    'error'          => Text::_('COM_NXPEASYCART_ERROR_COUPON_EXPIRED'),
                    'discount_cents' => 0,
                ];
            }
        }

        // Check usage limits
        if ($coupon['max_uses'] !== null && $coupon['times_used'] >= $coupon['max_uses']) {
            return [
                'valid'          => false,
                'coupon'         => $coupon,
                'error'          => Text::_('COM_NXPEASYCART_ERROR_COUPON_MAX_USES_REACHED'),
                'discount_cents' => 0,
            ];
        }

        // Check minimum order total
        if ($subtotalCents < $coupon['min_total_cents']) {
            return [
                'valid'          => false,
                'coupon'         => $coupon,
                'error'          => Text::sprintf(
                    'COM_NXPEASYCART_ERROR_COUPON_MIN_TOTAL',
                    number_format($coupon['min_total'], 2)
                ),
                'discount_cents' => 0,
            ];
        }

        // Calculate discount
        $discountCents = $this->calculateDiscount($coupon, $subtotalCents);

        return [
            'valid'          => true,
            'coupon'         => $coupon,
            'error'          => null,
            'discount_cents' => $discountCents,
        ];
    }

    /**
     * Calculate discount amount in cents.
     *
     * @since 0.1.5
     */
    private function calculateDiscount(array $coupon, int $subtotalCents): int
    {
        if ($coupon['type'] === 'percent') {
            $discount = (int) round(($subtotalCents * $coupon['value']) / 100);
        } else {
            // Fixed amount (convert to cents)
            $discount = (int) round($coupon['value'] * 100);
        }

        // Discount cannot exceed subtotal
        return min($discount, $subtotalCents);
    }

    /**
     * Increment usage counter for a coupon.
     *
     * @since 0.1.5
     */
    public function incrementUsage(int $couponId): void
    {
        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__nxp_easycart_coupons'))
            ->set($this->db->quoteName('times_used') . ' = ' . $this->db->quoteName('times_used') . ' + 1')
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $couponId, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $this->db->execute();
    }

    private function normalisePayload(array $data, ?int $ignoreId = null): array
    {
        $code = strtoupper(trim((string) ($data['code'] ?? '')));

        if ($code === '') {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_COUPON_CODE_REQUIRED'), 400);
        }

        if ($this->codeExists($code, $ignoreId)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_COUPON_CODE_EXISTS'), 400);
        }

        $type = strtolower((string) ($data['type'] ?? 'percent'));

        if (!in_array($type, self::TYPES, true)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_COUPON_TYPE_INVALID'), 400);
        }

        $value = (float) ($data['value'] ?? 0);

        if ($value <= 0) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_COUPON_VALUE_INVALID'), 400);
        }

        if ($type === 'percent' && $value > 100) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_COUPON_PERCENT_INVALID'), 400);
        }

        $minTotal = (float) ($data['min_total'] ?? 0);
        $maxUses  = $data['max_uses'] !== null ? (int) $data['max_uses'] : null;

        if ($maxUses !== null && $maxUses < 0) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_COUPON_MAX_USES_INVALID'), 400);
        }

        $active = isset($data['active']) ? (bool) $data['active'] : true;

        $start = $this->normaliseDate($data['start'] ?? null);
        $end   = $this->normaliseDate($data['end'] ?? null);

        return [
            'code'            => $code,
            'type'            => $type,
            'value'           => $value,
            'min_total_cents' => (int) round($minTotal * 100),
            'start'           => $start,
            'end'             => $end,
            'max_uses'        => $maxUses,
            'active'          => $active ? 1 : 0,
        ];
    }

    private function codeExists(string $code, ?int $ignoreId = null): bool
    {
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__nxp_easycart_coupons'))
            ->where($this->db->quoteName('code') . ' = :code')
            ->bind(':code', $code, ParameterType::STRING);

        if ($ignoreId) {
            $query->where($this->db->quoteName('id') . ' != :ignoreId')
                ->bind(':ignoreId', $ignoreId, ParameterType::INTEGER);
        }

        $this->db->setQuery($query);

        return (int) $this->db->loadResult() > 0;
    }

    private function normaliseDate($value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            $date = new DateTimeImmutable(is_numeric($value) ? '@' . $value : (string) $value);
        } catch (\Exception $exception) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_COUPON_DATE_INVALID'), 400, $exception);
        }

        return $date->format('Y-m-d H:i:s');
    }

    private function mapRow(object $row): array
    {
        return [
            'id'              => (int) $row->id,
            'code'            => (string) $row->code,
            'type'            => (string) $row->type,
            'value'           => (float) $row->value,
            'min_total_cents' => (int) ($row->min_total_cents ?? 0),
            'min_total'       => ((int) ($row->min_total_cents ?? 0)) / 100,
            'start'           => $row->start ? (string) $row->start : null,
            'end'             => $row->end ? (string) $row->end : null,
            'max_uses'        => $row->max_uses !== null ? (int) $row->max_uses : null,
            'times_used'      => (int) ($row->times_used ?? 0),
            'active'          => (bool) $row->active,
        ];
    }
}
