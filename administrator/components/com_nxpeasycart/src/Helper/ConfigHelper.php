<?php

namespace Joomla\Component\Nxpeasycart\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Table\Table;
use Joomla\Registry\Registry;
use RuntimeException;

/**
 * Component configuration helper utilities.
 *
 * @since 0.1.5
 */
class ConfigHelper
{
    /**
     * Cached base currency value.
     *
     * @since 0.1.5
     */
    private static ?string $baseCurrency = null;

    /**
     * Cached checkout phone requirement flag.
     *
     * @since 0.1.5
     */
    private static ?bool $checkoutPhoneRequired = null;

    /**
     * Cached storefront category page size.
     *
     * @since 0.1.5
     */
    private static ?int $categoryPageSize = null;

    /**
     * Cached storefront category pagination mode.
     *
     * @since 0.1.5
     */
    private static ?string $categoryPaginationMode = null;

    /**
     * Cached auto-send order emails flag.
     *
     * @since 0.1.5
     */
    private static ?bool $autoSendOrderEmails = null;

    /**
     * Cached stale order cleanup enabled flag.
     *
     * @since 0.1.9
     */
    private static ?bool $staleOrderCleanupEnabled = null;

    /**
     * Cached stale order hours threshold.
     *
     * @since 0.1.9
     */
    private static ?int $staleOrderHours = null;

    /**
     * Cached show advanced mode flag.
     *
     * @since 0.1.12
     */
    private static ?bool $showAdvancedMode = null;

    /**
     * Resolve the store's base currency (ISO 4217, uppercase).
     *
     * @since 0.1.5
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
     *
     * @since 0.1.5
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
     *
     * @since 0.1.5
     */
    public static function clearCache(): void
    {
        self::$baseCurrency = null;
        self::$checkoutPhoneRequired = null;
        self::$categoryPageSize = null;
        self::$categoryPaginationMode = null;
        self::$autoSendOrderEmails = null;
        self::$staleOrderCleanupEnabled = null;
        self::$staleOrderHours = null;
        self::$showAdvancedMode = null;
    }

    /**
     * Persist a new base currency to the component configuration.
     *
     * @since 0.1.5
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
     *
     * @since 0.1.5
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

    /**
     * Persist storefront category page size.
     *
     * @since 0.1.5
     */
    public static function setCategoryPageSize(int $limit): void
    {
        $limit = $limit > 0 ? $limit : 12;

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
        $params->set('category_page_size', $limit);

        $component->params = (string) $params;
        $table->params = (string) $params;

        if (!$table->check() || !$table->store()) {
            throw new RuntimeException('Failed to save category page size.');
        }

        self::$categoryPageSize = $limit;
    }

    /**
     * Persist storefront category pagination mode.
     *
     * @since 0.1.5
     */
    public static function setCategoryPaginationMode(string $mode): void
    {
        $mode = \in_array($mode, ['paged', 'infinite'], true) ? $mode : 'paged';

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
        $params->set('category_pagination_mode', $mode);

        $component->params = (string) $params;
        $table->params = (string) $params;

        if (!$table->check() || !$table->store()) {
            throw new RuntimeException('Failed to save category pagination mode.');
        }

        self::$categoryPaginationMode = $mode;
    }

    /**
     * Resolve the storefront category page size.
     *
     * @since 0.1.5
     */
    public static function getCategoryPageSize(): int
    {
        if (self::$categoryPageSize !== null) {
            return self::$categoryPageSize;
        }

        $params = ComponentHelper::getParams('com_nxpeasycart');
        $limit  = (int) $params->get('category_page_size', 12);

        if ($limit <= 0) {
            $limit = 12;
        }

        self::$categoryPageSize = $limit;

        return self::$categoryPageSize;
    }

    /**
     * Resolve the storefront category pagination mode.
     *
     * @since 0.1.5
     */
    public static function getCategoryPaginationMode(): string
    {
        if (self::$categoryPaginationMode !== null) {
            return self::$categoryPaginationMode;
        }

        $params = ComponentHelper::getParams('com_nxpeasycart');
        $mode   = (string) $params->get('category_pagination_mode', 'paged');
        $mode   = \in_array($mode, ['paged', 'infinite'], true) ? $mode : 'paged';

        self::$categoryPaginationMode = $mode;

        return self::$categoryPaginationMode;
    }

