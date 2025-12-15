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

use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use Joomla\Component\Nxpeasycart\Administrator\Helper\ConfigHelper;
use RuntimeException;

/**
 * Database table for product variants.
 *
 * @since 0.1.5
 */
class VariantTable extends Table
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
        parent::__construct('#__nxp_easycart_variants', 'id', $db);
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.5
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
        $this->is_digital = (int) (bool) ($this->is_digital ?? 0);
        $this->stock  = max(0, (int) $this->stock);

        if ($this->price_cents < 0) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_VARIANT_PRICE_INVALID'));
        }

        if ($this->weight !== null && $this->weight !== '') {
            $this->weight = (float) $this->weight;
        } else {
            $this->weight = null;
        }

        // Validate EAN (optional barcode field)
        $this->ean = $this->validateEan($this->ean ?? '');

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

    /**
     * Validate and normalise an EAN barcode.
     *
     * Accepts EAN-8 or EAN-13. Returns null if empty, or the validated code.
     * Throws an exception if the format is invalid or the check digit fails.
     *
     * @param string $ean Raw EAN input
     *
     * @return string|null Validated EAN or null if empty
     *
     * @throws RuntimeException If format is invalid or check digit fails
     *
     * @since 0.1.17
     */
    private function validateEan(string $ean): ?string
    {
        $ean = trim($ean);

        if ($ean === '') {
            return null;
        }

        // Must be digits only
        if (!preg_match('/^\d+$/', $ean)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_VARIANT_EAN_FORMAT'));
        }

        $length = strlen($ean);

        // Must be 8 or 13 digits (EAN-8 or EAN-13)
        if ($length !== 8 && $length !== 13) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_VARIANT_EAN_LENGTH'));
        }

        // Validate check digit
        if (!$this->verifyEanCheckDigit($ean)) {
            throw new RuntimeException(Text::_('COM_NXPEASYCART_ERROR_VARIANT_EAN_CHECKSUM'));
        }

        return $ean;
    }

    /**
     * Verify the check digit of an EAN-8 or EAN-13 barcode.
     *
     * Uses the standard GS1 checksum algorithm.
     *
     * @param string $ean EAN code (8 or 13 digits)
     *
     * @return bool True if the check digit is valid
     *
     * @since 0.1.17
     */
    private function verifyEanCheckDigit(string $ean): bool
    {
        $length = strlen($ean);
        $sum    = 0;

        // GS1 algorithm: from right to left, alternate weights of 1 and 3
        // The last digit is the check digit
        for ($i = 0; $i < $length - 1; $i++) {
            $digit  = (int) $ean[$length - 2 - $i];
            $weight = ($i % 2 === 0) ? 3 : 1;
            $sum   += $digit * $weight;
        }

        $checkDigit           = (10 - ($sum % 10)) % 10;
        $providedCheckDigit   = (int) $ean[$length - 1];

        return $checkDigit === $providedCheckDigit;
    }
}
