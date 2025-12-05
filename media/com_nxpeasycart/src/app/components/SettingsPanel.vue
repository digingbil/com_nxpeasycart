<template>
    <section class="nxp-ec-admin-panel nxp-ec-admin-panel--settings">
        <header class="nxp-ec-admin-panel__header">
            <div>
                <h2 class="nxp-ec-admin-panel__title">
                    {{
                        __(
                            "COM_NXPEASYCART_MENU_SETTINGS",
                            "Settings",
                            [],
                            "settingsPanelTitle"
                        )
                    }}
                </h2>
                <p class="nxp-ec-admin-panel__lead">
                    {{
                        __(
                            "COM_NXPEASYCART_SETTINGS_LEAD",
                            "Configure payments, security, and store defaults.",
                            [],
                            "settingsPanelLead"
                        )
                    }}
                </p>
            </div>
        </header>

        <nav class="nxp-ec-settings-tabs">
            <button
                type="button"
                class="nxp-ec-settings-tab"
                :class="{ 'is-active': activeTab === 'general' }"
                @click="activeTab = 'general'"
            >
                {{
                    __(
                        "COM_NXPEASYCART_SETTINGS_TAB_GENERAL",
                        "General",
                        [],
                        "settingsTabGeneral"
                    )
                }}
            </button>
            <button
                v-if="settingsDraft.showAdvancedMode"
                type="button"
                class="nxp-ec-settings-tab"
                :class="{ 'is-active': activeTab === 'security' }"
                @click="activeTab = 'security'"
            >
                {{
                    __(
                        "COM_NXPEASYCART_SETTINGS_TAB_SECURITY",
                        "Security",
                        [],
                        "settingsTabSecurity"
                    )
                }}
            </button>
            <button
                type="button"
                class="nxp-ec-settings-tab"
                :class="{ 'is-active': activeTab === 'payments' }"
                @click="activeTab = 'payments'"
            >
                {{
                    __(
                        "COM_NXPEASYCART_SETTINGS_TAB_PAYMENTS",
                        "Payments",
                        [],
                        "settingsTabPayments"
                    )
                }}
            </button>
            <button
                type="button"
                class="nxp-ec-settings-tab"
                :class="{ 'is-active': activeTab === 'visual' }"
                @click="activeTab = 'visual'"
            >
                {{
                    __(
                        "COM_NXPEASYCART_SETTINGS_TAB_VISUAL",
                        "Visual",
                        [],
                        "settingsTabVisual"
                    )
                }}
            </button>
        </nav>

        <div v-if="activeTab === 'general'" class="nxp-ec-settings-panel">
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
                    @click="refreshGeneral"
                    :disabled="settingsState.loading"
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
                v-if="settingsState.error"
                class="nxp-ec-admin-alert nxp-ec-admin-alert--error"
            >
                {{ settingsState.error }}
            </div>

            <div
                v-else-if="settingsState.loading"
                class="nxp-ec-admin-panel__loading"
            >
                {{
                    __(
                        "COM_NXPEASYCART_SETTINGS_GENERAL_LOADING",
                        "Loading settings…",
                        [],
                        "settingsGeneralLoading"
                    )
                }}
            </div>

            <form
                v-else
                class="nxp-ec-settings-form nxp-ec-settings-form--payments"
                @submit.prevent="saveGeneral"
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
                            v-model.trim="settingsDraft.storeName"
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
                            v-model.trim="settingsDraft.storeEmail"
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
                            v-model.trim="settingsDraft.storePhone"
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
                            v-model="settingsDraft.baseCurrency"
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
                            v-model="settingsDraft.checkoutPhoneRequired"
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
                            v-model.trim="settingsDraft.displayLocale"
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
                            v-model.number="settingsDraft.categoryPageSize"
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
                            v-model="settingsDraft.categoryPaginationMode"
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
                            v-model="settingsDraft.autoSendOrderEmails"
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
                            v-model="settingsDraft.staleOrderCleanupEnabled"
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
                        v-if="settingsDraft.staleOrderCleanupEnabled"
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
                            v-model.number="settingsDraft.staleOrderHours"
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
                            v-model="settingsDraft.showAdvancedMode"
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
                    v-if="settingsState.message"
                    class="nxp-ec-admin-alert nxp-ec-admin-alert--success"
                >
                    {{ settingsState.message }}
                </div>

                <div class="nxp-ec-settings-actions">
                    <button
                        class="nxp-ec-btn"
                        type="button"
                        @click="resetGeneral"
                        :disabled="settingsState.saving"
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
                        :disabled="settingsState.saving"
                    >
                        {{
                            settingsState.saving
                                ? __("JPROCESSING_REQUEST", "Saving…")
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

        <div v-else-if="activeTab === 'security' && settingsDraft.showAdvancedMode" class="nxp-ec-settings-panel">
            <header class="nxp-ec-settings-panel__header">
                <h3>
                    {{
                        __(
                            "COM_NXPEASYCART_SETTINGS_SECURITY_TITLE",
                            "Security & anti-spam",
                            [],
                            "settingsSecurityTitle"
                        )
                    }}
                </h3>
                <button
                    class="nxp-ec-btn"
                    type="button"
                    @click="refreshGeneral"
                    :disabled="settingsState.loading"
                >
                    {{
                        __(
                            "COM_NXPEASYCART_COUPONS_REFRESH",
                            "Refresh",
                            [],
                            "settingsSecurityRefresh"
                        )
                    }}
                </button>
            </header>

            <div
                v-if="settingsState.error"
                class="nxp-ec-admin-alert nxp-ec-admin-alert--error"
            >
                {{ settingsState.error }}
            </div>

            <form
                v-else
                class="nxp-ec-settings-form nxp-ec-settings-form--security"
                @submit.prevent="saveSecurity"
            >
                <p class="nxp-ec-form-help">
                    {{
                        __(
                            "COM_NXPEASYCART_SETTINGS_SECURITY_LEAD",
                            "Tighten checkout and offline payment limits to reduce bot spam. Set a value to 0 to disable a limit.",
                            [],
                            "settingsSecurityLead"
                        )
                    }}
                </p>

                <div class="nxp-ec-form-grid">
                    <div class="nxp-ec-form-field">
                        <label class="nxp-ec-form-label" for="settings-checkout-window">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_SECURITY_CHECKOUT_WINDOW",
                                    "Checkout window (minutes)",
                                    [],
                                    "settingsSecurityCheckoutWindow"
                                )
                            }}
                        </label>
                        <input
                            id="settings-checkout-window"
                            class="nxp-ec-form-input"
                            type="number"
                            min="0"
                            step="1"
                            v-model.number="securityDraft.checkoutWindowMinutes"
                        />
                        <p class="nxp-ec-form-help">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_SECURITY_CHECKOUT_WINDOW_HELP",
                                    "Applies to all gateways; counts reset after this window.",
                                    [],
                                    "settingsSecurityCheckoutWindowHelp"
                                )
                            }}
                        </p>
                    </div>
                    <div class="nxp-ec-form-field">
                        <label class="nxp-ec-form-label" for="settings-offline-window">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_SECURITY_OFFLINE_WINDOW",
                                    "Offline payments window (minutes)",
                                    [],
                                    "settingsSecurityOfflineWindow"
                                )
                            }}
                        </label>
                        <input
                            id="settings-offline-window"
                            class="nxp-ec-form-input"
                            type="number"
                            min="0"
                            step="1"
                            v-model.number="securityDraft.offlineWindowMinutes"
                        />
                        <p class="nxp-ec-form-help">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_SECURITY_OFFLINE_WINDOW_HELP",
                                    "Used for COD and bank transfer spam protection.",
                                    [],
                                    "settingsSecurityOfflineWindowHelp"
                                )
                            }}
                        </p>
                    </div>
                </div>

                <div class="nxp-ec-form-grid">
                    <div class="nxp-ec-form-field">
                        <label class="nxp-ec-form-label" for="settings-checkout-ip-limit">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_SECURITY_CHECKOUT_IP_LIMIT",
                                    "Checkout attempts per IP",
                                    [],
                                    "settingsSecurityCheckoutIpLimit"
                                )
                            }}
                        </label>
                        <input
                            id="settings-checkout-ip-limit"
                            class="nxp-ec-form-input"
                            type="number"
                            min="0"
                            step="1"
                            v-model.number="securityDraft.checkoutIpLimit"
                        />
                    </div>
                    <div class="nxp-ec-form-field">
                        <label class="nxp-ec-form-label" for="settings-checkout-email-limit">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_SECURITY_CHECKOUT_EMAIL_LIMIT",
                                    "Checkout attempts per email",
                                    [],
                                    "settingsSecurityCheckoutEmailLimit"
                                )
                            }}
                        </label>
                        <input
                            id="settings-checkout-email-limit"
                            class="nxp-ec-form-input"
                            type="number"
                            min="0"
                            step="1"
                            v-model.number="securityDraft.checkoutEmailLimit"
                        />
                    </div>
                    <div class="nxp-ec-form-field">
                        <label class="nxp-ec-form-label" for="settings-checkout-session-limit">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_SECURITY_CHECKOUT_SESSION_LIMIT",
                                    "Checkout attempts per session",
                                    [],
                                    "settingsSecurityCheckoutSessionLimit"
                                )
                            }}
                        </label>
                        <input
                            id="settings-checkout-session-limit"
                            class="nxp-ec-form-input"
                            type="number"
                            min="0"
                            step="1"
                            v-model.number="securityDraft.checkoutSessionLimit"
                        />
                    </div>
                </div>

                <div class="nxp-ec-form-grid">
                    <div class="nxp-ec-form-field">
                        <label class="nxp-ec-form-label" for="settings-offline-ip-limit">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_SECURITY_OFFLINE_IP_LIMIT",
                                    "Offline attempts per IP",
                                    [],
                                    "settingsSecurityOfflineIpLimit"
                                )
                            }}
                        </label>
                        <input
                            id="settings-offline-ip-limit"
                            class="nxp-ec-form-input"
                            type="number"
                            min="0"
                            step="1"
                            v-model.number="securityDraft.offlineIpLimit"
                        />
                    </div>
                    <div class="nxp-ec-form-field">
                        <label class="nxp-ec-form-label" for="settings-offline-email-limit">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_SECURITY_OFFLINE_EMAIL_LIMIT",
                                    "Offline attempts per email",
                                    [],
                                    "settingsSecurityOfflineEmailLimit"
                                )
                            }}
                        </label>
                        <input
                            id="settings-offline-email-limit"
                            class="nxp-ec-form-input"
                            type="number"
                            min="0"
                            step="1"
                            v-model.number="securityDraft.offlineEmailLimit"
                        />
                    </div>
                </div>

                <div
                    v-if="settingsState.message"
                    class="nxp-ec-admin-alert nxp-ec-admin-alert--success"
                >
                    {{ settingsState.message }}
                </div>

                <div class="nxp-ec-settings-actions">
                    <button
                        class="nxp-ec-btn"
                        type="button"
                        @click="resetSecurity"
                        :disabled="settingsState.saving"
                    >
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_GENERAL_CANCEL",
                                "Cancel",
                                [],
                                "settingsSecurityCancel"
                            )
                        }}
                    </button>
                    <button
                        class="nxp-ec-btn nxp-ec-btn--primary"
                        type="submit"
                        :disabled="settingsState.saving"
                    >
                        {{
                            settingsState.saving
                                ? __("JPROCESSING_REQUEST", "Saving…")
                                : __(
                                      "COM_NXPEASYCART_SETTINGS_SECURITY_SAVE",
                                      "Save security",
                                      [],
                                      "settingsSecuritySave"
                                  )
                        }}
                    </button>
                </div>
            </form>
        </div>

        <div v-else-if="activeTab === 'payments'" class="nxp-ec-settings-panel">
            <header class="nxp-ec-settings-panel__header">
                <h3>
                    {{
                        __(
                            "COM_NXPEASYCART_SETTINGS_PAYMENTS_TITLE",
                            "Payment gateways",
                            [],
                            "settingsPaymentsTitle"
                        )
                    }}
                </h3>
                <div class="nxp-ec-settings-actions">
                    <button
                        class="nxp-ec-btn"
                        type="button"
                        @click="refreshPayments"
                        :disabled="paymentsState.loading"
                    >
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_PAYMENTS_REFRESH",
                                "Refresh",
                                [],
                                "settingsPaymentsRefresh"
                            )
                        }}
                    </button>
                </div>
            </header>

            <div
                v-if="paymentsState.error"
                class="nxp-ec-admin-alert nxp-ec-admin-alert--error"
            >
                {{ paymentsState.error }}
            </div>

            <div
                v-else-if="paymentsState.loading"
                class="nxp-ec-admin-panel__loading"
            >
                {{
                    __(
                        "COM_NXPEASYCART_SETTINGS_PAYMENTS_LOADING",
                        "Loading payment configuration…",
                        [],
                        "settingsPaymentsLoading"
                    )
                }}
            </div>

            <form
                v-else
                class="nxp-ec-settings-form nxp-ec-settings-form--payments"
                @submit.prevent="savePayments"
            >
                <fieldset>
                    <legend>
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_PAYMENTS_STRIPE",
                                "Stripe",
                                [],
                                "settingsPaymentsStripe"
                            )
                        }}
                    </legend>
                    <div class="nxp-ec-form-field">
                        <label
                            class="nxp-ec-form-label"
                            for="nxp-ec-stripe-publishable"
                        >
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_PAYMENTS_STRIPE_PUBLISHABLE",
                                    "Publishable key",
                                    [],
                                    "settingsPaymentsStripePublishable"
                                )
                            }}
                        </label>
                        <input
                            id="nxp-ec-stripe-publishable"
                            class="nxp-ec-form-input"
                            type="text"
                            v-model.trim="paymentsDraft.stripe.publishable_key"
                        />
                    </div>

                    <div class="nxp-ec-form-field">
                        <label class="nxp-ec-form-label" for="nxp-ec-stripe-secret">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_PAYMENTS_STRIPE_SECRET",
                                    "Secret key",
                                    [],
                                    "settingsPaymentsStripeSecret"
                                )
                            }}
                        </label>
                        <input
                            id="nxp-ec-stripe-secret"
                            class="nxp-ec-form-input"
                            type="password"
                            v-model.trim="paymentsDraft.stripe.secret_key"
                            autocomplete="off"
                        />
                    </div>

                    <div class="nxp-ec-form-field">
                        <label class="nxp-ec-form-label" for="nxp-ec-stripe-webhook">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_PAYMENTS_STRIPE_WEBHOOK",
                                    "Webhook secret",
                                    [],
                                    "settingsPaymentsStripeWebhook"
                                )
                            }}
                        </label>
                        <input
                            id="nxp-ec-stripe-webhook"
                            class="nxp-ec-form-input"
                            type="password"
                            v-model.trim="paymentsDraft.stripe.webhook_secret"
                            autocomplete="off"
                        />
                        <p class="nxp-ec-form-help">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_PAYMENTS_STRIPE_HELP",
                                    "Copy the signing secret from your Stripe webhook configuration.",
                                    [],
                                    "settingsPaymentsStripeHelp"
                                )
                            }}
                        </p>
                    </div>

                    <div class="nxp-ec-form-field">
                        <label class="nxp-ec-form-label" for="nxp-ec-stripe-mode">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_PAYMENTS_MODE",
                                    "Mode",
                                    [],
                                    "settingsPaymentsMode"
                                )
                            }}
                        </label>
                        <select
                            id="nxp-ec-stripe-mode"
                            class="nxp-ec-form-input"
                            v-model="paymentsDraft.stripe.mode"
                        >
                            <option value="test">
                                {{
                                    __(
                                        "COM_NXPEASYCART_SETTINGS_PAYMENTS_MODE_TEST",
                                        "Test",
                                        [],
                                        "settingsPaymentsModeTest"
                                    )
                                }}
                            </option>
                            <option value="live">
                                {{
                                    __(
                                        "COM_NXPEASYCART_SETTINGS_PAYMENTS_MODE_LIVE",
                                        "Live",
                                        [],
                                        "settingsPaymentsModeLive"
                                    )
                                }}
                            </option>
                        </select>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_PAYMENTS_PAYPAL",
                                "PayPal",
                                [],
                                "settingsPaymentsPayPal"
                            )
                        }}
                    </legend>
                    <div class="nxp-ec-form-field">
                        <label
                            class="nxp-ec-form-label"
                            for="nxp-ec-paypal-client-id"
                        >
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_PAYMENTS_PAYPAL_CLIENT_ID",
                                    "Client ID",
                                    [],
                                    "settingsPaymentsPayPalClientId"
                                )
                            }}
                        </label>
                        <input
                            id="nxp-ec-paypal-client-id"
                            class="nxp-ec-form-input"
                            type="text"
                            v-model.trim="paymentsDraft.paypal.client_id"
                        />
                    </div>

                    <div class="nxp-ec-form-field">
                        <label
                            class="nxp-ec-form-label"
                            for="nxp-ec-paypal-client-secret"
                        >
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_PAYMENTS_PAYPAL_CLIENT_SECRET",
                                    "Client secret",
                                    [],
                                    "settingsPaymentsPayPalClientSecret"
                                )
                            }}
                        </label>
                        <input
                            id="nxp-ec-paypal-client-secret"
                            class="nxp-ec-form-input"
                            type="password"
                            v-model.trim="paymentsDraft.paypal.client_secret"
                            autocomplete="off"
                        />
                    </div>

                    <div class="nxp-ec-form-field">
                        <label
                            class="nxp-ec-form-label"
                            for="nxp-ec-paypal-webhook-id"
                        >
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_PAYMENTS_PAYPAL_WEBHOOK",
                                    "Webhook ID",
                                    [],
                                    "settingsPaymentsPayPalWebhook"
                                )
                            }}
                        </label>
                        <input
                            id="nxp-ec-paypal-webhook-id"
                            class="nxp-ec-form-input"
                            type="text"
                            v-model.trim="paymentsDraft.paypal.webhook_id"
                        />
                        <p class="nxp-ec-form-help">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_PAYMENTS_PAYPAL_HELP",
                                    "Use the webhook ID from your PayPal app to verify notifications.",
                                    [],
                                    "settingsPaymentsPayPalHelp"
                                )
                            }}
                        </p>
                    </div>

                    <div class="nxp-ec-form-field">
                        <label class="nxp-ec-form-label" for="nxp-ec-paypal-mode">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_PAYMENTS_MODE",
                                    "Mode",
                                    [],
                                    "settingsPaymentsMode"
                                )
                            }}
                        </label>
                        <select
                            id="nxp-ec-paypal-mode"
                            class="nxp-ec-form-input"
                            v-model="paymentsDraft.paypal.mode"
                        >
                            <option value="sandbox">
                                {{
                                    __(
                                        "COM_NXPEASYCART_SETTINGS_PAYMENTS_MODE_SANDBOX",
                                        "Sandbox",
                                        [],
                                        "settingsPaymentsModeSandbox"
                                    )
                                }}
                            </option>
                            <option value="live">
                                {{
                                    __(
                                        "COM_NXPEASYCART_SETTINGS_PAYMENTS_MODE_LIVE",
                                        "Live",
                                        [],
                                        "settingsPaymentsModeLive"
                                    )
                                }}
                            </option>
                        </select>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_PAYMENTS_COD",
                                "Cash on delivery",
                                [],
                                "settingsPaymentsCod"
                            )
                        }}
                    </legend>
                    <div class="nxp-ec-form-field nxp-ec-form-field--inline">
                        <label class="nxp-ec-form-label" for="nxp-ec-cod-enabled">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_PAYMENTS_COD_ENABLED",
                                    "Enable cash on delivery",
                                    [],
                                    "settingsPaymentsCodEnabled"
                                )
                            }}
                        </label>
                        <input
                            id="nxp-ec-cod-enabled"
                            type="checkbox"
                            v-model="paymentsDraft.cod.enabled"
                        />
                    </div>
                    <div class="nxp-ec-form-field">
                        <label class="nxp-ec-form-label" for="nxp-ec-cod-label">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_PAYMENTS_COD_LABEL",
                                    "Checkout label",
                                    [],
                                    "settingsPaymentsCodLabel"
                                )
                            }}
                        </label>
                        <input
                            id="nxp-ec-cod-label"
                            class="nxp-ec-form-input"
                            type="text"
                            v-model.trim="paymentsDraft.cod.label"
                            placeholder="Cash on delivery"
                        />
                    </div>
                </fieldset>

                <fieldset>
                    <legend>
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_PAYMENTS_BANK_TRANSFER",
                                "Bank transfer",
                                [],
                                "settingsPaymentsBankTransfer"
                            )
                        }}
                    </legend>
                    <div class="nxp-ec-form-field nxp-ec-form-field--inline">
                        <label class="nxp-ec-form-label" for="nxp-ec-bank-transfer-enabled">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_PAYMENTS_BANK_TRANSFER_ENABLED",
                                    "Enable bank transfer",
                                    [],
                                    "settingsPaymentsBankTransferEnabled"
                                )
                            }}
                        </label>
                        <input
                            id="nxp-ec-bank-transfer-enabled"
                            type="checkbox"
                            v-model="paymentsDraft.bank_transfer.enabled"
                        />
                    </div>
                    <div class="nxp-ec-form-field">
                        <label class="nxp-ec-form-label" for="nxp-ec-bank-transfer-label">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_PAYMENTS_BANK_TRANSFER_LABEL",
                                    "Checkout label",
                                    [],
                                    "settingsPaymentsBankTransferLabel"
                                )
                            }}
                        </label>
                        <input
                            id="nxp-ec-bank-transfer-label"
                            class="nxp-ec-form-input"
                            type="text"
                            v-model.trim="paymentsDraft.bank_transfer.label"
                            placeholder="Bank transfer"
                        />
                    </div>
                    <div class="nxp-ec-form-field">
                        <label class="nxp-ec-form-label" for="nxp-ec-bank-transfer-instructions">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_PAYMENTS_BANK_TRANSFER_INSTRUCTIONS",
                                    "Payment instructions",
                                    [],
                                    "settingsPaymentsBankTransferInstructions"
                                )
                            }}
                        </label>
                        <textarea
                            id="nxp-ec-bank-transfer-instructions"
                            class="nxp-ec-form-input"
                            rows="4"
                            v-model="paymentsDraft.bank_transfer.instructions"
                            :placeholder="__(
                                'COM_NXPEASYCART_SETTINGS_PAYMENTS_BANK_TRANSFER_INSTRUCTIONS_PLACEHOLDER',
                                'Share how to complete the transfer and include the order number reference.',
                                [],
                                'settingsPaymentsBankTransferInstructionsPlaceholder'
                            )"
                        ></textarea>
                        <p class="nxp-ec-form-help">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_PAYMENTS_BANK_TRANSFER_INSTRUCTIONS_HELP",
                                    "Customers will see this in the checkout email alongside their invoice.",
                                    [],
                                    "settingsPaymentsBankTransferInstructionsHelp"
                                )
                            }}
                        </p>
                    </div>
                    <div class="nxp-ec-form-grid">
                        <div class="nxp-ec-form-field">
                            <label class="nxp-ec-form-label" for="nxp-ec-bank-transfer-account-name">
                                {{
                                    __(
                                        "COM_NXPEASYCART_SETTINGS_PAYMENTS_BANK_TRANSFER_ACCOUNT_NAME",
                                        "Account name",
                                        [],
                                        "settingsPaymentsBankTransferAccountName"
                                    )
                                }}
                            </label>
                            <input
                                id="nxp-ec-bank-transfer-account-name"
                                class="nxp-ec-form-input"
                                type="text"
                                v-model.trim="paymentsDraft.bank_transfer.account_name"
                            />
                        </div>
                        <div class="nxp-ec-form-field">
                            <label class="nxp-ec-form-label" for="nxp-ec-bank-transfer-iban">
                                {{
                                    __(
                                        "COM_NXPEASYCART_SETTINGS_PAYMENTS_BANK_TRANSFER_IBAN",
                                        "IBAN",
                                        [],
                                        "settingsPaymentsBankTransferIban"
                                    )
                                }}
                            </label>
                            <input
                                id="nxp-ec-bank-transfer-iban"
                                class="nxp-ec-form-input"
                                type="text"
                                v-model.trim="paymentsDraft.bank_transfer.iban"
                                maxlength="34"
                            />
                        </div>
                        <div class="nxp-ec-form-field">
                            <label class="nxp-ec-form-label" for="nxp-ec-bank-transfer-bic">
                                {{
                                    __(
                                        "COM_NXPEASYCART_SETTINGS_PAYMENTS_BANK_TRANSFER_BIC",
                                        "BIC/SWIFT",
                                        [],
                                        "settingsPaymentsBankTransferBic"
                                    )
                                }}
                            </label>
                            <input
                                id="nxp-ec-bank-transfer-bic"
                                class="nxp-ec-form-input"
                                type="text"
                                v-model.trim="paymentsDraft.bank_transfer.bic"
                                maxlength="11"
                            />
                        </div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_PAYMENTS_STATUS",
                                "Status",
                                [],
                                "settingsPaymentsStatus"
                            )
                        }}
                    </legend>
                    <div class="nxp-ec-form-field nxp-ec-form-field--inline">
                        <label
                            class="nxp-ec-form-label"
                            for="settings-payments-configured"
                        >
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_GENERAL_PAYMENTS_CONFIGURED",
                                    "Payments configured",
                                    [],
                                    "settingsPaymentsConfigured"
                                )
                            }}
                        </label>
                        <input
                            id="settings-payments-configured"
                            class="nxp-ec-form-checkbox"
                            type="checkbox"
                            v-model="settingsDraft.paymentsConfigured"
                        />
                        <p class="nxp-ec-form-help">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_GENERAL_PAYMENTS_HELP",
                                    "Track when core payment settings are complete (used by dashboard checklist).",
                                    [],
                                    "settingsPaymentsConfiguredHelp"
                                )
                            }}
                        </p>
                    </div>
                </fieldset>

                <div
                    v-if="paymentsState.message"
                    class="nxp-ec-admin-alert nxp-ec-admin-alert--success"
                >
                    {{ paymentsState.message }}
                </div>

                <div class="nxp-ec-settings-actions">
                    <button
                        class="nxp-ec-btn"
                        type="button"
                        @click="resetPayments"
                        :disabled="paymentsState.saving"
                    >
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_GENERAL_CANCEL",
                                "Cancel",
                                [],
                                "settingsPaymentsCancel"
                            )
                        }}
                    </button>
                    <button
                        class="nxp-ec-btn nxp-ec-btn--primary"
                        type="submit"
                        :disabled="paymentsState.saving"
                    >
                        {{
                            paymentsState.saving
                                ? __("JPROCESSING_REQUEST", "Saving…")
                                : __(
                                      "COM_NXPEASYCART_SETTINGS_PAYMENTS_SAVE",
                                      "Save payments",
                                      [],
                                      "settingsPaymentsSave"
                                  )
                        }}
                    </button>
                </div>
            </form>
        </div>

        <div v-else-if="activeTab === 'visual'" class="nxp-ec-settings-panel">
            <header class="nxp-ec-settings-panel__header">
                <h3>
                    {{
                        __(
                            "COM_NXPEASYCART_SETTINGS_VISUAL_TITLE",
                            "Storefront colors",
                            [],
                            "settingsVisualTitle"
                        )
                    }}
                </h3>
                <button
                    class="nxp-ec-btn"
                    type="button"
                    @click="refreshVisual"
                    :disabled="settingsState.loading"
                >
                    {{
                        __(
                            "COM_NXPEASYCART_SETTINGS_VISUAL_REFRESH",
                            "Refresh",
                            [],
                            "settingsVisualRefresh"
                        )
                    }}
                </button>
            </header>

            <div
                v-if="settingsState.error"
                class="nxp-ec-admin-alert nxp-ec-admin-alert--error"
            >
                {{ settingsState.error }}
            </div>

            <div
                v-else-if="settingsState.loading"
                class="nxp-ec-admin-panel__loading"
            >
                {{
                    __(
                        "COM_NXPEASYCART_SETTINGS_VISUAL_LOADING",
                        "Loading visual settings…",
                        [],
                        "settingsVisualLoading"
                    )
                }}
            </div>

            <form
                v-else
                class="nxp-ec-settings-form nxp-ec-settings-form--visual"
                @submit.prevent="saveVisual"
            >
                <p class="nxp-ec-form-help">
                    {{
                        __(
                            "COM_NXPEASYCART_SETTINGS_VISUAL_HINT",
                            "Override your template's colors. Empty fields will use your current template defaults shown in the color pickers.",
                            [],
                            "settingsVisualHint"
                        )
                    }}
                </p>

                <p class="nxp-ec-form-help nxp-ec-form-help--muted">
                    {{
                        __(
                            "COM_NXPEASYCART_SETTINGS_VISUAL_ADAPTER_NOTE",
                            "The component will attempt to adapt to your current template's styling automatically. If no adapter is available for your template, these fallback colors will be used instead.",
                            [],
                            "settingsVisualAdapterNote"
                        )
                    }}
                </p>

                <div class="nxp-ec-visual-grid">
                    <div class="nxp-ec-visual-field">
                        <label class="nxp-ec-form-label" for="visual-primary-color">
                            {{
                                __(
                                    "COM_NXPEASYCART_VISUAL_PRIMARY",
                                    "Primary color",
                                    [],
                                    "visualPrimary"
                                )
                            }}
                        </label>
                        <div class="nxp-ec-color-input-group">
                            <input
                                id="visual-primary-color"
                                class="nxp-ec-color-picker"
                                type="color"
                                :value="visualDraft.primaryColor || templateDefaults.primary"
                                @input="visualDraft.primaryColor = $event.target.value"
                            />
                            <input
                                class="nxp-ec-form-input"
                                type="text"
                                v-model.trim="visualDraft.primaryColor"
                                :placeholder="templateDefaults.primary"
                                maxlength="7"
                            />
                            <button
                                type="button"
                                class="nxp-ec-btn nxp-ec-btn--link"
                                @click="visualDraft.primaryColor = ''"
                            >
                                {{
                                    __(
                                        "COM_NXPEASYCART_VISUAL_RESET",
                                        "Reset",
                                        [],
                                        "visualReset"
                                    )
                                }}
                            </button>
                        </div>
                    </div>

                    <div class="nxp-ec-visual-field">
                        <label class="nxp-ec-form-label" for="visual-text-color">
                            {{
                                __(
                                    "COM_NXPEASYCART_VISUAL_TEXT",
                                    "Text color",
                                    [],
                                    "visualText"
                                )
                            }}
                        </label>
                        <div class="nxp-ec-color-input-group">
                            <input
                                id="visual-text-color"
                                class="nxp-ec-color-picker"
                                type="color"
                                :value="visualDraft.textColor || templateDefaults.text"
                                @input="visualDraft.textColor = $event.target.value"
                            />
                            <input
                                class="nxp-ec-form-input"
                                type="text"
                                v-model.trim="visualDraft.textColor"
                                :placeholder="templateDefaults.text"
                                maxlength="7"
                            />
                            <button
                                type="button"
                                class="nxp-ec-btn nxp-ec-btn--link"
                                @click="visualDraft.textColor = ''"
                            >
                                {{ __("COM_NXPEASYCART_VISUAL_RESET", "Reset", [], "visualReset") }}
                            </button>
                        </div>
                    </div>

                    <div class="nxp-ec-visual-field">
                        <label class="nxp-ec-form-label" for="visual-surface-color">
                            {{
                                __(
                                    "COM_NXPEASYCART_VISUAL_SURFACE",
                                    "Background color",
                                    [],
                                    "visualSurface"
                                )
                            }}
                        </label>
                        <div class="nxp-ec-color-input-group">
                            <input
                                id="visual-surface-color"
                                class="nxp-ec-color-picker"
                                type="color"
                                :value="visualDraft.surfaceColor || templateDefaults.surface"
                                @input="visualDraft.surfaceColor = $event.target.value"
                            />
                            <input
                                class="nxp-ec-form-input"
                                type="text"
                                v-model.trim="visualDraft.surfaceColor"
                                :placeholder="templateDefaults.surface"
                                maxlength="7"
                            />
                            <button
                                type="button"
                                class="nxp-ec-btn nxp-ec-btn--link"
                                @click="visualDraft.surfaceColor = ''"
                            >
                                {{ __("COM_NXPEASYCART_VISUAL_RESET", "Reset", [], "visualReset") }}
                            </button>
                        </div>
                    </div>

                    <div class="nxp-ec-visual-field">
                        <label class="nxp-ec-form-label" for="visual-border-color">
                            {{
                                __(
                                    "COM_NXPEASYCART_VISUAL_BORDER",
                                    "Border color",
                                    [],
                                    "visualBorder"
                                )
                            }}
                        </label>
                        <div class="nxp-ec-color-input-group">
                            <input
                                id="visual-border-color"
                                class="nxp-ec-color-picker"
                                type="color"
                                :value="visualDraft.borderColor || templateDefaults.border"
                                @input="visualDraft.borderColor = $event.target.value"
                            />
                            <input
                                class="nxp-ec-form-input"
                                type="text"
                                v-model.trim="visualDraft.borderColor"
                                :placeholder="templateDefaults.border"
                                maxlength="7"
                            />
                            <button
                                type="button"
                                class="nxp-ec-btn nxp-ec-btn--link"
                                @click="visualDraft.borderColor = ''"
                            >
                                {{ __("COM_NXPEASYCART_VISUAL_RESET", "Reset", [], "visualReset") }}
                            </button>
                        </div>
                    </div>

                    <div class="nxp-ec-visual-field">
                        <label class="nxp-ec-form-label" for="visual-muted-color">
                            {{
                                __(
                                    "COM_NXPEASYCART_VISUAL_MUTED",
                                    "Muted text color",
                                    [],
                                    "visualMuted"
                                )
                            }}
                        </label>
                        <div class="nxp-ec-color-input-group">
                            <input
                                id="visual-muted-color"
                                class="nxp-ec-color-picker"
                                type="color"
                                :value="visualDraft.mutedColor || templateDefaults.muted"
                                @input="visualDraft.mutedColor = $event.target.value"
                            />
                            <input
                                class="nxp-ec-form-input"
                                type="text"
                                v-model.trim="visualDraft.mutedColor"
                                :placeholder="templateDefaults.muted"
                                maxlength="7"
                            />
                            <button
                                type="button"
                                class="nxp-ec-btn nxp-ec-btn--link"
                                @click="visualDraft.mutedColor = ''"
                            >
                                {{ __("COM_NXPEASYCART_VISUAL_RESET", "Reset", [], "visualReset") }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="nxp-ec-visual-preview">
                    <h4>
                        {{
                            __(
                                "COM_NXPEASYCART_VISUAL_PREVIEW",
                                "Preview",
                                [],
                                "visualPreview"
                            )
                        }}
                    </h4>
                    <div class="nxp-ec-preview-box" :style="previewStyles">
                        <button type="button" class="nxp-ec-preview-btn">
                            {{ __("COM_NXPEASYCART_VISUAL_PREVIEW_BUTTON", "Sample Button", [], "visualPreviewButton") }}
                        </button>
                        <p class="nxp-ec-preview-text">
                            {{ __("COM_NXPEASYCART_VISUAL_PREVIEW_TEXT", "Sample text in your chosen colors", [], "visualPreviewText") }}
                        </p>
                        <p class="nxp-ec-preview-muted">
                            {{ __("COM_NXPEASYCART_VISUAL_PREVIEW_MUTED", "Muted text example", [], "visualPreviewMuted") }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="settingsState.message"
                    class="nxp-ec-admin-alert nxp-ec-admin-alert--success"
                >
                    {{ settingsState.message }}
                </div>

                <div class="nxp-ec-settings-actions">
                    <button
                        class="nxp-ec-btn"
                        type="button"
                        @click="resetVisual"
                        :disabled="settingsState.saving"
                    >
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_VISUAL_CANCEL",
                                "Cancel",
                                [],
                                "settingsVisualCancel"
                            )
                        }}
                    </button>
                    <button
                        class="nxp-ec-btn nxp-ec-btn--primary"
                        type="submit"
                        :disabled="settingsState.saving"
                    >
                        {{
                            settingsState.saving
                                ? __("JPROCESSING_REQUEST", "Saving…")
                                : __(
                                      "COM_NXPEASYCART_SETTINGS_VISUAL_SAVE",
                                      "Save colors",
                                      [],
                                      "settingsVisualSave"
                                  )
                        }}
                    </button>
                </div>
            </form>
        </div>
    </section>
