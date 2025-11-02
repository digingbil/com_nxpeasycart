<?php

namespace Nxp\EasyCart\Admin\Administrator\Service;

\defined('_JEXEC') or die;

use DateTimeImmutable;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Lightweight audit log helper for recording state changes.
 */
class AuditService
{
    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    public function record(string $entityType, int $entityId, string $action, array $context = [], ?int $userId = null): void
    {
        $entry = (object) [
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'action'      => $action,
            'context'     => empty($context) ? null : json_encode($context, JSON_UNESCAPED_SLASHES),
            'created'     => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
            'created_by'  => $userId,
        ];

        $this->db->insertObject('#__nxp_easycart_audit', $entry);
    }

    public function forOrder(int $orderId): array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__nxp_easycart_audit'))
            ->where($this->db->quoteName('entity_type') . ' = :type')
            ->where($this->db->quoteName('entity_id') . ' = :id')
            ->order($this->db->quoteName('created') . ' DESC')
            ->bind(':type', 'order', ParameterType::STRING)
            ->bind(':id', $orderId, ParameterType::INTEGER);

        $this->db->setQuery($query);

        $rows = $this->db->loadObjectList() ?: [];

        return array_map(function ($row) {
            $context = [];

            if (!empty($row->context)) {
                $decoded = json_decode($row->context, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $context = $decoded;
                }
            }

            return [
                'id'         => (int) $row->id,
                'action'     => (string) $row->action,
                'context'    => $context,
                'created'    => (string) $row->created,
                'created_by' => $row->created_by !== null ? (int) $row->created_by : null,
            ];
        }, $rows);
    }

    public function paginate(array $filters = [], int $limit = 20, int $start = 0): array
    {
        $limit  = $limit > 0 ? $limit : 20;
        $start  = $start >= 0 ? $start : 0;
        $entity = isset($filters['entity']) ? trim((string) $filters['entity']) : '';
        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';

        $query = $this->db->getQuery(true)
            ->select([
                'a.*',
                'u.name AS user_name',
                'u.username AS user_username',
            ])
            ->from($this->db->quoteName('#__nxp_easycart_audit', 'a'))
            ->leftJoin(
                $this->db->quoteName('#__users', 'u')
                . ' ON ' . $this->db->quoteName('u.id') . ' = ' . $this->db->quoteName('a.created_by')
            )
            ->order($this->db->quoteName('a.created') . ' DESC');

        if ($entity !== '') {
            $query->where($this->db->quoteName('a.entity_type') . ' = :entity')
                ->bind(':entity', $entity, ParameterType::STRING);
        }

        if ($search !== '') {
            $searchParam = '%' . $search . '%';
            $query->where(
                '(' . $this->db->quoteName('a.action') . ' LIKE :search'
                . ' OR ' . $this->db->quoteName('a.context') . ' LIKE :search)'
            )
                ->bind(':search', $searchParam, ParameterType::STRING);
        }

        $countQuery = clone $query;
        $countQuery->clear('select')->select('COUNT(*)');

        $this->db->setQuery($countQuery);
        $total = (int) $this->db->loadResult();

        $query->setLimit($limit, $start);
        $this->db->setQuery($query);
        $rows = $this->db->loadObjectList() ?: [];

        $items = array_map(function ($row) {
            $context = [];

            if (!empty($row->context)) {
                $decoded = json_decode($row->context, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $context = $decoded;
                }
            }

            return [
                'id'          => (int) $row->id,
                'entity_type' => (string) $row->entity_type,
                'entity_id'   => (int) $row->entity_id,
                'action'      => (string) $row->action,
                'context'     => $context,
                'created'     => (string) $row->created,
                'created_by'  => $row->created_by !== null ? (int) $row->created_by : null,
                'user'        => [
                    'name'     => $row->user_name         !== null ? (string) $row->user_name : '',
                    'username' => $row->user_username !== null ? (string) $row->user_username : '',
                ],
            ];
        }, $rows);

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
}
