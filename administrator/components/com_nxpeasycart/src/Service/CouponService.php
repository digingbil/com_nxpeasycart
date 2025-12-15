<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

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
     * @param bool $hasSaleItems Whether the cart contains items currently on sale
     * @param int|null $userId Logged-in user ID (null for guests)
     * @param string|null $guestEmail Guest email for tracking (used when userId is null)
     * @return array{valid: bool, coupon: ?array, error: ?string, discount_cents: int}
     *
     * @since 0.1.5
     */
    public function validate(
        string $code,
        int $subtotalCents,
        bool $hasSaleItems = false,
        ?int $userId = null,
        ?string $guestEmail = null
    ): array {
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

        // Check global usage limits
        if ($coupon['max_uses'] !== null && $coupon['times_used'] >= $coupon['max_uses']) {
            return [
                'valid'          => false,
                'coupon'         => $coupon,
                'error'          => Text::_('COM_NXPEASYCART_ERROR_COUPON_MAX_USES_REACHED'),
                'discount_cents' => 0,
            ];
        }

        // Check per-user usage limit
        if ($coupon['max_uses_per_user'] !== null) {
            // Require login for coupons with per-user limits to prevent abuse via multiple emails
            if ($userId === null || $userId <= 0) {
                return [
                    'valid'          => false,
                    'coupon'         => $coupon,
                    'error'          => Text::_('COM_NXPEASYCART_ERROR_COUPON_LOGIN_REQUIRED'),
                    'discount_cents' => 0,
                ];
            }

            $userUsageCount = $this->getUserUsageCount(
                (int) $coupon['id'],
                $userId,
                $guestEmail
            );

            if ($userUsageCount >= $coupon['max_uses_per_user']) {
                return [
                    'valid'          => false,
                    'coupon'         => $coupon,
                    'error'          => Text::_('COM_NXPEASYCART_ERROR_COUPON_USER_LIMIT_REACHED'),
                    'discount_cents' => 0,
                ];
            }
        }

        // Check if coupon allows sale items
        if ($hasSaleItems && !$coupon['allow_sale_items']) {
            return [
                'valid'          => false,
                'coupon'         => $coupon,
                'error'          => Text::_('COM_NXPEASYCART_ERROR_COUPON_NO_SALE_ITEMS'),
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
     * Count how many times a user has used a specific coupon.
     *
     * @param int $couponId Coupon identifier
     * @param int|null $userId Logged-in user ID (null for guests)
     * @param string|null $email Guest email for tracking
     * @return int Number of times the user/email has used this coupon
     *
     * @since 0.2.1
     */
    public function getUserUsageCount(int $couponId, ?int $userId, ?string $email = null): int
    {
        if ($couponId <= 0) {
            return 0;
        }

        // For logged-in users, count by user_id
        if ($userId !== null && $userId > 0) {
            $query = $this->db->getQuery(true)
                ->select('COUNT(*)')
                ->from($this->db->quoteName('#__nxp_easycart_coupon_usage'))
                ->where($this->db->quoteName('coupon_id') . ' = :couponId')
                ->where($this->db->quoteName('user_id') . ' = :userId')
                ->bind(':couponId', $couponId, ParameterType::INTEGER)
                ->bind(':userId', $userId, ParameterType::INTEGER);

            $this->db->setQuery($query);

            return (int) $this->db->loadResult();
        }

        // For guests, count by normalised email
        $email = $email !== null ? strtolower(trim($email)) : '';

        if ($email === '') {
            return 0;
        }

        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__nxp_easycart_coupon_usage'))
            ->where($this->db->quoteName('coupon_id') . ' = :couponId')
            ->where($this->db->quoteName('user_id') . ' IS NULL')
            ->where($this->db->quoteName('guest_email') . ' = :email')
            ->bind(':couponId', $couponId, ParameterType::INTEGER)
            ->bind(':email', $email, ParameterType::STRING);

        $this->db->setQuery($query);

        return (int) $this->db->loadResult();
    }

    /**
     * Record coupon usage after successful order.
     *
     * @param int $couponId Coupon identifier
     * @param int $orderId Order identifier
     * @param int|null $userId Logged-in user ID (null for guests)
     * @param string|null $guestEmail Guest email address
     *
     * @since 0.2.1
     */
    public function recordUsage(int $couponId, int $orderId, ?int $userId, ?string $guestEmail = null): void
    {
        if ($couponId <= 0 || $orderId <= 0) {
            return;
        }

        $record = (object) [
            'coupon_id'   => $couponId,
            'order_id'    => $orderId,
            'user_id'     => $userId !== null && $userId > 0 ? $userId : null,
            'guest_email' => $userId === null || $userId <= 0
                ? ($guestEmail !== null ? strtolower(trim($guestEmail)) : null)
                : null,
            'created'     => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];

        $this->db->insertObject('#__nxp_easycart_coupon_usage', $record);
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
        $maxUses  = isset($data['max_uses']) && $data['max_uses'] !== null && $data['max_uses'] !== ''
            ? (int) $data['max_uses']
            : null;

        if ($maxUses !== null && $maxUses < 0) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_COUPON_MAX_USES_INVALID'), 400);
        }

        $maxUsesPerUser = isset($data['max_uses_per_user']) && $data['max_uses_per_user'] !== null && $data['max_uses_per_user'] !== ''
            ? (int) $data['max_uses_per_user']
            : null;

        if ($maxUsesPerUser !== null && $maxUsesPerUser < 0) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_COUPON_MAX_USES_PER_USER_INVALID'), 400);
        }

        $active         = isset($data['active']) ? (bool) $data['active'] : true;
        $allowSaleItems = isset($data['allow_sale_items']) ? (bool) $data['allow_sale_items'] : true;

        $start = $this->normaliseDate($data['start'] ?? null);
        $end   = $this->normaliseDate($data['end'] ?? null);

        return [
            'code'              => $code,
            'type'              => $type,
            'value'             => $value,
            'min_total_cents'   => (int) round($minTotal * 100),
            'start'             => $start,
            'end'               => $end,
            'max_uses'          => $maxUses,
            'max_uses_per_user' => $maxUsesPerUser,
            'allow_sale_items'  => $allowSaleItems ? 1 : 0,
            'active'            => $active ? 1 : 0,
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
            'id'                => (int) $row->id,
            'code'              => (string) $row->code,
            'type'              => (string) $row->type,
            'value'             => (float) $row->value,
            'min_total_cents'   => (int) ($row->min_total_cents ?? 0),
            'min_total'         => ((int) ($row->min_total_cents ?? 0)) / 100,
            'start'             => $row->start ? (string) $row->start : null,
            'end'               => $row->end ? (string) $row->end : null,
            'max_uses'          => $row->max_uses !== null ? (int) $row->max_uses : null,
            'max_uses_per_user' => isset($row->max_uses_per_user) && $row->max_uses_per_user !== null
                ? (int) $row->max_uses_per_user
                : null,
            'times_used'        => (int) ($row->times_used ?? 0),
            'allow_sale_items'  => (bool) ($row->allow_sale_items ?? true),
            'active'            => (bool) $row->active,
        ];
    }
}
