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
                            "Configure taxes, shipping, and store defaults.",
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
                :class="{ 'is-active': activeTab === 'tax' }"
                @click="activeTab = 'tax'"
            >
                {{
                    __(
                        "COM_NXPEASYCART_SETTINGS_TAB_TAX",
                        "Tax rates",
                        [],
                        "settingsTabTax"
                    )
                }}
            </button>
            <button
                type="button"
                class="nxp-ec-settings-tab"
                :class="{ 'is-active': activeTab === 'shipping' }"
                @click="activeTab = 'shipping'"
            >
                {{
                    __(
                        "COM_NXPEASYCART_SETTINGS_TAB_SHIPPING",
                        "Shipping rules",
                        [],
                        "settingsTabShipping"
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
                class="nxp-ec-settings-form"
                @submit.prevent="saveGeneral"
            >
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
                    <input
                        id="settings-base-currency"
                        class="nxp-ec-form-input nxp-ec-form-input--uppercase"
                        type="text"
                        v-model.trim="settingsDraft.baseCurrency"
                        minlength="3"
                        maxlength="3"
                        pattern="[A-Za-z]{3}"
                        required
                    />
                    <p class="nxp-ec-form-help">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_GENERAL_BASE_CURRENCY_HELP",
                                "Use ISO 4217 currency codes such as USD or EUR.",
                                [],
                                "settingsGeneralBaseCurrencyHelp"
                            )
                        }}
                    </p>
                </div>

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
                                "settingsGeneralPaymentsConfigured"
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
                                "settingsGeneralPaymentsHelp"
                            )
                        }}
                    </p>
                </div>

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

        <div v-else-if="activeTab === 'security'" class="nxp-ec-settings-panel">
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

        <div v-else-if="activeTab === 'tax'" class="nxp-ec-settings-panel">
            <header class="nxp-ec-settings-panel__header">
                <h3>
                    {{
                        __(
                            "COM_NXPEASYCART_SETTINGS_TAX_TITLE",
                            "Tax rates",
                            [],
                            "settingsTaxTitle"
                        )
                    }}
                </h3>
                <button
                    class="nxp-ec-btn"
                    type="button"
                    @click="refreshTax"
                    :disabled="taxState.loading"
                >
                    {{
                        __(
                            "COM_NXPEASYCART_COUPONS_REFRESH",
                            "Refresh",
                            [],
                            "couponsRefresh"
                        )
                    }}
                </button>
            </header>

            <div
                v-if="taxState.error"
                class="nxp-ec-admin-alert nxp-ec-admin-alert--error"
            >
                {{ taxState.error }}
            </div>

            <div v-else class="nxp-ec-settings-grid">
                <div class="nxp-ec-settings-table">
                    <table class="nxp-ec-admin-table">
                        <thead>
                            <tr>
                                <th scope="col">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_SETTINGS_TAX_COUNTRY",
                                            "Country",
                                            [],
                                            "settingsTaxCountry"
                                        )
                                    }}
                                </th>
                                <th scope="col">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_SETTINGS_TAX_REGION",
                                            "Region",
                                            [],
                                            "settingsTaxRegion"
                                        )
                                    }}
                                </th>
                                <th scope="col">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_SETTINGS_TAX_RATE",
                                            "Rate",
                                            [],
                                            "settingsTaxRate"
                                        )
                                    }}
                                </th>
                                <th scope="col">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_SETTINGS_TAX_INCLUSIVE",
                                            "Inclusive",
                                            [],
                                            "settingsTaxInclusive"
                                        )
                                    }}
                                </th>
                                <th scope="col">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_SETTINGS_TAX_PRIORITY",
                                            "Priority",
                                            [],
                                            "settingsTaxPriority"
                                        )
                                    }}
                                </th>
                                <th
                                    scope="col"
                                    class="nxp-ec-admin-table__actions"
                                ></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!taxState.items.length">
                                <td colspan="6">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_SETTINGS_TAX_EMPTY",
                                            "No tax rates defined.",
                                            [],
                                            "settingsTaxEmpty"
                                        )
                                    }}
                                </td>
                            </tr>
                            <tr
                                v-for="rate in taxState.items"
                                :key="rate.id"
                                :class="{
                                    'is-active': taxDraft.id === rate.id,
                                }"
                            >
                                <th scope="row">{{ rate.country }}</th>
                                <td>{{ rate.region || "—" }}</td>
                                <td>{{ (rate.rate ?? 0).toFixed(2) }}%</td>
                                <td>
                                    {{
                                        rate.inclusive
                                            ? __("JYES", "Yes")
                                            : __("JNO", "No")
                                    }}
                                </td>
                                <td>{{ rate.priority }}</td>
                                <td class="nxp-ec-admin-table__actions">
                                    <button
                                        class="nxp-ec-btn nxp-ec-btn--link nxp-ec-btn--icon"
                                        type="button"
                                        @click="editTax(rate)"
                                        :title="__('JEDIT', 'Edit')"
                                        :aria-label="__('JEDIT', 'Edit')"
                                    >
                                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                        <span class="nxp-ec-sr-only">
                                            {{ __("JEDIT", "Edit") }}
                                        </span>
                                    </button>
                                    <button
                                        class="nxp-ec-btn nxp-ec-btn--link nxp-ec-btn--danger nxp-ec-btn--icon"
                                        type="button"
                                        @click="deleteTax(rate)"
                                        :title="__('COM_NXPEASYCART_REMOVE', 'Remove')"
                                        :aria-label="__('COM_NXPEASYCART_REMOVE', 'Remove')"
                                    >
                                        <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                        <span class="nxp-ec-sr-only">
                                            {{
                                                __(
                                                    "COM_NXPEASYCART_REMOVE",
                                                    "Remove"
                                                )
                                            }}
                                        </span>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <form class="nxp-ec-settings-form" @submit.prevent="saveTax">
                    <h4>
                        {{
                            taxDraft.id
                                ? __("JEDIT", "Edit")
                                : __(
                                      "COM_NXPEASYCART_SETTINGS_TAX_ADD",
                                      "Add tax rate",
                                      [],
                                      "settingsTaxAdd"
                                  )
                        }}
                    </h4>

                    <div class="nxp-ec-form-field">
                        <label class="nxp-ec-form-label" for="tax-country">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_TAX_COUNTRY",
                                    "Country",
                                    [],
                                    "settingsTaxCountry"
                                )
                            }}
                        </label>
                        <input
                            id="tax-country"
                            class="nxp-ec-form-input"
                            type="text"
                            v-model.trim="taxDraft.country"
                            maxlength="2"
                            required
                        />
                    </div>

                    <div class="nxp-ec-form-field">
                        <label class="nxp-ec-form-label" for="tax-region">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_TAX_REGION",
                                    "Region",
                                    [],
                                    "settingsTaxRegion"
                                )
                            }}
                        </label>
                        <input
                            id="tax-region"
                            class="nxp-ec-form-input"
                            type="text"
                            v-model.trim="taxDraft.region"
                        />
                    </div>

                    <div class="nxp-ec-form-field">
                        <label class="nxp-ec-form-label" for="tax-rate">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_TAX_RATE",
                                    "Rate",
                                    [],
                                    "settingsTaxRate"
                                )
                            }}
                        </label>
                        <input
                            id="tax-rate"
                            class="nxp-ec-form-input"
                            type="number"
                            min="0"
                            step="0.01"
                            v-model.number="taxDraft.rate"
                            required
                        />
                    </div>

                    <div class="nxp-ec-form-field nxp-ec-form-field--inline">
                        <label class="nxp-ec-form-label" for="tax-inclusive">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_TAX_INCLUSIVE",
                                    "Inclusive",
                                    [],
                                    "settingsTaxInclusive"
                                )
                            }}
                        </label>
                        <input
                            id="tax-inclusive"
                            class="nxp-ec-form-checkbox"
                            type="checkbox"
                            v-model="taxDraft.inclusive"
                        />
                    </div>

                    <div class="nxp-ec-form-field">
                        <label class="nxp-ec-form-label" for="tax-priority">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_TAX_PRIORITY",
                                    "Priority",
                                    [],
                                    "settingsTaxPriority"
                                )
                            }}
                        </label>
                        <input
                            id="tax-priority"
                            class="nxp-ec-form-input"
                            type="number"
                            step="1"
                            v-model.number="taxDraft.priority"
                        />
                    </div>

                    <div class="nxp-ec-settings-actions">
                        <button
                            class="nxp-ec-btn"
                            type="button"
                            @click="resetTax"
                            :disabled="taxState.saving"
                        >
                            {{
                                __(
                                    "COM_NXPEASYCART_TAX_FORM_CANCEL",
                                    "Cancel",
                                    [],
                                    "taxFormCancel"
                                )
                            }}
                        </button>
                        <button
                            class="nxp-ec-btn nxp-ec-btn--primary"
                            type="submit"
                            :disabled="taxState.saving"
                        >
                            {{
                                taxState.saving
                                    ? __("JPROCESSING_REQUEST", "Saving…")
                                    : __(
                                          "COM_NXPEASYCART_TAX_FORM_SAVE",
                                          "Save tax rate",
                                          [],
                                          "taxFormSave"
                                      )
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div v-else-if="activeTab === 'shipping'" class="nxp-ec-settings-panel">
            <header class="nxp-ec-settings-panel__header">
                <h3>
                    {{
                        __(
                            "COM_NXPEASYCART_SETTINGS_SHIPPING_TITLE",
                            "Shipping rules",
                            [],
                            "settingsShippingTitle"
                        )
                    }}
                </h3>
                <button
                    class="nxp-ec-btn"
                    type="button"
                    @click="refreshShipping"
                    :disabled="shippingState.loading"
                >
                    {{
                        __(
                            "COM_NXPEASYCART_COUPONS_REFRESH",
                            "Refresh",
                            [],
                            "couponsRefresh"
                        )
                    }}
                </button>
            </header>

            <div
                v-if="shippingState.error"
                class="nxp-ec-admin-alert nxp-ec-admin-alert--error"
            >
                {{ shippingState.error }}
            </div>

            <div v-else class="nxp-ec-settings-grid">
                <div class="nxp-ec-settings-table">
                    <table class="nxp-ec-admin-table">
                        <thead>
                            <tr>
                                <th scope="col">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_SETTINGS_SHIPPING_NAME",
                                            "Name",
                                            [],
                                            "settingsShippingName"
                                        )
                                    }}
                                </th>
                                <th scope="col">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_SETTINGS_SHIPPING_TYPE",
                                            "Type",
                                            [],
                                            "settingsShippingType"
                                        )
                                    }}
                                </th>
                                <th scope="col">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_SETTINGS_SHIPPING_PRICE",
                                            "Price",
                                            [],
                                            "settingsShippingPrice"
                                        )
                                    }}
                                </th>
                                <th scope="col">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_SETTINGS_SHIPPING_THRESHOLD",
                                            "Threshold",
                                            [],
                                            "settingsShippingThreshold"
                                        )
                                    }}
                                </th>
                                <th scope="col">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_SETTINGS_SHIPPING_REGIONS",
                                            "Regions",
                                            [],
                                            "settingsShippingRegions"
                                        )
                                    }}
                                </th>
                                <th scope="col">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_SETTINGS_SHIPPING_ACTIVE",
                                            "Active",
                                            [],
                                            "settingsShippingActive"
                                        )
                                    }}
                                </th>
                                <th
                                    scope="col"
                                    class="nxp-ec-admin-table__actions"
                                ></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!shippingState.items.length">
                                <td colspan="7">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_SETTINGS_SHIPPING_EMPTY",
                                            "No shipping rules defined.",
                                            [],
                                            "settingsShippingEmpty"
                                        )
                                    }}
                                </td>
                            </tr>
                            <tr
                                v-for="rule in shippingState.items"
                                :key="rule.id"
                                :class="{
                                    'is-active': shippingDraft.id === rule.id,
                                }"
                            >
                                <th scope="row">{{ rule.name }}</th>
                                <td>{{ shippingTypeLabel(rule.type) }}</td>
                                <td>
                                    {{
                                        formatCurrency(
                                            rule.price_cents,
                                            baseCurrency
                                        )
                                    }}
                                </td>
                                <td>
                                    {{
                                        rule.type === "free_over"
                                            ? formatCurrency(
                                                  rule.threshold_cents,
                                                  baseCurrency
                                              )
                                            : "—"
                                    }}
                                </td>
                                <td>
                                    {{
                                        rule.regions && rule.regions.length
                                            ? rule.regions.join(", ")
                                            : __(
                                                  "COM_NXPEASYCART_SETTINGS_SHIPPING_ALL",
                                                  "All regions",
                                                  [],
                                                  "settingsShippingAll"
                                              )
                                    }}
                                </td>
                                <td>
                                    <span
                                        class="nxp-ec-badge"
                                        :class="{ 'is-active': rule.active }"
                                    >
                                        <i
                                            :class="
                                                rule.active
                                                    ? 'fa-solid fa-circle-check'
                                                    : 'fa-regular fa-circle'
                                            "
                                            aria-hidden="true"
                                        ></i>
                                        {{
                                            rule.active
                                                ? __("JYES", "Yes")
                                                : __("JNO", "No")
                                        }}
                                    </span>
                                </td>
                                <td class="nxp-ec-admin-table__actions">
                                    <button
                                        class="nxp-ec-btn nxp-ec-btn--link nxp-ec-btn--icon"
                                        type="button"
                                        @click="editShipping(rule)"
                                        :title="__('JEDIT', 'Edit')"
                                        :aria-label="__('JEDIT', 'Edit')"
                                    >
                                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                        <span class="nxp-ec-sr-only">
                                            {{ __("JEDIT", "Edit") }}
                                        </span>
                                    </button>
                                    <button
                                        class="nxp-ec-btn nxp-ec-btn--link nxp-ec-btn--danger nxp-ec-btn--icon"
                                        type="button"
                                        @click="deleteShipping(rule)"
                                        :title="__('COM_NXPEASYCART_REMOVE', 'Remove')"
                                        :aria-label="__('COM_NXPEASYCART_REMOVE', 'Remove')"
                                    >
                                        <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                        <span class="nxp-ec-sr-only">
                                            {{
                                                __(
                                                    "COM_NXPEASYCART_REMOVE",
                                                    "Remove"
                                                )
                                            }}
                                        </span>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <form class="nxp-ec-settings-form" @submit.prevent="saveShipping">
                    <h4>
                        {{
                            shippingDraft.id
                                ? __("JEDIT", "Edit")
                                : __(
                                      "COM_NXPEASYCART_SETTINGS_SHIPPING_ADD",
                                      "Add shipping rule",
                                      [],
                                      "settingsShippingAdd"
                                  )
                        }}
                    </h4>

                    <div class="nxp-ec-form-field">
                        <label class="nxp-ec-form-label" for="shipping-name">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_SHIPPING_NAME",
                                    "Name",
                                    [],
                                    "settingsShippingName"
                                )
                            }}
                        </label>
                        <input
                            id="shipping-name"
                            class="nxp-ec-form-input"
                            type="text"
                            v-model.trim="shippingDraft.name"
                            required
                        />
                    </div>

                    <div class="nxp-ec-form-field">
                        <label class="nxp-ec-form-label" for="shipping-type">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_SHIPPING_TYPE",
                                    "Type",
                                    [],
                                    "settingsShippingType"
                                )
                            }}
                        </label>
                        <select
                            id="shipping-type"
                            class="nxp-ec-form-select"
                            v-model="shippingDraft.type"
                        >
                            <option value="flat">
                                {{
                                    __(
                                        "COM_NXPEASYCART_SETTINGS_SHIPPING_TYPE_FLAT",
                                        "Flat rate",
                                        [],
                                        "settingsShippingTypeFlat"
                                    )
                                }}
                            </option>
                            <option value="free_over">
                                {{
                                    __(
                                        "COM_NXPEASYCART_SETTINGS_SHIPPING_TYPE_FREE",
                                        "Free over threshold",
                                        [],
                                        "settingsShippingTypeFree"
                                    )
                                }}
                            </option>
                        </select>
                    </div>

                    <div class="nxp-ec-form-field">
                        <label class="nxp-ec-form-label" for="shipping-price">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_SHIPPING_PRICE",
                                    "Price",
                                    [],
                                    "settingsShippingPrice"
                                )
                            }}
                        </label>
                        <input
                            id="shipping-price"
                            class="nxp-ec-form-input"
                            type="number"
                            min="0"
                            step="0.01"
                            v-model.number="shippingDraft.price"
                            required
                        />
                    </div>

                    <div
                        class="nxp-ec-form-field"
                        v-if="shippingDraft.type === 'free_over'"
                    >
                        <label class="nxp-ec-form-label" for="shipping-threshold">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_SHIPPING_THRESHOLD",
                                    "Threshold",
                                    [],
                                    "settingsShippingThreshold"
                                )
                            }}
                        </label>
                        <input
                            id="shipping-threshold"
                            class="nxp-ec-form-input"
                            type="number"
                            min="0"
                            step="0.01"
                            v-model.number="shippingDraft.threshold"
                            required
                        />
                    </div>

                    <div class="nxp-ec-form-field">
                        <label class="nxp-ec-form-label" for="shipping-regions">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_SHIPPING_REGIONS",
                                    "Regions",
                                    [],
                                    "settingsShippingRegions"
                                )
                            }}
                        </label>
                        <input
                            id="shipping-regions"
                            class="nxp-ec-form-input"
                            type="text"
                            v-model.trim="shippingDraft.regions"
                            :placeholder="
                                __(
                                    'COM_NXPEASYCART_SETTINGS_SHIPPING_REGIONS_PLACEHOLDER',
                                    'Use comma-separated ISO codes (two-letter ISO 3166‑1 alpha‑2), e.g. US,GB,FR,DE,DK,MK,BG',
                                    [],
                                    'settingsShippingRegionsPlaceholder'
                                )
                            "
                        />
                    </div>

                    <div class="nxp-ec-form-field nxp-ec-form-field--inline">
                        <label class="nxp-ec-form-label" for="shipping-active">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_SHIPPING_ACTIVE",
                                    "Active",
                                    [],
                                    "settingsShippingActive"
                                )
                            }}
                        </label>
                        <input
                            id="shipping-active"
                            class="nxp-ec-form-checkbox"
                            type="checkbox"
                            v-model="shippingDraft.active"
                        />
                    </div>

                    <div class="nxp-ec-settings-actions">
                        <button
                            class="nxp-ec-btn"
                            type="button"
                            @click="resetShipping"
                            :disabled="shippingState.saving"
                        >
                            {{
                                __(
                                    "COM_NXPEASYCART_SHIPPING_FORM_CANCEL",
                                    "Cancel",
                                    [],
                                    "shippingFormCancel"
                                )
                            }}
                        </button>
                        <button
                            class="nxp-ec-btn nxp-ec-btn--primary"
                            type="submit"
                            :disabled="shippingState.saving"
                        >
                            {{
                                shippingState.saving
                                    ? __("JPROCESSING_REQUEST", "Saving…")
                                    : __(
                                          "COM_NXPEASYCART_SHIPPING_FORM_SAVE",
                                          "Save shipping rule",
                                          [],
                                          "shippingFormSave"
                                      )
                            }}
                        </button>
                    </div>
                </form>
            </div>
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

                    <!-- Setup Guide Toggle -->
                    <div class="nxp-ec-setup-guide">
                        <button
                            type="button"
                            class="nxp-ec-setup-guide__toggle"
                            @click="stripeGuideOpen = !stripeGuideOpen"
                            :aria-expanded="stripeGuideOpen"
                        >
                            <i :class="stripeGuideOpen ? 'fa-solid fa-chevron-down' : 'fa-solid fa-chevron-right'" aria-hidden="true"></i>
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_PAYMENTS_STRIPE_SETUP_GUIDE",
                                    "Setup Guide – How to get your Stripe credentials",
                                    [],
                                    "settingsPaymentsStripeSetupGuide"
                                )
                            }}
                        </button>
                        <div v-show="stripeGuideOpen" class="nxp-ec-setup-guide__content">
                            <ol class="nxp-ec-setup-steps">
                                <li>
                                    {{ __("COM_NXPEASYCART_SETTINGS_PAYMENTS_STRIPE_STEP1", "Log in to your", [], "stripeStep1") }}
                                    <a href="https://dashboard.stripe.com/" target="_blank" rel="noopener noreferrer">
                                        {{ __("COM_NXPEASYCART_SETTINGS_PAYMENTS_STRIPE_DASHBOARD", "Stripe Dashboard", [], "stripeDashboard") }}
                                        <i class="fa-solid fa-external-link-alt" aria-hidden="true"></i>
                                    </a>
                                </li>
                                <li>
                                    {{ __("COM_NXPEASYCART_SETTINGS_PAYMENTS_STRIPE_STEP2", "Go to Developers → API keys to find your Publishable key and Secret key.", [], "stripeStep2") }}
                                </li>
                                <li>
                                    {{ __("COM_NXPEASYCART_SETTINGS_PAYMENTS_STRIPE_STEP3", "Go to Developers → Webhooks and click \"Add endpoint\".", [], "stripeStep3") }}
                                </li>
                                <li>
                                    {{ __("COM_NXPEASYCART_SETTINGS_PAYMENTS_STRIPE_STEP4", "Use this URL as your webhook endpoint:", [], "stripeStep4") }}
                                    <div class="nxp-ec-webhook-url">
                                        <code>{{ stripeWebhookUrl }}</code>
                                        <button
                                            type="button"
                                            class="nxp-ec-btn nxp-ec-btn--small"
                                            @click="copyToClipboard(stripeWebhookUrl, 'COM_NXPEASYCART_COPIED', 'Copied!')"
                                            :title="__('COM_NXPEASYCART_COPY', 'Copy', [], 'copy')"
                                        >
                                            <i class="fa-solid fa-copy" aria-hidden="true"></i>
                                            {{ __("COM_NXPEASYCART_COPY", "Copy", [], "copy") }}
                                        </button>
                                    </div>
                                </li>
                                <li>
                                    {{ __("COM_NXPEASYCART_SETTINGS_PAYMENTS_STRIPE_STEP5", "Select these events: checkout.session.completed, payment_intent.succeeded, payment_intent.payment_failed", [], "stripeStep5") }}
                                </li>
                                <li>
                                    {{ __("COM_NXPEASYCART_SETTINGS_PAYMENTS_STRIPE_STEP6", "After creating the webhook, click to reveal the \"Signing secret\" (starts with whsec_) and paste it below.", [], "stripeStep6") }}
                                </li>
                            </ol>
                        </div>
                    </div>

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
                            :placeholder="__('COM_NXPEASYCART_SETTINGS_PAYMENTS_STRIPE_PUBLISHABLE_PLACEHOLDER', 'pk_test_... or pk_live_...', [], 'stripePublishablePlaceholder')"
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
                            :placeholder="__('COM_NXPEASYCART_SETTINGS_PAYMENTS_STRIPE_SECRET_PLACEHOLDER', 'sk_test_... or sk_live_...', [], 'stripeSecretPlaceholder')"
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
                            <span class="nxp-ec-form-label__required">*</span>
                        </label>
                        <input
                            id="nxp-ec-stripe-webhook"
                            class="nxp-ec-form-input"
                            type="password"
                            v-model.trim="paymentsDraft.stripe.webhook_secret"
                            autocomplete="off"
                            :placeholder="__('COM_NXPEASYCART_SETTINGS_PAYMENTS_STRIPE_WEBHOOK_PLACEHOLDER', 'whsec_...', [], 'stripeWebhookPlaceholder')"
                        />
                        <p class="nxp-ec-form-help">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_PAYMENTS_STRIPE_WEBHOOK_REQUIRED",
                                    "Required for security. Without this, payment confirmations won't work.",
                                    [],
                                    "settingsPaymentsStripeWebhookRequired"
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
                        <p class="nxp-ec-form-help nxp-ec-form-help--info">
                            <i class="fa-solid fa-info-circle" aria-hidden="true"></i>
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_PAYMENTS_MODE_HINT",
                                    "Use Test mode with test API keys while setting up. Switch to Live mode with live keys when ready to accept real payments.",
                                    [],
                                    "settingsPaymentsModeHint"
                                )
                            }}
                        </p>
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

                    <!-- Setup Guide Toggle -->
                    <div class="nxp-ec-setup-guide">
                        <button
                            type="button"
                            class="nxp-ec-setup-guide__toggle"
                            @click="paypalGuideOpen = !paypalGuideOpen"
                            :aria-expanded="paypalGuideOpen"
                        >
                            <i :class="paypalGuideOpen ? 'fa-solid fa-chevron-down' : 'fa-solid fa-chevron-right'" aria-hidden="true"></i>
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_PAYMENTS_PAYPAL_SETUP_GUIDE",
                                    "Setup Guide – How to get your PayPal credentials",
                                    [],
                                    "settingsPaymentsPayPalSetupGuide"
                                )
                            }}
                        </button>
                        <div v-show="paypalGuideOpen" class="nxp-ec-setup-guide__content">
                            <ol class="nxp-ec-setup-steps">
                                <li>
                                    {{ __("COM_NXPEASYCART_SETTINGS_PAYMENTS_PAYPAL_STEP1", "Log in to the", [], "paypalStep1") }}
                                    <a href="https://developer.paypal.com/dashboard/" target="_blank" rel="noopener noreferrer">
                                        {{ __("COM_NXPEASYCART_SETTINGS_PAYMENTS_PAYPAL_DASHBOARD", "PayPal Developer Dashboard", [], "paypalDashboard") }}
                                        <i class="fa-solid fa-external-link-alt" aria-hidden="true"></i>
                                    </a>
                                </li>
                                <li>
                                    {{ __("COM_NXPEASYCART_SETTINGS_PAYMENTS_PAYPAL_STEP2", "Go to Apps & Credentials and create a new app (or use an existing one).", [], "paypalStep2") }}
                                </li>
                                <li>
                                    {{ __("COM_NXPEASYCART_SETTINGS_PAYMENTS_PAYPAL_STEP3", "Copy the Client ID and Secret from your app credentials.", [], "paypalStep3") }}
                                </li>
                                <li>
                                    {{ __("COM_NXPEASYCART_SETTINGS_PAYMENTS_PAYPAL_STEP4", "In your app, go to Webhooks and click \"Add Webhook\".", [], "paypalStep4") }}
                                </li>
                                <li>
                                    {{ __("COM_NXPEASYCART_SETTINGS_PAYMENTS_PAYPAL_STEP5", "Use this URL as your webhook endpoint:", [], "paypalStep5") }}
                                    <div class="nxp-ec-webhook-url">
                                        <code>{{ paypalWebhookUrl }}</code>
                                        <button
                                            type="button"
                                            class="nxp-ec-btn nxp-ec-btn--small"
                                            @click="copyToClipboard(paypalWebhookUrl, 'COM_NXPEASYCART_COPIED', 'Copied!')"
                                            :title="__('COM_NXPEASYCART_COPY', 'Copy', [], 'copy')"
                                        >
                                            <i class="fa-solid fa-copy" aria-hidden="true"></i>
                                            {{ __("COM_NXPEASYCART_COPY", "Copy", [], "copy") }}
                                        </button>
                                    </div>
                                </li>
                                <li>
                                    {{ __("COM_NXPEASYCART_SETTINGS_PAYMENTS_PAYPAL_STEP6", "Subscribe to these events: PAYMENT.CAPTURE.COMPLETED, CHECKOUT.ORDER.APPROVED", [], "paypalStep6") }}
                                </li>
                                <li>
                                    {{ __("COM_NXPEASYCART_SETTINGS_PAYMENTS_PAYPAL_STEP7", "After saving, copy the Webhook ID (found in the webhook details) and paste it below.", [], "paypalStep7") }}
                                </li>
                            </ol>
                        </div>
                    </div>

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
                            :placeholder="__('COM_NXPEASYCART_SETTINGS_PAYMENTS_PAYPAL_CLIENT_ID_PLACEHOLDER', 'AY...', [], 'paypalClientIdPlaceholder')"
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
                            :placeholder="__('COM_NXPEASYCART_SETTINGS_PAYMENTS_PAYPAL_CLIENT_SECRET_PLACEHOLDER', 'EL...', [], 'paypalClientSecretPlaceholder')"
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
                            <span class="nxp-ec-form-label__required">*</span>
                        </label>
                        <input
                            id="nxp-ec-paypal-webhook-id"
                            class="nxp-ec-form-input"
                            type="text"
                            v-model.trim="paymentsDraft.paypal.webhook_id"
                            :placeholder="__('COM_NXPEASYCART_SETTINGS_PAYMENTS_PAYPAL_WEBHOOK_PLACEHOLDER', 'WH-...', [], 'paypalWebhookPlaceholder')"
                        />
                        <p class="nxp-ec-form-help">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_PAYMENTS_PAYPAL_WEBHOOK_REQUIRED",
                                    "Required for security. Without this, payment confirmations won't work.",
                                    [],
                                    "settingsPaymentsPayPalWebhookRequired"
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
    taxState: {
        type: Object,
        required: true,
    },
    shippingState: {
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
    siteRoot: {
        type: String,
        default: "",
    },
});

