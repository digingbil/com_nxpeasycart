<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Table;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;

/**
 * Database table for products.
 */
class ProductTable extends Table
{
    /**
     * Constructor.
     *
     * @param DatabaseDriver $db Database connector
     */
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__nxp_easycart_products', 'id', $db);
    }

    /**
     * {@inheritDoc}
     */
    public function check()
    {
        if (empty($this->title)) {
            $this->setError(Text::_('COM_NXPEASYCART_ERROR_PRODUCT_TITLE_REQUIRED'));

            return false;
        }

        if (empty($this->slug)) {
            $this->slug = ApplicationHelper::stringURLSafe($this->title);
        }

        $this->slug = ApplicationHelper::stringURLSafe($this->slug);

        if ($this->slug === '') {
            $this->setError(Text::_('COM_NXPEASYCART_ERROR_PRODUCT_SLUG_EXISTS'));

            return false;
        }

        $db   = $this->getDbo();
        $slug = $this->slug;

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

        if ((int) $db->loadResult()) {
            $this->setError(Text::_('COM_NXPEASYCART_ERROR_PRODUCT_SLUG_EXISTS'));

            return false;
        }

        return parent::check();
    }
}
