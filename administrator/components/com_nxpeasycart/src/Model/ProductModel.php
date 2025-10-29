<?php

namespace Nxp\EasyCart\Admin\Model;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Utilities\ArrayHelper;

\defined('_JEXEC') or die;

/**
 * Admin model for a single product entity.
 */
class ProductModel extends AdminModel
{
    /**
     * Text prefix for Joomla messaging.
     *
     * @var string
     */
    protected $text_prefix = 'COM_NXPEASYCART';

    /**
     * Save the product record.
     *
     * @param array<string,mixed> $data Product payload
     *
     * @return bool
     */
    public function save($data): bool
    {
        $form = $this->getForm($data, false);

        if ($form === false) {
            return false;
        }

        $data = $this->validate($form, $data);

        if ($data === false) {
            return false;
        }

        if (empty($data['slug']) && !empty($data['title'])) {
            $data['slug'] = OutputFilter::stringURLSafe($data['title']);
        }

        /** @var \Nxp\EasyCart\Admin\Table\ProductTable $table */
        $table = $this->getTable();

        if (!$table->bind($data)) {
            $this->setError($table->getError());

            return false;
        }

        if (!$table->check()) {
            $this->setError($table->getError());

            return false;
        }

        if (!$table->store()) {
            $this->setError($table->getError());

            return false;
        }

        $this->setState('product.id', (int) $table->id);
        $this->setState($this->getName() . '.id', (int) $table->id);

        return true;
    }

    /**
     * Delete products for the given identifiers.
     *
     * @param array<int,int> $pks Product identifiers
     *
     * @return bool
     */
    public function delete(&$pks): bool
    {
        $pks = ArrayHelper::toInteger((array) $pks);

        if (empty($pks)) {
            $this->setError(Text::_('COM_NXPEASYCART_ERROR_PRODUCT_ID_REQUIRED'));

            return false;
        }

        return parent::delete($pks);
    }

    /**
     * Get the table instance.
     *
     * @param string $type   Table type
     * @param string $prefix Table prefix
     * @param array<string,mixed> $config Table config
     *
     * @return Table
     */
    public function getTable($type = 'Product', $prefix = 'Nxp\\EasyCart\\Admin\\Table\\', $config = []): Table
    {
        return Table::getInstance($type, $prefix, $config);
    }

    /**
     * Retrieve the Form for validation.
     *
     * @param array<string,mixed> $data     Data
     * @param bool                $loadData Load existing data
     *
     * @return Form|false
     */
    public function getForm($data = [], $loadData = true)
    {
        return $this->loadForm('com_nxpeasycart.product', 'product', ['control' => 'jform', 'load_data' => $loadData]);
    }

    /**
     * Autoload any previously entered data.
     *
     * @return array<string,mixed>
     */
    protected function loadFormData(): array
    {
        $data = $this->getItem();

        return \is_object($data) ? (array) $data : [];
    }
}
