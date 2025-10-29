<?php

namespace Nxp\EasyCart\Admin\Table;

use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

\defined('_JEXEC') or die;

/**
 * Table class for products.
 */
class ProductTable extends Table
{
    /**
     * Constructor.
     *
     * @param DatabaseDriver $db Database driver
     */
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__nxp_products', 'id', $db);
    }

    /**
     * Validate record properties prior to storage.
     *
     * @return bool
     */
    public function check(): bool
    {
        if (trim((string) $this->title) === '') {
            $this->setError(Text::_('COM_NXPEASYCART_ERROR_PRODUCT_TITLE_REQUIRED'));

            return false;
        }

        if (trim((string) $this->slug) === '') {
            $this->slug = OutputFilter::stringURLSafe($this->title);
        }

        $this->slug = OutputFilter::stringURLSafe($this->slug);

        // Ensure slug uniqueness.
        $query = $this->_db->getQuery(true)
            ->select($this->_db->quoteName('id'))
            ->from($this->_db->quoteName('#__nxp_products'))
            ->where($this->_db->quoteName('slug') . ' = ' . $this->_db->quote($this->slug));

        if ((int) $this->id > 0) {
            $query->where($this->_db->quoteName('id') . ' != ' . (int) $this->id);
        }

        $this->_db->setQuery($query);

        if ((int) $this->_db->loadResult() > 0) {
            $this->setError(Text::_('COM_NXPEASYCART_ERROR_PRODUCT_SLUG_EXISTS'));

            return false;
        }

        return parent::check();
    }
}
