<?php

namespace Nxp\EasyCart\Admin\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Table\Table;
use Joomla\Registry\Registry;
use RuntimeException;

/**
 * Component configuration helper utilities.
 */
class ConfigHelper
{
    /**
     * Cached base currency value.
     */
    private static ?string $baseCurrency = null;

    /**
     * Resolve the store's base currency (ISO 4217, uppercase).
     */
    public static function getBaseCurrency(): string
    {
        if (self::$baseCurrency !== null) {
            return self::$baseCurrency;
        }

        $params = ComponentHelper::getParams('com_nxpeasycart');

        $raw      = strtoupper((string) $params->get('base_currency', ''));
        $filtered = preg_replace('/[^A-Z]/', '', $raw);

        if ($filtered === null || strlen($filtered) !== 3) {
            $filtered = 'USD';
        }

        self::$baseCurrency = $filtered;

        return self::$baseCurrency;
    }

    /**
     * Reset cached configuration values.
     */
    public static function clearCache(): void
    {
        self::$baseCurrency = null;
    }

    /**
     * Persist a new base currency to the component configuration.
     */
    public static function setBaseCurrency(string $currency): void
    {
        $currency = strtoupper(preg_replace('/[^A-Za-z]/', '', trim($currency ?? '')));

        if ($currency === '' || strlen($currency) !== 3) {
            throw new RuntimeException('Invalid base currency.');
        }

        $component = ComponentHelper::getComponent('com_nxpeasycart');

        if (!$component || !isset($component->id)) {
            throw new RuntimeException('Component configuration unavailable.');
        }

        /** @var \Joomla\CMS\Table\Extension $table */
        $table = Table::getInstance('extension');

        if (!$table->load((int) $component->id)) {
            throw new RuntimeException('Unable to load component record.');
        }

        $params = new Registry($table->params);
        $params->set('base_currency', $currency);

        $table->params = (string) $params;

        if (!$table->check() || !$table->store()) {
            throw new RuntimeException('Failed to save base currency setting.');
        }

        self::clearCache();
    }
}
