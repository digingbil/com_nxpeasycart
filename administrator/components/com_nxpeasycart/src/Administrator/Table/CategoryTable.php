<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Table;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use RuntimeException;

/**
 * Database table for product categories.
 *
 * @since 0.1.5
 */
class CategoryTable extends Table
{
    /**
     * Columns that can be set to NULL when stored.
     *
     * Joomla's Table::store() skips null values by default.
     * Listing columns here ensures they get SET to NULL explicitly.
     *
     * @var array<int, string>
     *
     * @since 0.1.5
     */
    protected $_nullable = ['parent_id'];

    /**
     * Constructor.
     *
     * @param DatabaseDriver $db Database connector
     *
     * @since 0.1.5
     */
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__nxp_easycart_categories', 'id', $db);
    }

    /**
     * {@inheritDoc}
     *
     * Override to ensure nullable columns (like parent_id) are included
     * in UPDATE queries even when their value is NULL.
     *
     * @since 0.1.5
     */
    public function store($updateNulls = true)
    {
        return parent::store($updateNulls);
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.5
     */
    public function check()
    {
        $this->title = trim((string) $this->title);

        if ($this->title === '') {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_CATEGORY_TITLE_REQUIRED'));
        }

        if (empty($this->slug)) {
            $this->slug = ApplicationHelper::stringURLSafe($this->title);
        }

        $this->slug = ApplicationHelper::stringURLSafe((string) $this->slug);

        if ($this->slug === '') {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_CATEGORY_SLUG_INVALID'));
        }

        $db    = $this->getDatabase();
        $slug  = $this->slug;
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName($this->_tbl))
            ->where($db->quoteName('slug') . ' = :slug')
            ->bind(':slug', $slug, ParameterType::STRING);

        if (!empty($this->id)) {
            $currentId = (int) $this->id;
            $query->where($db->quoteName('id') . ' != :currentId')
                ->bind(':currentId', $currentId, ParameterType::INTEGER);
        }

        $db->setQuery($query);

        if ((int) $db->loadResult() > 0) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_CATEGORY_SLUG_EXISTS'));
        }

        $this->sort = (int) $this->sort;

        // Handle parent_id: null, empty string, 0 all mean "no parent"
        if ($this->parent_id === null || $this->parent_id === '' || $this->parent_id === 0 || $this->parent_id === '0') {
            $this->parent_id = null;
        } else {
            $this->parent_id = (int) $this->parent_id;
        }

        return parent::check();
    }
}
