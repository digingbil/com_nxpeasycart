<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Table;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use Joomla\Component\Nxpeasycart\Administrator\Helper\ConfigHelper;
use RuntimeException;

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
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_VARIANT_PRODUCT_REQUIRED'));
        }

        $this->sku = trim((string) $this->sku);

        if ($this->sku === '') {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_VARIANT_SKU_REQUIRED'));
        }

        $this->currency = strtoupper(trim((string) $this->currency));

        if ($this->currency === '') {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_VARIANT_CURRENCY_REQUIRED'));
        }

        $baseCurrency = ConfigHelper::getBaseCurrency();

        if ($this->currency !== $baseCurrency) {
            throw new RuntimeException(Text::sprintf('COM_NXPEASYCART_ERROR_VARIANT_CURRENCY_MISMATCH', $baseCurrency));
        }

        $this->active = (int) (bool) $this->active;
        $this->stock  = max(0, (int) $this->stock);

        if ($this->price_cents < 0) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_VARIANT_PRICE_INVALID'));
        }

        if ($this->weight !== null && $this->weight !== '') {
            $this->weight = (float) $this->weight;
        } else {
            $this->weight = null;
        }

        // Enforce unique SKU
        $db  = $this->getDatabase();
        $sku = (string) $this->sku;

        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName($this->_tbl))
            ->where($db->quoteName('sku') . ' = :sku')
            ->bind(':sku', $sku, ParameterType::STRING);

        if (!empty($this->id)) {
            $currentId = (int) $this->id;
            $query->where($db->quoteName('id') . ' != :currentId')
                ->bind(':currentId', $currentId, ParameterType::INTEGER);
        }

        $db->setQuery($query);

        if ((int) $db->loadResult() > 0) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_VARIANT_SKU_EXISTS'));
        }

        return parent::check();
    }
}