</template>

<script setup>
import { reactive, ref, watch, computed } from "vue";

const props = defineProps({
    settingsState: {
        type: Object,
        required: true,
    },
    paymentsState: {
        type: Object,
        default: () => ({
            loading: false,
            saving: false,
            error: "",
            message: "",
            config: {
                stripe: {},
                paypal: {},
            },
        }),
    },
    translate: {
        type: Function,
        required: true,
    },
    baseCurrency: {
        type: String,
        default: "USD",
    },
    initialTab: {
        type: String,
        default: "general",
    },
});

const emit = defineEmits([
    "refresh-settings",
    "save-settings",
    "refresh-payments",
    "save-payments",
]);

const __ = props.translate;
const settingsState = props.settingsState;
const paymentsState = props.paymentsState;

const validTabs = ["general", "security", "payments", "visual"];
const activeTab = ref(
    validTabs.includes(props.initialTab) ? props.initialTab : "general"
);

const baseCurrency = computed(() => {
    const fallback = props.baseCurrency || "USD";
    const draft =
        typeof settingsDraft.baseCurrency === "string"
            ? settingsDraft.baseCurrency.trim()
            : "";
    const stateCurrency =
        typeof settingsState?.values?.base_currency === "string"
            ? settingsState.values.base_currency.trim()
            : "";

    const value =
        draft !== "" ? draft : stateCurrency !== "" ? stateCurrency : fallback;

    return value.toUpperCase();
});

