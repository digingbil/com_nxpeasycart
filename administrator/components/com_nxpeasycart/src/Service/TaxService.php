<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use RuntimeException;

/**
 * Tax rate management service.
 *
 * @since 0.1.5
 */
class TaxService
{
    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    public function paginate(array $filters = [], int $limit = 20, int $start = 0): array
    {
        $limit  = $limit > 0 ? $limit : 20;
        $start  = $start >= 0 ? $start : 0;
        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__nxp_easycart_tax_rates'))
            ->order($this->db->quoteName('priority') . ' ASC');

        if ($search !== '') {
            $param = '%' . $search . '%';
            $query->where(
                '(' . $this->db->quoteName('country') . ' LIKE :search
                  OR ' . $this->db->quoteName('region') . ' LIKE :search)'
            )
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
        $payload = $this->normalise($data);
        $rate    = (object) $payload;
        $this->db->insertObject('#__nxp_easycart_tax_rates', $rate);

        $id = (int) $this->db->insertid();

        return $this->get($id) ?? [];
    }

    public function update(int $id, array $data): array
    {
        if ($id <= 0) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_TAX_RATE_NOT_FOUND'), 404);
        }

        $payload = $this->normalise($data);
        $rate    = (object) array_merge(['id' => $id], $payload);
        $this->db->updateObject('#__nxp_easycart_tax_rates', $rate, 'id');

        return $this->get($id) ?? [];
    }

    public function delete(array $ids): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));

        if (!$ids) {
            return 0;
        }

        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__nxp_easycart_tax_rates'))
            ->where($this->db->quoteName('id') . ' IN (' . implode(',', $ids) . ')');

        $this->db->setQuery($query);
        $this->db->execute();

        return $this->db->getAffectedRows();
    }

    public function get(int $id): ?array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__nxp_easycart_tax_rates'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $row = $this->db->loadObject();

        return $row ? $this->mapRow($row) : null;
    }

    private function normalise(array $data): array
    {
        $country = strtoupper(trim((string) ($data['country'] ?? '')));

        if (strlen($country) !== 2) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_TAX_RATE_COUNTRY'), 400);
        }

        $region = trim((string) ($data['region'] ?? ''));
        $rate   = (float) ($data['rate'] ?? 0);

        if ($rate < 0) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_TAX_RATE_INVALID'), 400);
        }

        $inclusive = isset($data['inclusive']) ? (bool) $data['inclusive'] : false;
        $priority  = isset($data['priority']) ? (int) $data['priority'] : 0;

        return [
            'country'   => $country,
            'region'    => $region,
            'rate'      => $rate,
            'inclusive' => $inclusive ? 1 : 0,
            'priority'  => $priority,
        ];
    }

    private function mapRow(object $row): array
    {
        return [
            'id'        => (int) $row->id,
            'country'   => (string) $row->country,
            'region'    => $row->region !== null ? (string) $row->region : '',
            'rate'      => (float) $row->rate,
            'inclusive' => (bool) $row->inclusive,
            'priority'  => (int) $row->priority,
        ];
    }
}
