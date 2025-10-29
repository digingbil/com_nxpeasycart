<?php

namespace Nxp\EasyCart\Admin\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;

/**
 * Product admin model.
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

    /**
     * {@inheritDoc}
     */
    public function getForm($data = [], $loadData = true)
    {
        $form = $this->loadForm('com_nxpeasycart.product', 'product', ['control' => '', 'load_data' => $loadData]);

        if (empty($form)) {
            return false;
        }

        return $form;
    }

    /**
     * {@inheritDoc}
     */
    protected function loadFormData()
    {
        $data = $this->getData();

        if (!$data) {
            $data = [];
        }

        return $data;
    }

    /**
     * {@inheritDoc}
     */
    protected function prepareTable($table)
    {
        if (empty($table->slug) && !empty($table->title)) {
            $table->slug = ApplicationHelper::stringURLSafe($table->title);
        }

        if (!empty($table->slug)) {
            $table->slug = ApplicationHelper::stringURLSafe($table->slug);
        }

        $date = Factory::getDate();
        $user = Factory::getApplication()->getIdentity();

        if (empty($table->id)) {
            $table->created = $date->toSql();
            $table->created_by = (int) $user->id;
        } else {
            $table->modified = $date->toSql();
            $table->modified_by = (int) $user->id;
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function canDelete($record)
    {
        $user = Factory::getApplication()->getIdentity();

        return (bool) $user->authorise('core.delete', 'com_nxpeasycart');
    }

    /**
     * {@inheritDoc}
     */
    protected function canEdit($record)
    {
        $user = Factory::getApplication()->getIdentity();

        return (bool) $user->authorise('core.edit', 'com_nxpeasycart');
    }

    /**
     * {@inheritDoc}
     */
    public function validate($form, $data, $group = null)
    {
        $validated = parent::validate($form, $data, $group);

        if ($validated === false) {
            return false;
        }

        if (empty($validated['title'])) {
            $this->setError(Text::_('COM_NXPEASYCART_ERROR_PRODUCT_TITLE_REQUIRED'));

            return false;
        }

        return $validated;
    }
}