const settingsDraft = reactive({
    storeName: "",
    storeEmail: "",
    storePhone: "",
    checkoutPhoneRequired: false,
    paymentsConfigured: false,
    autoSendOrderEmails: false,
    baseCurrency: "",
    displayLocale: "",
    categoryPageSize: 12,
    categoryPaginationMode: "paged",
    staleOrderCleanupEnabled: false,
    staleOrderHours: 48,
    showAdvancedMode: false,
});

const securityDraft = reactive({
    checkoutWindowMinutes: 15,
    checkoutIpLimit: 10,
    checkoutEmailLimit: 5,
    checkoutSessionLimit: 15,
    offlineWindowMinutes: 240,
    offlineIpLimit: 10,
    offlineEmailLimit: 5,
});

const paymentsDraft = reactive({
    stripe: {
        publishable_key: "",
        secret_key: "",
        webhook_secret: "",
        mode: "test",
    },
    paypal: {
        client_id: "",
        client_secret: "",
        webhook_id: "",
        mode: "sandbox",
    },
    cod: {
        enabled: true,
        label: "Cash on delivery",
    },
    bank_transfer: {
        enabled: false,
        label: "Bank transfer",
        instructions: "",
        account_name: "",
        iban: "",
        bic: "",
    },
});

const visualDraft = reactive({
    primaryColor: "",
    textColor: "",
    surfaceColor: "",
    borderColor: "",
    mutedColor: "",
});

