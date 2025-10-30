<?php

namespace Nxp\EasyCart\Admin\Administrator\Table;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use Nxp\EasyCart\Admin\Administrator\Helper\ConfigHelper;

/**
 * Database table for product variants.
 */
class VariantTable extends Table
{
    /**
     * Constructor.
     *
     * @param DatabaseDriver $db Database connector
     */
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__nxp_easycart_variants', 'id', $db);
    }

    /**
     * {@inheritDoc}
     */
    public function check()
    {
        if (empty($this->product_id)) {
            $this->setError(Text::_('COM_NXPEASYCART_ERROR_VARIANT_PRODUCT_REQUIRED'));

            return false;
        }

        $this->sku = trim((string) $this->sku);

        if ($this->sku === '') {
            $this->setError(Text::_('COM_NXPEASYCART_ERROR_VARIANT_SKU_REQUIRED'));

            return false;
        }

        $this->currency = strtoupper(trim((string) $this->currency));

        if ($this->currency === '') {
            $this->setError(Text::_('COM_NXPEASYCART_ERROR_VARIANT_CURRENCY_REQUIRED'));

            return false;
        }

        $baseCurrency = ConfigHelper::getBaseCurrency();

        if ($this->currency !== $baseCurrency) {
            $this->setError(Text::sprintf('COM_NXPEASYCART_ERROR_VARIANT_CURRENCY_MISMATCH', $baseCurrency));

            return false;
        }

        $this->active = (int) (bool) $this->active;
        $this->stock = max(0, (int) $this->stock);

        if ($this->price_cents < 0) {
            $this->setError(Text::_('COM_NXPEASYCART_ERROR_VARIANT_PRICE_INVALID'));

            return false;
        }

        if ($this->weight !== null && $this->weight !== '') {
            $this->weight = (float) $this->weight;
        } else {
            $this->weight = null;
        }

        // Enforce unique SKU
        $db = $this->getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName($this->_tbl))
            ->where($db->quoteName('sku') . ' = :sku')
            ->bind(':sku', $this->sku, ParameterType::STRING);

        if (!empty($this->id)) {
            $query->where($db->quoteName('id') . ' != :currentId')
                ->bind(':currentId', (int) $this->id, ParameterType::INTEGER);
        }

        $db->setQuery($query);

        if ((int) $db->loadResult() > 0) {
            $this->setError(Text::_('COM_NXPEASYCART_ERROR_VARIANT_SKU_EXISTS'));

            return false;
        }

        return parent::check();
    }
}