    /**
     * Whether to auto-send order status notification emails.
     *
     * @since 0.1.5
     */
    public static function isAutoSendOrderEmails(): bool
    {
        if (self::$autoSendOrderEmails !== null) {
            return self::$autoSendOrderEmails;
        }

        $params = ComponentHelper::getParams('com_nxpeasycart');

        self::$autoSendOrderEmails = (bool) ((int) $params->get('auto_send_order_emails', 0));

        return self::$autoSendOrderEmails;
    }

    /**
     * Persist auto-send order emails flag.
     *
     * @since 0.1.5
     */
    public static function setAutoSendOrderEmails(bool $enabled): void
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
        $params->set('auto_send_order_emails', $enabled ? 1 : 0);

        $component->params = (string) $params;
        $table->params = (string) $params;

        if (!$table->check() || !$table->store()) {
            throw new RuntimeException('Failed to save auto-send order emails setting.');
        }

        self::$autoSendOrderEmails = $enabled;
    }

    /**
     * Whether stale order cleanup is enabled.
     *
     * @since 0.1.9
     */
    public static function isStaleOrderCleanupEnabled(): bool
    {
        if (self::$staleOrderCleanupEnabled !== null) {
            return self::$staleOrderCleanupEnabled;
        }

        $params = ComponentHelper::getParams('com_nxpeasycart');

        self::$staleOrderCleanupEnabled = (bool) ((int) $params->get('stale_order_cleanup_enabled', 0));

        return self::$staleOrderCleanupEnabled;
    }

    /**
     * Persist stale order cleanup enabled flag.
     *
     * @since 0.1.9
     */
    public static function setStaleOrderCleanupEnabled(bool $enabled): void
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
        $params->set('stale_order_cleanup_enabled', $enabled ? 1 : 0);

        $component->params = (string) $params;
        $table->params = (string) $params;

        if (!$table->check() || !$table->store()) {
            throw new RuntimeException('Failed to save stale order cleanup setting.');
        }

        self::$staleOrderCleanupEnabled = $enabled;
    }

    /**
     * Get stale order hours threshold.
     *
     * @since 0.1.9
     */
    public static function getStaleOrderHours(): int
    {
        if (self::$staleOrderHours !== null) {
            return self::$staleOrderHours;
        }

        $params = ComponentHelper::getParams('com_nxpeasycart');
        $hours  = (int) $params->get('stale_order_hours', 48);

        // Clamp between 1 and 720 hours (1 hour to 30 days)
        if ($hours < 1) {
            $hours = 48;
        } elseif ($hours > 720) {
            $hours = 720;
        }

        self::$staleOrderHours = $hours;

        return self::$staleOrderHours;
    }

    /**
     * Persist stale order hours threshold.
     *
     * @since 0.1.9
     */
    public static function setStaleOrderHours(int $hours): void
    {
        // Clamp between 1 and 720 hours
        $hours = max(1, min(720, $hours));

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
        $params->set('stale_order_hours', $hours);

        $component->params = (string) $params;
        $table->params = (string) $params;

        if (!$table->check() || !$table->store()) {
            throw new RuntimeException('Failed to save stale order hours setting.');
        }

        self::$staleOrderHours = $hours;
    }

    /**
     * Whether advanced mode (Logs, Security settings) is visible.
     *
     * @since 0.1.12
     */
    public static function isShowAdvancedMode(): bool
    {
        if (self::$showAdvancedMode !== null) {
            return self::$showAdvancedMode;
        }

        $params = ComponentHelper::getParams('com_nxpeasycart');

        self::$showAdvancedMode = (bool) ((int) $params->get('show_advanced_mode', 0));

        return self::$showAdvancedMode;
    }

    /**
     * Persist show advanced mode flag.
     *
     * @since 0.1.12
     */
    public static function setShowAdvancedMode(bool $enabled): void
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
        $params->set('show_advanced_mode', $enabled ? 1 : 0);

        $component->params = (string) $params;
        $table->params = (string) $params;

        if (!$table->check() || !$table->store()) {
            throw new RuntimeException('Failed to save show advanced mode setting.');
        }

        self::$showAdvancedMode = $enabled;
    }
}