const templateDefaults = computed(() => {
    const defaults = settingsState.values?.visual_defaults ?? {};
    return {
        primary: defaults.primary_color || "#4f6d7a",
        text: defaults.text_color || "#1f2933",
        surface: defaults.surface_color || "#ffffff",
        border: defaults.border_color || "#e4e7ec",
        muted: defaults.muted_color || "#6b7280",
    };
});

const currencies = computed(() => {
    return settingsState.values?.currencies ?? [];
});

const previewStyles = computed(() => ({
    "--nxp-ec-color-primary": visualDraft.primaryColor || templateDefaults.value.primary,
    "--nxp-ec-color-text": visualDraft.textColor || templateDefaults.value.text,
    "--nxp-ec-color-surface": visualDraft.surfaceColor || templateDefaults.value.surface,
    "--nxp-ec-color-border": visualDraft.borderColor || templateDefaults.value.border,
    "--nxp-ec-color-muted": visualDraft.mutedColor || templateDefaults.value.muted,
}));

const applySettings = (values = {}) => {
    const store = values?.store ?? {};
    const payments = values?.payments ?? {};
    const visual = values?.visual ?? {};
    const security = values?.security?.rate_limits ?? {};

    Object.assign(settingsDraft, {
        storeName: store.name ?? "",
        storeEmail: store.email ?? "",
        storePhone: store.phone ?? "",
        checkoutPhoneRequired: Boolean(
            values?.checkout_phone_required ?? false
        ),
        paymentsConfigured: Boolean(payments.configured),
        autoSendOrderEmails: Boolean(values?.auto_send_order_emails ?? false),
        baseCurrency:
            typeof values?.base_currency === "string"
                ? values.base_currency.trim().toUpperCase()
                : (props.baseCurrency || "USD").toUpperCase(),
        displayLocale:
            typeof values?.display_locale === "string"
                ? values.display_locale.trim()
                : "",
        categoryPageSize: Number.isFinite(Number(values?.category_page_size))
            ? Number(values.category_page_size)
            : 12,
        categoryPaginationMode:
            values?.category_pagination_mode === "infinite"
                ? "infinite"
                : "paged",
        staleOrderCleanupEnabled: Boolean(values?.stale_order_cleanup_enabled ?? false),
        staleOrderHours: Number.isFinite(Number(values?.stale_order_hours))
            ? Math.max(1, Math.min(720, Number(values.stale_order_hours)))
            : 48,
        showAdvancedMode: Boolean(values?.show_advanced_mode ?? false),
    });

    Object.assign(visualDraft, {
        primaryColor: visual.primary_color ?? "",
        textColor: visual.text_color ?? "",
        surfaceColor: visual.surface_color ?? "",
        borderColor: visual.border_color ?? "",
        mutedColor: visual.muted_color ?? "",
    });

    Object.assign(securityDraft, {
        checkoutWindowMinutes: Number.isFinite(Number(security.checkout_window_minutes))
            ? Number(security.checkout_window_minutes)
            : 15,
        checkoutIpLimit: Number.isFinite(Number(security.checkout_ip_limit))
            ? Number(security.checkout_ip_limit)
            : 10,
        checkoutEmailLimit: Number.isFinite(Number(security.checkout_email_limit))
            ? Number(security.checkout_email_limit)
            : 5,
        checkoutSessionLimit: Number.isFinite(Number(security.checkout_session_limit))
            ? Number(security.checkout_session_limit)
            : 15,
        offlineWindowMinutes: Number.isFinite(Number(security.offline_window_minutes))
            ? Number(security.offline_window_minutes)
            : 240,
        offlineIpLimit: Number.isFinite(Number(security.offline_ip_limit))
            ? Number(security.offline_ip_limit)
            : 10,
        offlineEmailLimit: Number.isFinite(Number(security.offline_email_limit))
            ? Number(security.offline_email_limit)
            : 5,
    });
};

