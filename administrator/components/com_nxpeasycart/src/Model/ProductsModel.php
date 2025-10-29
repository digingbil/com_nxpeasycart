<?php

namespace Nxp\EasyCart\Admin\Model;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\QueryInterface;

\defined('_JEXEC') or die;

/**
 * List model for catalog products.
 */
class ProductsModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param array<string,mixed> $config Model config
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id',
                'title',
                'slug',
                'active',
                'created',
            ];
        }

        parent::__construct($config);
    }

    /**
     * Populate the model state.
     *
     * @param string|null $ordering  Ordering column
     * @param string|null $direction Direction
     *
     * @return void
     */
    protected function populateState($ordering = null, $direction = null): void
    {
        $app = $this->getApplication();
        $search = $app->input->getString('search');
        $active = $app->input->getCmd('active');

        $this->setState('filter.search', $search);

        if ($active !== null && $active !== '') {
            $this->setState('filter.active', (int) $active);
        }

        parent::populateState($ordering ?? 'p.created', $direction ?? 'DESC');

        $limit = $app->input->getInt('limit', $app->get('list_limit', 20));
        $this->setState('list.limit', $limit > 0 ? $limit : 20);
    }

    /**
     * Build the list query.
     *
     * @return QueryInterface
     */
    protected function getListQuery(): QueryInterface
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select(
            [
                $db->quoteName('p.id'),
                $db->quoteName('p.title'),
                $db->quoteName('p.slug'),
                $db->quoteName('p.active'),
                $db->quoteName('p.created'),
                $db->quoteName('p.modified'),
            ]
        )
            ->from($db->quoteName('#__nxp_products', 'p'));

        $search = $this->getState('filter.search');

        if (!empty($search)) {
            $token = '%' . $db->escape($search, true) . '%';
            $query->where(
                '(' . $db->quoteName('p.title') . ' LIKE ' . $db->quote($token, false)
                . ' OR ' . $db->quoteName('p.slug') . ' LIKE ' . $db->quote($token, false) . ')'
            );
        }

        $active = $this->getState('filter.active');

        if ($active !== null) {
            $query->where($db->quoteName('p.active') . ' = ' . (int) $active);
        }

        $orderCol = $this->state->get('list.ordering', 'p.created');
        $orderDir = $this->state->get('list.direction', 'DESC');

        if (!\in_array($orderCol, ['p.id', 'p.title', 'p.slug', 'p.active', 'p.created'], true)) {
            $orderCol = 'p.created';
        }

        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDir));

        return $query;
    }
}
