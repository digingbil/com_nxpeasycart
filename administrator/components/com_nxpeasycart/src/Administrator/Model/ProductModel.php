<?php

namespace Nxp\EasyCart\Admin\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\AdminModel;

/**
 * Placeholder product model.
 */
class ProductModel extends AdminModel
{
    /**
     * {@inheritDoc}
     */
    public function getTable($name = 'Product', $prefix = 'Table', $options = [])
    {
        return parent::getTable($name, $prefix, $options);
    }
}