const applyPayments = (config = {}) => {
    const stripe = config.stripe ?? {};
    const paypal = config.paypal ?? {};
    const cod = config.cod ?? {};
    const bank = config.bank_transfer ?? {};

    Object.assign(paymentsDraft.stripe, {
        publishable_key: stripe.publishable_key ?? "",
        secret_key: stripe.secret_key ?? "",
        webhook_secret: stripe.webhook_secret ?? "",
        mode: stripe.mode ?? "test",
    });

    Object.assign(paymentsDraft.paypal, {
        client_id: paypal.client_id ?? "",
        client_secret: paypal.client_secret ?? "",
        webhook_id: paypal.webhook_id ?? "",
        mode: paypal.mode ?? "sandbox",
    });

    Object.assign(paymentsDraft.cod, {
        enabled:
            cod.enabled !== undefined
                ? Boolean(cod.enabled)
                : paymentsDraft.cod.enabled,
        label: cod.label ?? paymentsDraft.cod.label ?? "Cash on delivery",
    });

    Object.assign(paymentsDraft.bank_transfer, {
        enabled:
            bank.enabled !== undefined
                ? Boolean(bank.enabled)
                : paymentsDraft.bank_transfer.enabled,
        label: bank.label ?? paymentsDraft.bank_transfer.label ?? "Bank transfer",
        instructions:
            bank.instructions ?? paymentsDraft.bank_transfer.instructions ?? "",
        account_name:
            bank.account_name ?? paymentsDraft.bank_transfer.account_name ?? "",
        iban: bank.iban ?? paymentsDraft.bank_transfer.iban ?? "",
        bic: bank.bic ?? paymentsDraft.bank_transfer.bic ?? "",
    });
};

