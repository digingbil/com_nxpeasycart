<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  com_nxpeasycart
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Nxpeasycart\Administrator\Table;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use RuntimeException;

/**
 * Database table for products.
 *
 * @since 0.1.5
 */
class ProductTable extends Table
{
    /**
     * Constructor.
     *
     * @param DatabaseDriver $db Database connector
     *
     * @since 0.1.5
     */
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__nxp_easycart_products', 'id', $db);
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.5
     */
    public function check()
    {
        if (empty($this->title)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_PRODUCT_TITLE_REQUIRED'));
        }

        if (empty($this->slug)) {
            $this->slug = ApplicationHelper::stringURLSafe($this->title);
        }

        $this->slug = ApplicationHelper::stringURLSafe($this->slug);

        if ($this->slug === '') {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_PRODUCT_SLUG_EXISTS'));
        }

        $db   = $this->getDatabase();
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
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_PRODUCT_SLUG_EXISTS'));
        }

        return parent::check();
    }
}
