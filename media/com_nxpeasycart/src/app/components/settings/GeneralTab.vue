<template>
    <div class="nxp-ec-settings-panel">
        <header class="nxp-ec-settings-panel__header">
            <h3>
                {{
                    __(
                        "COM_NXPEASYCART_SETTINGS_GENERAL_TITLE",
                        "Store defaults",
                        [],
                        "settingsGeneralTitle"
                    )
                }}
            </h3>
            <button
                class="nxp-ec-btn"
                type="button"
                @click="$emit('refresh')"
                :disabled="loading"
            >
                {{
                    __(
                        "COM_NXPEASYCART_SETTINGS_GENERAL_REFRESH",
                        "Refresh",
                        [],
                        "settingsGeneralRefresh"
                    )
                }}
            </button>
        </header>

        <div
            v-if="error"
            class="nxp-ec-admin-alert nxp-ec-admin-alert--error"
        >
            {{ error }}
        </div>

        <div
            v-else-if="loading"
            class="nxp-ec-admin-panel__loading"
        >
            {{
                __(
                    "COM_NXPEASYCART_SETTINGS_GENERAL_LOADING",
                    "Loading settings...",
                    [],
                    "settingsGeneralLoading"
                )
            }}
        </div>

        <form
            v-else
            class="nxp-ec-settings-form nxp-ec-settings-form--payments"
            @submit.prevent="$emit('save')"
        >
            <fieldset>
                <legend>
                    {{
                        __(
                            "COM_NXPEASYCART_SETTINGS_GENERAL_STORE_INFO",
                            "Store Information",
                            [],
                            "settingsGeneralStoreInfo"
                        )
                    }}
                </legend>
                <div class="nxp-ec-form-field">
                    <label class="nxp-ec-form-label" for="settings-store-name">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_GENERAL_STORE_NAME",
                                "Store name",
                                [],
                                "settingsGeneralStoreName"
                            )
                        }}
                    </label>
                    <input
                        id="settings-store-name"
                        class="nxp-ec-form-input"
                        type="text"
                        v-model.trim="draft.storeName"
                        maxlength="190"
                    />
                </div>

                <div class="nxp-ec-form-field">
                    <label class="nxp-ec-form-label" for="settings-store-email">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_GENERAL_STORE_EMAIL",
                                "Support email",
                                [],
                                "settingsGeneralStoreEmail"
                            )
                        }}
                    </label>
                    <input
                        id="settings-store-email"
                        class="nxp-ec-form-input"
                        type="email"
                        v-model.trim="draft.storeEmail"
                    />
                </div>

                <div class="nxp-ec-form-field">
                    <label class="nxp-ec-form-label" for="settings-store-phone">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_GENERAL_STORE_PHONE",
                                "Support phone",
                                [],
                                "settingsGeneralStorePhone"
                            )
                        }}
                    </label>
                    <input
                        id="settings-store-phone"
                        class="nxp-ec-form-input"
                        type="text"
                        v-model.trim="draft.storePhone"
                        maxlength="64"
                    />
                </div>
            </fieldset>

            <fieldset>
                <legend>
                    {{
                        __(
                            "COM_NXPEASYCART_SETTINGS_GENERAL_CHECKOUT",
                            "Checkout",
                            [],
                            "settingsGeneralCheckout"
                        )
                    }}
                </legend>
                <div class="nxp-ec-form-field">
                    <label class="nxp-ec-form-label" for="settings-base-currency">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_GENERAL_BASE_CURRENCY",
                                "Base currency",
                                [],
                                "settingsGeneralBaseCurrency"
                            )
                        }}
                    </label>
                    <select
                        id="settings-base-currency"
                        class="nxp-ec-form-select"
                        v-model="draft.baseCurrency"
                        required
                    >
                        <option
                            v-for="currency in currencies"
                            :key="currency.code"
                            :value="currency.code"
                        >
                            {{ currency.label }}
                        </option>
                    </select>
                    <p class="nxp-ec-form-help">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_GENERAL_BASE_CURRENCY_HELP",
                                "Select the store's base currency (ISO 4217).",
                                [],
                                "settingsGeneralBaseCurrencyHelp"
                            )
                        }}
                    </p>
                </div>

                <div class="nxp-ec-form-field nxp-ec-form-field--inline">
                    <label class="nxp-ec-form-label" for="settings-checkout-phone-required">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_GENERAL_CHECKOUT_PHONE_REQUIRED",
                                "Require phone at checkout",
                                [],
                                "settingsGeneralCheckoutPhoneRequired"
                            )
                        }}
                    </label>
                    <input
                        id="settings-checkout-phone-required"
                        class="nxp-ec-form-checkbox"
                        type="checkbox"
                        v-model="draft.checkoutPhoneRequired"
                    />
                    <p class="nxp-ec-form-help">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_GENERAL_CHECKOUT_PHONE_HELP",
                                "Collect a phone number during checkout (recommended for delivery issues).",
                                [],
                                "settingsGeneralCheckoutPhoneHelp"
                            )
                        }}
                    </p>
                </div>

                <div class="nxp-ec-form-field">
                    <label class="nxp-ec-form-label" for="settings-display-locale">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_GENERAL_DISPLAY_LOCALE",
                                "Price display locale",
                                [],
                                "settingsGeneralDisplayLocale"
                            )
                        }}
                    </label>
                    <input
                        id="settings-display-locale"
                        class="nxp-ec-form-input"
                        type="text"
                        v-model.trim="draft.displayLocale"
                        maxlength="10"
                        placeholder="Auto (from site language)"
                        pattern="[a-z]{2}[-_][A-Z]{2}"
                    />
                    <p class="nxp-ec-form-help">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_GENERAL_DISPLAY_LOCALE_HELP",
                                "Override price formatting locale. Leave empty to use Joomla's site language. Examples: mk-MK, de-DE, en-US",
                                [],
                                "settingsGeneralDisplayLocaleHelp"
                            )
                        }}
                    </p>
                </div>
            </fieldset>

            <fieldset>
                <legend>
                    {{
                        __(
                            "COM_NXPEASYCART_SETTINGS_GENERAL_STOREFRONT",
                            "Storefront",
                            [],
                            "settingsGeneralStorefront"
                        )
                    }}
                </legend>
                <div class="nxp-ec-form-field">
                    <label
                        class="nxp-ec-form-label"
                        for="settings-category-page-size"
                    >
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_GENERAL_CATEGORY_PAGE_SIZE",
                                "Products per page",
                                [],
                                "settingsGeneralCategoryPageSize"
                            )
                        }}
                    </label>
                    <input
                        id="settings-category-page-size"
                        class="nxp-ec-form-input"
                        type="number"
                        min="1"
                        step="1"
                        v-model.number="draft.categoryPageSize"
                    />
                    <p class="nxp-ec-form-help">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_GENERAL_CATEGORY_PAGE_SIZE_HELP",
                                "How many products to show per page on the storefront category grid.",
                                [],
                                "settingsGeneralCategoryPageSizeHelp"
                            )
                        }}
                    </p>
                </div>

                <div class="nxp-ec-form-field">
                    <label
                        class="nxp-ec-form-label"
                        for="settings-category-pagination-mode"
                    >
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_GENERAL_CATEGORY_PAGINATION_MODE",
                                "Category pagination mode",
                                [],
                                "settingsGeneralCategoryPaginationMode"
                            )
                        }}
                    </label>
                    <select
                        id="settings-category-pagination-mode"
                        class="nxp-ec-form-select"
                        v-model="draft.categoryPaginationMode"
                    >
                        <option value="paged">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_GENERAL_CATEGORY_PAGINATION_PAGED",
                                    "Previous/Next links",
                                    [],
                                    "settingsGeneralCategoryPaginationPaged"
                                )
                            }}
                        </option>
                        <option value="infinite">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_GENERAL_CATEGORY_PAGINATION_INFINITE",
                                    "Load more on scroll",
                                    [],
                                    "settingsGeneralCategoryPaginationInfinite"
                                )
                            }}
                        </option>
                    </select>
                    <p class="nxp-ec-form-help">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_GENERAL_CATEGORY_PAGINATION_MODE_HELP",
                                "Choose between classic paging links or an infinite load-more experience on the category grid.",
                                [],
                                "settingsGeneralCategoryPaginationModeHelp"
                            )
                        }}
                    </p>
                </div>
            </fieldset>

            <fieldset>
                <legend>
                    {{
                        __(
                            "COM_NXPEASYCART_SETTINGS_GENERAL_NOTIFICATIONS",
                            "Notifications",
                            [],
                            "settingsGeneralNotifications"
                        )
                    }}
                </legend>
                <div class="nxp-ec-form-field nxp-ec-form-field--inline">
                    <label
                        class="nxp-ec-form-label"
                        for="settings-auto-send-order-emails"
                    >
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_GENERAL_AUTO_SEND_EMAILS",
                                "Auto-send order emails",
                                [],
                                "settingsGeneralAutoSendEmails"
                            )
                        }}
                    </label>
                    <input
                        id="settings-auto-send-order-emails"
                        class="nxp-ec-form-checkbox"
                        type="checkbox"
                        v-model="draft.autoSendOrderEmails"
                    />
                    <p class="nxp-ec-form-help">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_GENERAL_AUTO_SEND_EMAILS_HELP",
                                "Automatically send email notifications when order status changes (e.g. shipped, refunded).",
                                [],
                                "settingsGeneralAutoSendEmailsHelp"
                            )
                        }}
                    </p>
                </div>
            </fieldset>

            <fieldset>
                <legend>
                    {{
                        __(
                            "COM_NXPEASYCART_SETTINGS_GENERAL_STALE_ORDER_CLEANUP",
                            "Stale Order Cleanup",
                            [],
                            "settingsGeneralStaleOrderCleanup"
                        )
                    }}
                </legend>

                <div class="nxp-ec-form-field nxp-ec-form-field--inline">
                    <label
                        class="nxp-ec-form-label"
                        for="settings-stale-order-cleanup-enabled"
                    >
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_GENERAL_STALE_ORDER_ENABLED",
                                "Enable stale order cleanup",
                                [],
                                "settingsGeneralStaleOrderEnabled"
                            )
                        }}
                    </label>
                    <input
                        id="settings-stale-order-cleanup-enabled"
                        class="nxp-ec-form-checkbox"
                        type="checkbox"
                        v-model="draft.staleOrderCleanupEnabled"
                    />
                    <p class="nxp-ec-form-help">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_GENERAL_STALE_ORDER_ENABLED_HELP",
                                "Automatically cancel pending orders older than the threshold below. Requires the NXP Easy Cart Cleanup task plugin to be enabled in System > Scheduled Tasks.",
                                [],
                                "settingsGeneralStaleOrderEnabledHelp"
                            )
                        }}
                    </p>
                </div>

                <div
                    v-if="draft.staleOrderCleanupEnabled"
                    class="nxp-ec-form-field"
                >
                    <label
                        class="nxp-ec-form-label"
                        for="settings-stale-order-hours"
                    >
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_GENERAL_STALE_ORDER_HOURS",
                                "Hours before cleanup",
                                [],
                                "settingsGeneralStaleOrderHours"
                            )
                        }}
                    </label>
                    <input
                        id="settings-stale-order-hours"
                        class="nxp-ec-form-input"
                        type="number"
                        min="1"
                        max="720"
                        step="1"
                        v-model.number="draft.staleOrderHours"
                    />
                    <p class="nxp-ec-form-help">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_GENERAL_STALE_ORDER_HOURS_HELP",
                                "Pending orders older than this will be automatically canceled and their reserved stock released (1-720 hours, default 48 = 2 days).",
                                [],
                                "settingsGeneralStaleOrderHoursHelp"
                            )
                        }}
                    </p>
                </div>
            </fieldset>

            <fieldset>
                <legend>
                    {{
                        __(
                            "COM_NXPEASYCART_SETTINGS_GENERAL_ADVANCED_MODE",
                            "Advanced Mode",
                            [],
                            "settingsGeneralAdvancedMode"
                        )
                    }}
                </legend>

                <div class="nxp-ec-form-field nxp-ec-form-field--inline">
                    <label
                        class="nxp-ec-form-label"
                        for="settings-show-advanced-mode"
                    >
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_GENERAL_SHOW_ADVANCED",
                                "Show advanced options",
                                [],
                                "settingsGeneralShowAdvanced"
                            )
                        }}
                    </label>
                    <input
                        id="settings-show-advanced-mode"
                        class="nxp-ec-form-checkbox"
                        type="checkbox"
                        v-model="draft.showAdvancedMode"
                    />
                    <p class="nxp-ec-form-help">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_GENERAL_SHOW_ADVANCED_HELP",
                                "Enable to show Security settings and Logs panel. These are typically only needed for debugging or advanced configuration.",
                                [],
                                "settingsGeneralShowAdvancedHelp"
                            )
                        }}
                    </p>
                </div>
            </fieldset>

            <div
                v-if="message"
                class="nxp-ec-admin-alert nxp-ec-admin-alert--success"
            >
                {{ message }}
            </div>

            <div class="nxp-ec-settings-actions">
                <button
                    class="nxp-ec-btn"
                    type="button"
                    @click="$emit('reset')"
                    :disabled="saving"
                >
                    {{
                        __(
                            "COM_NXPEASYCART_SETTINGS_GENERAL_CANCEL",
                            "Cancel",
                            [],
                            "settingsGeneralCancel"
                        )
                    }}
                </button>
                <button
                    class="nxp-ec-btn nxp-ec-btn--primary"
                    type="submit"
                    :disabled="saving"
                >
                    {{
                        saving
                            ? __("JPROCESSING_REQUEST", "Saving...")
                            : __(
                                  "COM_NXPEASYCART_SETTINGS_GENERAL_SAVE",
                                  "Save settings",
                                  [],
                                  "settingsGeneralSave"
                              )
                    }}
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
/**
 * GeneralTab - Store defaults and general settings.
 *
 * This is a "dumb" presentational component that receives state via props
 * and emits events to the parent for state changes. All business logic
 * (API calls, caching, etc.) remains in the parent SettingsPanel.vue.
 *
 * @since 0.3.2
 */

const props = defineProps({
    /**
     * Draft object containing general settings values.
     * This is mutated directly via v-model bindings.
     */
    draft: {
        type: Object,
        required: true,
    },
    /**
     * List of available currencies for the dropdown.
     */
    currencies: {
        type: Array,
        default: () => [],
    },
    /**
     * Translation function from parent.
     */
    translate: {
        type: Function,
        required: true,
    },
    /**
     * Loading state - disables form and shows loading message.
     */
    loading: {
        type: Boolean,
        default: false,
    },
    /**
     * Saving state - disables submit button.
     */
    saving: {
        type: Boolean,
        default: false,
    },
    /**
     * Error message to display.
     */
    error: {
        type: String,
        default: "",
    },
    /**
     * Success message to display.
     */
    message: {
        type: String,
        default: "",
    },
});

defineEmits(["refresh", "save", "reset"]);

const __ = props.translate;
</script>
