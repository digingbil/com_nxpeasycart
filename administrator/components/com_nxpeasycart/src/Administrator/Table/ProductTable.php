<?php

namespace Nxp\EasyCart\Admin\Administrator\Table;

\defined('_JEXEC') or die;

use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

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
        parent::__construct('#__nxp_products', 'id', $db);
    }
}
