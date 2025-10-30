<?php

namespace Nxp\EasyCart\Admin\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use RuntimeException;

/**
 * Shipping rule management service.
 */
class ShippingRuleService
{
    private const TYPES = ['flat', 'free_over'];

    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    public function paginate(array $filters = [], int $limit = 20, int $start = 0): array
    {
        $limit = $limit > 0 ? $limit : 20;
        $start = $start >= 0 ? $start : 0;
        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__nxp_easycart_shipping_rules'))
            ->order($this->db->quoteName('name') . ' ASC');

        if ($search !== '') {
            $param = '%' . $search . '%';
            $query->where($this->db->quoteName('name') . ' LIKE :search')
                ->bind(':search', $param, ParameterType::STRING);
        }

        $countQuery = clone $query;
        $countQuery->clear('select')->select('COUNT(*)');
        $this->db->setQuery($countQuery);
        $total = (int) $this->db->loadResult();

        $query->setLimit($limit, $start);
        $this->db->setQuery($query);
        $rows = $this->db->loadObjectList() ?: [];

        $items = array_map([$this, 'mapRow'], $rows);

        $pages = $limit > 0 ? (int) ceil($total / $limit) : 1;
        $current = $limit > 0 ? (int) floor($start / $limit) + 1 : 1;

        return [
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'pages' => max(1, $pages),
                'current' => max(1, $current),
                'start' => $start,
            ],
        ];
    }

    public function create(array $data): array
    {
        $payload = $this->normalise($data);
        $rule = (object) $payload;
        $this->db->insertObject('#__nxp_easycart_shipping_rules', $rule);
        $id = (int) $this->db->insertid();

        return $this->get($id) ?? [];
    }

    public function update(int $id, array $data): array
    {
        if ($id <= 0) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_SHIPPING_RULE_NOT_FOUND'), 404);
        }

        $payload = $this->normalise($data);
        $rule = (object) array_merge(['id' => $id], $payload);
        $this->db->updateObject('#__nxp_easycart_shipping_rules', $rule, 'id');

        return $this->get($id) ?? [];
    }

    public function delete(array $ids): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));

        if (!$ids) {
            return 0;
        }

        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__nxp_easycart_shipping_rules'))
            ->where($this->db->quoteName('id') . ' IN (' . implode(',', $ids) . ')');

        $this->db->setQuery($query);
        $this->db->execute();

        return $this->db->getAffectedRows();
    }

    public function get(int $id): ?array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__nxp_easycart_shipping_rules'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $row = $this->db->loadObject();

        return $row ? $this->mapRow($row) : null;
    }

    private function normalise(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_SHIPPING_RULE_NAME_REQUIRED'), 400);
        }

        $type = strtolower((string) ($data['type'] ?? 'flat'));

        if (!in_array($type, self::TYPES, true)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_SHIPPING_RULE_TYPE_INVALID'), 400);
        }

        $price = (float) ($data['price'] ?? 0);

        if ($price < 0) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_SHIPPING_RULE_PRICE_INVALID'), 400);
        }

        $threshold = (float) ($data['threshold'] ?? 0);

        if ($type === 'free_over' && $threshold <= 0) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_SHIPPING_RULE_THRESHOLD_REQUIRED'), 400);
        }

        $regions = [];

        if (isset($data['regions'])) {
            if (is_string($data['regions'])) {
                $regions = array_map('trim', explode(',', $data['regions']));
            } elseif (is_array($data['regions'])) {
                $regions = array_map(static fn($region) => trim((string) $region), $data['regions']);
            }

            $regions = array_values(array_filter($regions));
        }

        $active = isset($data['active']) ? (bool) $data['active'] : true;

        return [
            'name' => $name,
            'type' => $type,
            'price_cents' => (int) round($price * 100),
            'threshold_cents' => $type === 'free_over' ? (int) round($threshold * 100) : null,
            'regions' => $regions ? json_encode($regions) : null,
            'active' => $active ? 1 : 0,
        ];
    }

    private function mapRow(object $row): array
    {
        $regions = [];

        if ($row->regions) {
            $decoded = json_decode($row->regions, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $regions = array_values(array_filter(array_map('strval', $decoded)));
            }
        }

        return [
            'id' => (int) $row->id,
            'name' => (string) $row->name,
            'type' => (string) $row->type,
            'price_cents' => (int) $row->price_cents,
            'price' => ((int) $row->price_cents) / 100,
            'threshold_cents' => $row->threshold_cents !== null ? (int) $row->threshold_cents : null,
            'threshold' => $row->threshold_cents !== null ? ((int) $row->threshold_cents) / 100 : null,
            'regions' => $regions,
            'active' => (bool) $row->active,
        ];
    }
}
