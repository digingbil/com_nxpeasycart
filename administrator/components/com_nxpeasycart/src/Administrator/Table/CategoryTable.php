<?php

namespace Nxp\EasyCart\Admin\Administrator\Table;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;

/**
 * Database table for product categories.
 */
class CategoryTable extends Table
{
    /**
     * Constructor.
     *
     * @param DatabaseDriver $db Database connector
     */
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__nxp_easycart_categories', 'id', $db);
    }

    /**
     * {@inheritDoc}
     */
    public function check()
    {
        $this->title = trim((string) $this->title);

        if ($this->title === '') {
            $this->setError(Text::_('COM_NXPEASYCART_ERROR_CATEGORY_TITLE_REQUIRED'));

            return false;
        }

        if (empty($this->slug)) {
            $this->slug = ApplicationHelper::stringURLSafe($this->title);
        }

        $this->slug = ApplicationHelper::stringURLSafe((string) $this->slug);

        if ($this->slug === '') {
            $this->setError(Text::_('COM_NXPEASYCART_ERROR_CATEGORY_SLUG_INVALID'));

            return false;
        }

        $db    = $this->getDbo();
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
            $this->setError(Text::_('COM_NXPEASYCART_ERROR_CATEGORY_SLUG_EXISTS'));

            return false;
        }

        $this->sort = (int) $this->sort;

        if ($this->parent_id !== null && $this->parent_id !== '') {
            $this->parent_id = (int) $this->parent_id;
        } else {
            $this->parent_id = null;
        }

        return parent::check();
    }
}
