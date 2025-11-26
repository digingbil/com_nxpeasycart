<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;

/**
 * Categories list model.
 */
class CategoriesModel extends ListModel
{
    /**
     * Configure default filter fields.
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
                'parent_id',
                'sort',
            ];
        }

        parent::__construct($config);
    }

    /**
     * {@inheritDoc}
     */
    protected function populateState($ordering = 'a.sort', $direction = 'ASC')
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
     * {@inheritDoc}
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
                    $db->quoteName('a.parent_id'),
                    $db->quoteName('a.sort'),
                    '(SELECT COUNT(*) FROM ' . $db->quoteName('#__nxp_easycart_product_categories', 'pc') . ' WHERE ' . $db->quoteName('pc.category_id') . ' = ' . $db->quoteName('a.id') . ') AS ' . $db->quoteName('product_count'),
                ]
            )
            ->from($db->quoteName('#__nxp_easycart_categories', 'a'));

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

        $orderCol = $this->state->get('list.ordering', 'a.sort');
        $orderDir = $this->state->get('list.direction', 'ASC');

        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDir));
        $query->order($db->quoteName('a.title') . ' ASC');

        return $query;
    }
}
