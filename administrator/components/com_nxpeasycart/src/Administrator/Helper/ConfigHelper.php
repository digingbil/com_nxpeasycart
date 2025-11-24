<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Helper;

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
     * Cached checkout phone requirement flag.
     */
    private static ?bool $checkoutPhoneRequired = null;

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
     * Whether checkout should require a phone number.
     */
    public static function isCheckoutPhoneRequired(): bool
    {
        if (self::$checkoutPhoneRequired !== null) {
            return self::$checkoutPhoneRequired;
        }

        $params = ComponentHelper::getParams('com_nxpeasycart');

        self::$checkoutPhoneRequired = (bool) ((int) $params->get('checkout_phone_required', 0));

        return self::$checkoutPhoneRequired;
    }

    /**
     * Reset cached configuration values.
     */
    public static function clearCache(): void
    {
        self::$baseCurrency = null;
        self::$checkoutPhoneRequired = null;
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

        $component->params = (string) $params;
        $table->params = (string) $params;

        if (!$table->check() || !$table->store()) {
            throw new RuntimeException('Failed to save base currency setting.');
        }

        self::$baseCurrency = $currency;
    }

    /**
     * Persist checkout phone requirement flag.
     */
    public static function setCheckoutPhoneRequired(bool $required): void
    {
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
        $params->set('checkout_phone_required', $required ? 1 : 0);

        $component->params = (string) $params;
        $table->params = (string) $params;

        if (!$table->check() || !$table->store()) {
            throw new RuntimeException('Failed to save checkout phone setting.');
        }

        self::$checkoutPhoneRequired = $required;
    }
}
