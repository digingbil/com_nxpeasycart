<?php

namespace Nxp\EasyCart\Admin\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\Database\ParameterType;
use Nxp\EasyCart\Admin\Administrator\Table\CategoryTable;

/**
 * Single category admin model.
 */
class CategoryModel extends AdminModel
{
    /**
     * {@inheritDoc}
     *
     * @return CategoryTable
     */
    public function getTable($name = 'Category', $prefix = 'Table', $options = [])
    {
        return parent::getTable($name, $prefix, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function getForm($data = [], $loadData = true)
    {
        $form = $this->loadForm('com_nxpeasycart.category', 'category', ['control' => '', 'load_data' => $loadData]);

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
        $item = $this->getItem();

        return $item ? (array) $item : [];
    }

    /**
     * {@inheritDoc}
     */
    public function validate($form, $data, $group = null)
    {
        $valid = parent::validate($form, $data, $group);

        if ($valid === false) {
            return false;
        }

        $valid['title'] = trim((string) ($valid['title'] ?? ''));
        $valid['slug'] = isset($valid['slug']) ? trim((string) $valid['slug']) : '';
        $valid['sort'] = isset($valid['sort']) ? (int) $valid['sort'] : 0;
        $valid['parent_id'] = isset($valid['parent_id']) && $valid['parent_id'] !== ''
            ? (int) $valid['parent_id']
            : null;

        if ($valid['parent_id'] !== null && $valid['parent_id'] < 0) {
            $valid['parent_id'] = null;
        }

        $id = isset($valid['id']) ? (int) $valid['id'] : 0;

        if ($id > 0 && $valid['parent_id'] === $id) {
            $this->setError(Text::_('COM_NXPEASYCART_ERROR_CATEGORY_PARENT_INVALID'));

            return false;
        }

        if ($valid['parent_id'] !== null && !$this->categoryExists($valid['parent_id'])) {
            $this->setError(Text::_('COM_NXPEASYCART_ERROR_CATEGORY_PARENT_INVALID'));

            return false;
        }

        return $valid;
    }

    /**
     * {@inheritDoc}
     */
    protected function prepareTable($table)
    {
        $table->title = trim((string) $table->title);
        $table->slug = trim((string) $table->slug);
        $table->sort = (int) $table->sort;

        if ($table->sort < 0) {
            $table->sort = 0;
        }

        if ($table->parent_id !== null && $table->parent_id !== '') {
            $table->parent_id = (int) $table->parent_id;
        } else {
            $table->parent_id = null;
        }

        if ((int) $table->id === 0 && $table->sort === 0) {
            $table->sort = $this->resolveNextSort();
        }
    }

    /**
     * Determine whether a category exists.
     */
    private function categoryExists(int $id): bool
    {
        $db = $this->getDbo();
        $query = $db->getQuery(true)
            ->select('1')
            ->from($db->quoteName('#__nxp_easycart_categories'))
            ->where($db->quoteName('id') . ' = :categoryId')
            ->bind(':categoryId', $id, ParameterType::INTEGER)
            ->setLimit(1);

        $db->setQuery($query);

        return (bool) $db->loadResult();
    }

    /**
     * Calculate the next sort position.
     */
    private function resolveNextSort(): int
    {
        $db = $this->getDbo();
        $query = $db->getQuery(true)
            ->select('MAX(' . $db->quoteName('sort') . ')')
            ->from($db->quoteName('#__nxp_easycart_categories'));

        $db->setQuery($query);
        $max = (int) $db->loadResult();

        return $max + 1;
    }
}
