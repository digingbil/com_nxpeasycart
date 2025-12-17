<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;

/**
 * Products list model.
 *
 * @since 0.1.5
 */
class ProductsModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param array $config Configuration array
     *
     * @since 0.1.5
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id',
                'title',
                'slug',
                'active',
                'status',
                'featured',
                'created',
                'modified',
                'category_id',
            ];
        }

        parent::__construct($config);
    }

    /**
     * Populate model state.
     *
     * @param string $ordering  Column to order by
     * @param string $direction Order direction
     *
     * @return void
     *
     * @since 0.1.5
     */
    protected function populateState($ordering = 'a.created', $direction = 'DESC')
    {
        $app   = Factory::getApplication();
        $input = $app->input;

        $search     = $input->getString('search', '');
        $limit      = $input->getInt('limit', $app->get('list_limit', 20));
        $start      = $input->getInt('start', 0);
        $categoryId = $input->getInt('category_id', 0);
        $sortColumn = $input->getCmd('sort', '');
        $sortDir    = $input->getCmd('sort_dir', 'DESC');

        $this->setState('filter.search', $search);
        $this->setState('filter.category_id', $categoryId > 0 ? $categoryId : null);
        $this->setState('list.limit', max(0, $limit));
        $this->setState('list.start', max(0, $start));

        // Map frontend column names to database columns
        $sortMap = [
            'id'       => 'a.id',
            'title'    => 'a.title',
            'status'   => 'a.active',
            'modified' => 'a.modified',
        ];

        if ($sortColumn !== '' && isset($sortMap[$sortColumn])) {
            $ordering  = $sortMap[$sortColumn];
            $direction = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';
        }

        parent::populateState($ordering, $direction);
    }

    /**
     * Build list query.
     *
     * @return \Joomla\Database\DatabaseQuery
     *
     * @since 0.1.5
     */
    protected function getListQuery()
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select(
                [
                    $db->quoteName('a.id'),
                    $db->quoteName('a.title'),
                    $db->quoteName('a.slug'),
                    $db->quoteName('a.short_desc'),
                    $db->quoteName('a.long_desc'),
                    $db->quoteName('a.active'),
                    $db->quoteName('a.featured'),
                    $db->quoteName('a.created'),
                    $db->quoteName('a.created_by'),
                    $db->quoteName('a.modified'),
                    $db->quoteName('a.modified_by'),
                    $db->quoteName('a.images'),
                    $db->quoteName('a.checked_out'),
                    $db->quoteName('a.checked_out_time'),
                ]
            )
            ->from($db->quoteName('#__nxp_easycart_products', 'a'));

        // Filter by category
        $categoryId = $this->getState('filter.category_id');

        if ($categoryId !== null && $categoryId > 0) {
            $query->join(
                'INNER',
                $db->quoteName('#__nxp_easycart_product_categories', 'pc')
                . ' ON ' . $db->quoteName('pc.product_id') . ' = ' . $db->quoteName('a.id')
            )
            ->where($db->quoteName('pc.category_id') . ' = :categoryId')
            ->bind(':categoryId', $categoryId, ParameterType::INTEGER)
            ->group($db->quoteName('a.id'));
        }

        $search = $this->getState('filter.search');

        if ($search !== '') {
            if (str_starts_with($search, 'id:')) {
                $id = (int) substr($search, 3);
                $query->where($db->quoteName('a.id') . ' = :searchId')
                    ->bind(':searchId', $id, ParameterType::INTEGER);
            } else {
                $searchLike = '%' . $db->escape($search, true) . '%';
                $query->where(
                    '(' .
                    $db->quoteName('a.title') . ' LIKE :search' .
                    ' OR ' . $db->quoteName('a.slug') . ' LIKE :search' .
                    ')'
                )->bind(':search', $searchLike, ParameterType::STRING);
            }
        }

        $orderCol = $this->state->get('list.ordering', 'a.created');
        $orderDir = $this->state->get('list.direction', 'DESC');

        // When sorting by modified, use COALESCE to fall back to created date for NULL values
        // This matches the UI display which shows (modified || created)
        if ($orderCol === 'a.modified') {
            $orderClause = 'COALESCE(' . $db->quoteName('a.modified') . ', ' . $db->quoteName('a.created') . ') ' . $db->escape($orderDir);
        } else {
            $orderClause = $db->escape($orderCol) . ' ' . $db->escape($orderDir);
        }

        // Add secondary sort by ID for deterministic pagination when primary column has duplicate values
        $query->order($orderClause . ', ' . $db->quoteName('a.id') . ' ' . $db->escape($orderDir));

        return $query;
    }
}