watch(
    () => settingsState.values,
    (values) => {
        applySettings(values ?? {});
    },
    { immediate: true }
);

const refreshGeneral = () => emit("refresh-settings");

const saveGeneral = () => {
    const currency = (settingsDraft.baseCurrency || "").trim().toUpperCase();
    const locale = (settingsDraft.displayLocale || "").trim();

    emit("save-settings", {
        store: {
            name: settingsDraft.storeName,
            email: settingsDraft.storeEmail,
            phone: settingsDraft.storePhone,
            base_currency: currency,
        },
        payments: {
            configured: settingsDraft.paymentsConfigured,
        },
        base_currency: currency,
        display_locale: locale,
        checkout_phone_required: settingsDraft.checkoutPhoneRequired,
        auto_send_order_emails: settingsDraft.autoSendOrderEmails,
        category_page_size: Number.isFinite(Number(settingsDraft.categoryPageSize))
            ? Number(settingsDraft.categoryPageSize)
            : 12,
        category_pagination_mode:
            settingsDraft.categoryPaginationMode === "infinite"
                ? "infinite"
                : "paged",
        stale_order_cleanup_enabled: Boolean(settingsDraft.staleOrderCleanupEnabled),
        stale_order_hours: Number.isFinite(Number(settingsDraft.staleOrderHours))
            ? Math.max(1, Math.min(720, Number(settingsDraft.staleOrderHours)))
            : 48,
        show_advanced_mode: Boolean(settingsDraft.showAdvancedMode),
    });
};