const emit = defineEmits([
    "refresh-settings",
    "save-settings",
    "refresh-tax",
    "save-tax",
    "delete-tax",
    "refresh-shipping",
    "save-shipping",
    "delete-shipping",
    "refresh-payments",
    "save-payments",
]);

const __ = props.translate;
const settingsState = props.settingsState;
const taxState = props.taxState;
const shippingState = props.shippingState;
const paymentsState = props.paymentsState;

const validTabs = ["general", "security", "tax", "shipping", "payments", "visual"];
const activeTab = ref(
    validTabs.includes(props.initialTab) ? props.initialTab : "general"
);

// Setup guide toggle states
const stripeGuideOpen = ref(false);
const paypalGuideOpen = ref(false);

// Webhook URLs computed from site root
const stripeWebhookUrl = computed(() => {
    const root = props.siteRoot || (typeof window !== "undefined" ? window.location.origin : "");
    if (!root) return "";
    return `${root}/index.php?option=com_nxpeasycart&task=webhook.stripe`;
});

const paypalWebhookUrl = computed(() => {
    const root = props.siteRoot || (typeof window !== "undefined" ? window.location.origin : "");
    if (!root) return "";
    return `${root}/index.php?option=com_nxpeasycart&task=webhook.paypal`;
});

// Copy to clipboard helper
const copyToClipboard = async (text, successKey, successFallback) => {
    try {
        await navigator.clipboard.writeText(text);
        alert(__(successKey, successFallback, [], "clipboardCopied"));
    } catch (error) {
        // Fallback for older browsers
        const textarea = document.createElement("textarea");
        textarea.value = text;
        textarea.style.position = "fixed";
        textarea.style.opacity = "0";
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand("copy");
            alert(__(successKey, successFallback, [], "clipboardCopied"));
        } catch {
            alert(__("COM_NXPEASYCART_COPY_FAILED", "Failed to copy. Please copy manually.", [], "copyFailed"));
        }
        document.body.removeChild(textarea);
    }
};

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
    categoryPageSize: 12,
    categoryPaginationMode: "paged",
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

