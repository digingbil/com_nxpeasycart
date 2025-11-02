<?php

namespace Nxp\EasyCart\Admin\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;

/**
 * Products list model.
 */
class ProductsModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param array $config Configuration array
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id',
                'title',
                'slug',
                'active',
                'featured',
                'created',
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
     */
    protected function populateState($ordering = 'a.created', $direction = 'DESC')
    {
        $app   = Factory::getApplication();
        $input = $app->input;

        $search = $input->getString('search', '');
        $limit  = $input->getInt('limit', $app->get('list_limit', 20));
        $start  = $input->getInt('start', 0);

        $this->setState('filter.search', $search);
        $this->setState('list.limit', max(0, $limit));
        $this->setState('list.start', max(0, $start));

        parent::populateState($ordering, $direction);
    }

    /**
     * Build list query.
     *
     * @return \Joomla\Database\DatabaseQuery
     */
    protected function getListQuery()
    {
        $db    = $this->getDbo();
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
                ]
            )
            ->from($db->quoteName('#__nxp_easycart_products', 'a'));

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

        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDir));

        return $query;
    }
}