const resetGeneral = () => {
    applySettings(settingsState.values ?? {});
};

const saveSecurity = () => {
    const normalise = (value, fallback = 0) => {
        const num = Number(value);
        return Number.isFinite(num) && num >= 0 ? num : fallback;
    };

    emit("save-settings", {
        security: {
            rate_limits: {
                checkout_window_minutes: normalise(securityDraft.checkoutWindowMinutes, 15),
                checkout_ip_limit: normalise(securityDraft.checkoutIpLimit, 10),
                checkout_email_limit: normalise(securityDraft.checkoutEmailLimit, 5),
                checkout_session_limit: normalise(securityDraft.checkoutSessionLimit, 15),
                offline_window_minutes: normalise(securityDraft.offlineWindowMinutes, 240),
                offline_ip_limit: normalise(securityDraft.offlineIpLimit, 10),
                offline_email_limit: normalise(securityDraft.offlineEmailLimit, 5),
            },
        },
    });
};

const resetSecurity = () => {
    applySettings(settingsState.values ?? {});
};

watch(
    () => settingsState.saving,
    (saving, wasSaving) => {
        if (wasSaving && !saving && !settingsState.error) {
            applySettings(settingsState.values ?? {});
        }
    }
);

watch(
    () => paymentsState.config,
    (config) => {
        applyPayments(config ?? {});
    },
    { immediate: true }
);

watch(
    () => paymentsState.saving,
    (saving, wasSaving) => {
        if (wasSaving && !saving && !paymentsState.error) {
            applyPayments(paymentsState.config ?? {});
        }
    }
);

const refreshPayments = () => emit("refresh-payments");
const savePayments = () => {
    const payload = JSON.parse(JSON.stringify(paymentsDraft));
    emit("save-payments", payload);

    // Also save the paymentsConfigured flag via general settings
    emit("save-settings", {
        payments: {
            configured: settingsDraft.paymentsConfigured,
        },
    });
};
const resetPayments = () => {
    applyPayments(paymentsState.config ?? {});
};

const refreshVisual = () => emit("refresh-settings");
const saveVisual = () => {
    emit("save-settings", {
        visual: {
            primary_color: visualDraft.primaryColor,
            text_color: visualDraft.textColor,
            surface_color: visualDraft.surfaceColor,
            border_color: visualDraft.borderColor,
            muted_color: visualDraft.mutedColor,
        },
    });
};
const resetVisual = () => {
    const visual = settingsState.values?.visual ?? {};
    Object.assign(visualDraft, {
        primaryColor: visual.primary_color ?? "",
        textColor: visual.text_color ?? "",
        surfaceColor: visual.surface_color ?? "",
        borderColor: visual.border_color ?? "",
        mutedColor: visual.muted_color ?? "",
    });
};