const taxDraft = reactive({
    id: null,
    country: "",
    region: "",
    rate: 0,
    inclusive: false,
    priority: 0,
});

const shippingDraft = reactive({
    id: null,
    name: "",
    type: "flat",
    price: 0,
    threshold: 0,
    regions: "",
    active: true,
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
        categoryPageSize: Number.isFinite(Number(values?.category_page_size))
            ? Number(values.category_page_size)
            : 12,
        categoryPaginationMode:
            values?.category_pagination_mode === "infinite"
                ? "infinite"
                : "paged",
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
        checkout_phone_required: settingsDraft.checkoutPhoneRequired,
        auto_send_order_emails: settingsDraft.autoSendOrderEmails,
        category_page_size: Number.isFinite(Number(settingsDraft.categoryPageSize))
            ? Number(settingsDraft.categoryPageSize)
            : 12,
        category_pagination_mode:
            settingsDraft.categoryPaginationMode === "infinite"
                ? "infinite"
                : "paged",
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
    () => settingsDraft.baseCurrency,
    (value) => {
        if (typeof value === "string") {
            const normalised = value.replace(/[^A-Za-z]/g, "").toUpperCase();

            if (normalised !== value) {
                settingsDraft.baseCurrency = normalised;
            }
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

const refreshTax = () => emit("refresh-tax");
const refreshShipping = () => emit("refresh-shipping");

const editTax = (rate) => {
    Object.assign(taxDraft, {
        id: rate.id,
        country: rate.country,
        region: rate.region,
        rate: rate.rate,
        inclusive: rate.inclusive,
        priority: rate.priority,
    });
};

const resetTax = () => {
    Object.assign(taxDraft, {
        id: null,
        country: "",
        region: "",
        rate: 0,
        inclusive: false,
        priority: 0,
    });
};

const saveTax = () => {
    emit("save-tax", {
        id: taxDraft.id || undefined,
        country: taxDraft.country,
        region: taxDraft.region,
        rate: taxDraft.rate,
        inclusive: taxDraft.inclusive,
        priority: taxDraft.priority,
    });
};

const deleteTax = (rate) => {
    const message = __(
        "COM_NXPEASYCART_SETTINGS_TAX_DELETE",
        "Delete this tax rate?",
        [],
        "settingsTaxDelete"
    );

    if (window.confirm(message)) {
        emit("delete-tax", [rate.id]);
        if (taxDraft.id === rate.id) {
            resetTax();
        }
    }
};

watch(
    () => taxState.saving,
    (saving, wasSaving) => {
        if (wasSaving && !saving && !taxState.error) {
            resetTax();
        }
    }
);

const editShipping = (rule) => {
    Object.assign(shippingDraft, {
        id: rule.id,
        name: rule.name,
        type: rule.type,
        price: rule.price ?? (rule.price_cents ?? 0) / 100,
        threshold:
            rule.threshold !== null && rule.threshold !== undefined
                ? rule.threshold
                : (rule.threshold_cents ?? 0) / 100,
        regions:
            rule.regions && rule.regions.length ? rule.regions.join(", ") : "",
        active: rule.active,
    });
};

const resetShipping = () => {
    Object.assign(shippingDraft, {
        id: null,
        name: "",
        type: "flat",
        price: 0,
        threshold: 0,
        regions: "",
        active: true,
    });
};

const saveShipping = () => {
    const payload = {
        id: shippingDraft.id || undefined,
        name: shippingDraft.name,
        type: shippingDraft.type,
        price: shippingDraft.price,
        threshold:
            shippingDraft.type === "free_over" ? shippingDraft.threshold : 0,
        regions: shippingDraft.regions,
        active: shippingDraft.active,
    };

    emit("save-shipping", payload);
};

const deleteShipping = (rule) => {
    const message = __(
        "COM_NXPEASYCART_SETTINGS_SHIPPING_DELETE",
        "Delete this shipping rule?",
        [],
        "settingsShippingDelete"
    );

    if (window.confirm(message)) {
        emit("delete-shipping", [rule.id]);
        if (shippingDraft.id === rule.id) {
            resetShipping();
        }
    }
};

watch(
    () => shippingState.saving,
    (saving, wasSaving) => {
        if (wasSaving && !saving && !shippingState.error) {
            resetShipping();
        }
    }
);

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

const shippingTypeLabel = (type) => {
    return type === "free_over"
        ? __(
              "COM_NXPEASYCART_SETTINGS_SHIPPING_TYPE_FREE",
              "Free over threshold",
              [],
              "settingsShippingTypeFree"
          )
        : __(
              "COM_NXPEASYCART_SETTINGS_SHIPPING_TYPE_FLAT",
              "Flat rate",
              [],
              "settingsShippingTypeFlat"
          );
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
    padding: 1.25rem;
    border: 1px solid var(--nxp-ec-border, #e4e7ec);
    border-radius: 0.5rem;
    background: var(--nxp-ec-surface-alt, #f9fafb);
}

.nxp-ec-settings-form--payments legend {
    padding: 0.25rem 0.75rem;
    font-size: 1rem;
    font-weight: 600;
    color: var(--nxp-ec-text, #212529);
    background: var(--nxp-ec-surface, #fff);
    border: 1px solid var(--nxp-ec-border, #e4e7ec);
    border-radius: 0.375rem;
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

/* Setup Guide Styles */
.nxp-ec-setup-guide {
    margin-bottom: 1rem;
    border: 1px solid var(--nxp-ec-border, #e4e7ec);
    border-radius: 0.5rem;
    background: var(--nxp-ec-surface, #fff);
}

.nxp-ec-setup-guide__toggle {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.75rem 1rem;
    border: none;
    background: transparent;
    color: var(--nxp-ec-primary, #4f46e5);
    font-size: 0.875rem;
    font-weight: 500;
    text-align: left;
    cursor: pointer;
    transition: background 0.2s ease;
}

.nxp-ec-setup-guide__toggle:hover {
    background: var(--nxp-ec-surface-alt, #f9fafb);
}

.nxp-ec-setup-guide__toggle i {
    font-size: 0.75rem;
    transition: transform 0.2s ease;
}

.nxp-ec-setup-guide__content {
    padding: 0 1rem 1rem;
    border-top: 1px solid var(--nxp-ec-border, #e4e7ec);
    background: var(--nxp-ec-surface-alt, #f9fafb);
}

.nxp-ec-setup-steps {
    margin: 1rem 0 0;
    padding-left: 1.25rem;
    font-size: 0.875rem;
    line-height: 1.6;
    color: var(--nxp-ec-text, #212529);
}

.nxp-ec-setup-steps li {
    margin-bottom: 0.75rem;
}

.nxp-ec-setup-steps li:last-child {
    margin-bottom: 0;
}

.nxp-ec-setup-steps a {
    color: var(--nxp-ec-primary, #4f46e5);
    text-decoration: none;
    font-weight: 500;
}

.nxp-ec-setup-steps a:hover {
    text-decoration: underline;
}

.nxp-ec-setup-steps a i {
    margin-left: 0.25rem;
    font-size: 0.75rem;
}

/* Webhook URL Display */
.nxp-ec-webhook-url {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.5rem;
    padding: 0.5rem 0.75rem;
    background: var(--nxp-ec-surface, #fff);
    border: 1px solid var(--nxp-ec-border, #e4e7ec);
    border-radius: 0.375rem;
}

.nxp-ec-webhook-url code {
    flex: 1;
    font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
    font-size: 0.8rem;
    color: var(--nxp-ec-text, #212529);
    word-break: break-all;
}

.nxp-ec-btn--small {
    padding: 0.375rem 0.625rem;
    font-size: 0.75rem;
    white-space: nowrap;
}

.nxp-ec-btn--small i {
    margin-right: 0.25rem;
}

/* Required field indicator */
.nxp-ec-form-label__required {
    color: #dc2626;
    margin-left: 0.125rem;
}

/* Info help text variant */
.nxp-ec-form-help--info {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    margin-top: 0.5rem;
    padding: 0.75rem;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 0.375rem;
    color: #1e40af;
    font-size: 0.8rem;
    line-height: 1.5;
}

.nxp-ec-form-help--info i {
    flex-shrink: 0;
    margin-top: 0.125rem;
}

@media (max-width: 768px) {
    .nxp-ec-webhook-url {
        flex-direction: column;
        align-items: stretch;
    }

    .nxp-ec-webhook-url code {
        padding: 0.25rem 0;
    }

    .nxp-ec-webhook-url .nxp-ec-btn--small {
        align-self: flex-start;
    }
}
</style>