const formatCurrency = (cents, currency) => {
    const amount = (Number(cents) || 0) / 100;
    const code = (currency || "").toUpperCase() || baseCurrency.value;

    try {
        return new Intl.NumberFormat(undefined, {
            style: "currency",
            currency: code,
        }).format(amount);
    } catch (error) {
        return `${code} ${amount.toFixed(2)}`;
    }
};
</script>

<style scoped>
.nxp-ec-settings-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    padding: 1.5rem 1.5rem 0;
}

.nxp-ec-settings-tab {
    padding: 0.5rem 1rem;
    border: 1px solid var(--nxp-ec-border, #d0d5dd);
    border-radius: 999px;
    background: var(--nxp-ec-surface, #fff);
    color: var(--nxp-ec-text, #212529);
    cursor: pointer;
    transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
}

.nxp-ec-settings-tab:hover {
    background: var(--nxp-ec-surface-alt, #f8f9fa);
}

.nxp-ec-settings-tab.is-active {
    background: var(--nxp-ec-primary-bg-solid, #4f46e5);
    color: #fff;
    border-color: var(--nxp-ec-primary-bg-solid, #4f46e5);
}

.nxp-ec-settings-panel {
    display: grid;
    gap: 1.5rem;
    padding: 1.5rem;
}

.nxp-ec-settings-panel__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 1.5rem 0;
}

.nxp-ec-settings-grid {
    display: grid;
    gap: 1.5rem;
    grid-template-columns: 2fr 1fr;
}

@media (max-width: 960px) {
    .nxp-ec-settings-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .nxp-ec-settings-tabs {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        flex-wrap: nowrap;
        margin-left: -1rem;
        margin-right: -1rem;
        padding-left: 1rem;
        padding-right: 1rem;
    }

    .nxp-ec-settings-tabs::-webkit-scrollbar {
        display: none;
    }

    .nxp-ec-settings-tab {
        white-space: nowrap;
        flex-shrink: 0;
        min-height: 44px;
    }

    .nxp-ec-settings-panel {
        padding: 1rem;
    }

    .nxp-ec-settings-panel__header {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
        padding: 1rem 1rem 0;
    }

    .nxp-ec-settings-form {
        padding: 0.75rem;
    }

    .nxp-ec-settings-actions {
        flex-direction: column;
    }

    .nxp-ec-settings-actions .nxp-ec-btn {
        width: 100%;
    }

    .nxp-ec-visual-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .nxp-ec-settings-tabs {
        margin-left: -0.75rem;
        margin-right: -0.75rem;
        padding-left: 0.75rem;
        padding-right: 0.75rem;
    }

    .nxp-ec-settings-panel {
        padding: 0.75rem;
    }

    .nxp-ec-settings-panel__header {
        padding: 0.75rem 0.75rem 0;
    }

    .nxp-ec-form-grid {
        grid-template-columns: 1fr;
    }

    .nxp-ec-color-input-group {
        flex-wrap: wrap;
    }

    .nxp-ec-color-input-group .nxp-ec-form-input {
        max-width: none;
        flex: 1;
    }
}

.nxp-ec-settings-table {
    overflow-x: auto;
}

.nxp-ec-settings-form {
    display: grid;
    gap: 1rem;
    padding: 1rem;
    border: 1px solid var(--nxp-ec-border, #e4e7ec);
    border-radius: 0.75rem;
    background: var(--nxp-ec-surface, #fff);
}

/* Payment settings form - fieldset and legend styling */
.nxp-ec-settings-form--payments fieldset {
    margin: 0;
    padding: 1rem 1.25rem 1.25rem;
    border: 1px solid var(--nxp-ec-border, #e4e7ec);
    border-radius: 0.5rem;
    background: var(--nxp-ec-surface, #fff);
}

.nxp-ec-settings-form--payments legend {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0;
    margin-bottom: 1rem;
    font-size: 0.875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--nxp-ec-text-muted, #6b7280);
    background: transparent;
    border: none;
    border-radius: 0;
}

.nxp-ec-settings-form--payments legend::after {
    content: "";
    flex: 1;
    height: 1px;
    background: var(--nxp-ec-border, #e4e7ec);
}

.nxp-ec-settings-form--payments fieldset .nxp-ec-form-field {
    margin-top: 0.75rem;
}

.nxp-ec-settings-form--payments fieldset .nxp-ec-form-field:first-of-type {
    margin-top: 0;
}

.nxp-ec-form-grid {
    display: grid;
    gap: 1rem;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.nxp-ec-settings-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

.nxp-ec-form-input--uppercase {
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.nxp-ec-form-static {
    padding: 0.5rem 0.75rem;
    border: 1px solid var(--nxp-ec-border, #e4e7ec);
    border-radius: 0.5rem;
    background: var(--nxp-ec-surface-alt, #f9fafb);
    color: var(--nxp-ec-text, #212529);
}

.nxp-ec-visual-grid {
    display: grid;
    gap: 1.5rem;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
}

.nxp-ec-visual-field {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.nxp-ec-color-input-group {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.nxp-ec-color-input-group .nxp-ec-form-input {
    max-width: 100px;
}

/* Make color input placeholders more transparent to indicate they're defaults */
.nxp-ec-color-input-group .nxp-ec-form-input::placeholder {
    color: var(--nxp-ec-text-muted, #98a2b3);
    opacity: 0.5;
    font-style: italic;
}

/* Muted variant for form help text - smaller and more subtle */
.nxp-ec-form-help--muted {
    margin-top: 0.25rem;
    font-size: 0.8rem;
    opacity: 0.8;
    line-height: 1.4;
}

.nxp-ec-color-picker {
    width: 4rem;
    height: 2.5rem;
    border: 1px solid var(--nxp-ec-input-border, #d0d5dd);
    border-radius: 0.5rem;
    cursor: pointer;
}

.nxp-ec-color-picker::-webkit-color-swatch-wrapper {
    padding: 0;
}

.nxp-ec-color-picker::-webkit-color-swatch {
    border: none;
    border-radius: 0.375rem;
}

.nxp-ec-visual-preview {
    margin-top: 2rem;
    padding: 1.5rem;
    border: 1px solid var(--nxp-ec-border, #e4e7ec);
    border-radius: 0.75rem;
    background: var(--nxp-ec-surface-alt, #f9fafb);
}

.nxp-ec-visual-preview h4 {
    margin: 0 0 1rem;
    font-size: 1rem;
    font-weight: 600;
    color: var(--nxp-ec-text, #212529);
}

.nxp-ec-preview-box {
    padding: 2rem;
    border-radius: 0.5rem;
    background: var(--nxp-ec-color-surface);
    border: 1px solid var(--nxp-ec-color-border);
}

.nxp-ec-preview-btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 0.5rem;
    background: var(--nxp-ec-color-primary);
    color: #ffffff;
    font-weight: 600;
    cursor: pointer;
}

.nxp-ec-preview-text {
    margin: 1rem 0 0.5rem;
    color: var(--nxp-ec-color-text);
    font-size: 1rem;
}

.nxp-ec-preview-muted {
    margin: 0;
    color: var(--nxp-ec-color-muted);
    font-size: 0.875rem;
}
</style>
