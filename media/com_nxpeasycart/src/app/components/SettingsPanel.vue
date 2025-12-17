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
                type="button"
                class="nxp-ec-settings-tab"
                :class="{ 'is-active': activeTab === 'digital' }"
                @click="activeTab = 'digital'"
            >
                {{
                    __(
                        "COM_NXPEASYCART_SETTINGS_DIGITAL_TITLE",
                        "Digital"
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

        <!-- General Tab -->
        <GeneralTab
            v-if="activeTab === 'general'"
            :draft="settingsDraft"
            :currencies="currencies"
            :translate="__"
            :loading="settingsState.loading"
            :saving="settingsState.saving"
            :error="settingsState.error"
            :message="settingsState.message"
            @refresh="refreshGeneral"
            @save="saveGeneral"
            @reset="resetGeneral"
        />

        <!-- Digital Tab -->
        <DigitalTab
            v-else-if="activeTab === 'digital'"
            :draft="settingsDraft"
            :file-type-categories="fileTypeCategories"
            :translate="__"
            :loading="settingsState.loading"
            :saving="settingsState.saving"
            :error="settingsState.error"
            @refresh="refreshGeneral"
            @save="saveDigital"
            @reset="resetDigital"
        />

        <!-- Security Tab (Advanced Mode only) -->
        <SecurityTab
            v-else-if="activeTab === 'security' && settingsDraft.showAdvancedMode"
            :draft="securityDraft"
            :translate="__"
            :loading="settingsState.loading"
            :saving="settingsState.saving"
            :error="settingsState.error"
            :message="settingsState.message"
            @refresh="refreshGeneral"
            @save="saveSecurity"
            @reset="resetSecurity"
        />

        <!-- Payments Tab -->
        <PaymentsTab
            v-else-if="activeTab === 'payments'"
            :draft="paymentsDraft"
            :settings-draft="settingsDraft"
            :site-url="siteUrl"
            :translate="__"
            :loading="paymentsState.loading"
            :saving="paymentsState.saving"
            :error="paymentsState.error"
            :message="paymentsState.message"
            @refresh="refreshPayments"
            @save="savePayments"
            @reset="resetPayments"
        />

        <!-- Visual Tab -->
        <VisualTab
            v-else-if="activeTab === 'visual'"
            :draft="visualDraft"
            :template-defaults="templateDefaults"
            :translate="__"
            :loading="settingsState.loading"
            :saving="settingsState.saving"
            :error="settingsState.error"
            :message="settingsState.message"
            @refresh="refreshVisual"
            @save="saveVisual"
            @reset="resetVisual"
        />
    </section>
</template>

<script setup>
/**
 * SettingsPanel - Coordinator component for store settings.
 *
 * Orchestrates the settings tabs (General, Digital, Security, Payments, Visual)
 * and manages draft state synchronization with the composables.
 *
 * Refactored in v0.3.2 to use extracted tab components for maintainability.
 * Original monolithic component was ~3150 lines; now ~600 lines as coordinator.
 *
 * @since 0.1.0
 * @refactored 0.3.2
 */
import { reactive, ref, watch, computed } from "vue";
import GeneralTab from "./settings/GeneralTab.vue";
import DigitalTab from "./settings/DigitalTab.vue";
import SecurityTab from "./settings/SecurityTab.vue";
import PaymentsTab from "./settings/PaymentsTab.vue";
import VisualTab from "./settings/VisualTab.vue";

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

const validTabs = ["general", "digital", "security", "payments", "visual"];
const activeTab = ref(
    validTabs.includes(props.initialTab) ? props.initialTab : "general"
);

// ============================================================================
// Draft State Objects
// ============================================================================

const settingsDraft = reactive({
    storeName: "",
    storeEmail: "",
    storePhone: "",
    checkoutPhoneRequired: false,
    paymentsConfigured: false,
    autoSendOrderEmails: false,
    baseCurrency: props.baseCurrency || "USD",
    displayLocale: "",
    categoryPageSize: 12,
    categoryPaginationMode: "paged",
    staleOrderCleanupEnabled: false,
    staleOrderHours: 48,
    showAdvancedMode: false,
    digitalMaxDownloads: 5,
    digitalExpiryDays: 30,
    digitalStoragePath: "",
    digitalAutoFulfill: true,
    digitalMaxFileSize: 200,
    digitalAllowedExtensions: [],
    digitalCustomExtensions: "",
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

// File type categories from API or defaults
const fileTypeCategories = ref({
    archives: ["zip", "rar", "7z", "tar", "gz", "tgz"],
    audio: ["mp3", "wav", "flac"],
    video: ["mp4", "webm", "mov", "avi", "mkv"],
    images: ["jpg", "jpeg", "png", "gif", "svg", "webp", "avif"],
    documents: ["pdf", "txt", "rtf", "doc", "docx", "xls", "xlsx", "ppt", "pptx", "odt", "ods", "odp", "csv"],
    ebooks: ["epub", "mobi"],
    installers: ["exe", "msi", "deb", "rpm", "dmg", "app", "pkg", "apk", "ipa"],
});

// ============================================================================
// Computed Properties
// ============================================================================

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

    return draft !== "" ? draft : stateCurrency !== "" ? stateCurrency : fallback;
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

const siteUrl = computed(() => {
    return window.Joomla?.getOptions?.("system.paths")?.root || window.location.origin;
});

// ============================================================================
// Helper Functions
// ============================================================================

const getAllPredefinedExtensions = () => {
    return Object.values(fileTypeCategories.value).flat();
};

const getInitialAllowedExtensions = (values) => {
    const allowed = values?.digital?.allowed_extensions;
    if (allowed === null || allowed === undefined) {
        return getAllPredefinedExtensions();
    }
    if (Array.isArray(allowed)) {
        return [...allowed];
    }
    return getAllPredefinedExtensions();
};

// ============================================================================
// State Synchronization
// ============================================================================

const applySettings = (values = {}) => {
    const store = values?.store ?? {};
    const payments = values?.payments ?? {};
    const visual = values?.visual ?? {};
    const security = values?.security?.rate_limits ?? {};

    Object.assign(settingsDraft, {
        storeName: store.name ?? "",
        storeEmail: store.email ?? "",
        storePhone: store.phone ?? "",
        checkoutPhoneRequired: Boolean(values?.checkout_phone_required ?? false),
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
        digitalMaxDownloads: Number.isFinite(Number(values?.digital_download_max))
            ? Math.max(0, Number(values.digital_download_max))
            : 5,
        digitalExpiryDays: Number.isFinite(Number(values?.digital_download_expiry))
            ? Math.max(0, Number(values.digital_download_expiry))
            : 30,
        digitalStoragePath:
            typeof values?.digital_storage_path === "string"
                ? values.digital_storage_path
                : "",
        digitalAutoFulfill: Boolean(values?.digital_auto_fulfill ?? true),
        digitalMaxFileSize: Number.isFinite(Number(values?.digital_max_file_size))
            ? Math.max(1, Math.min(2048, Number(values.digital_max_file_size)))
            : 200,
        digitalAllowedExtensions: getInitialAllowedExtensions(values),
        digitalCustomExtensions:
            typeof values?.digital?.custom_extensions === "string"
                ? values.digital.custom_extensions
                : "",
    });

    // Store file type categories from API
    if (values?.digital?.file_type_categories) {
        fileTypeCategories.value = values.digital.file_type_categories;
    }

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
        enabled: cod.enabled !== undefined ? Boolean(cod.enabled) : paymentsDraft.cod.enabled,
        label: cod.label ?? paymentsDraft.cod.label ?? "Cash on delivery",
    });

    Object.assign(paymentsDraft.bank_transfer, {
        enabled: bank.enabled !== undefined ? Boolean(bank.enabled) : paymentsDraft.bank_transfer.enabled,
        label: bank.label ?? paymentsDraft.bank_transfer.label ?? "Bank transfer",
        instructions: bank.instructions ?? paymentsDraft.bank_transfer.instructions ?? "",
        account_name: bank.account_name ?? paymentsDraft.bank_transfer.account_name ?? "",
        iban: bank.iban ?? paymentsDraft.bank_transfer.iban ?? "",
        bic: bank.bic ?? paymentsDraft.bank_transfer.bic ?? "",
    });
};

// ============================================================================
// Watchers
// ============================================================================

watch(
    () => settingsState.values,
    (values) => {
        applySettings(values ?? {});
    },
    { immediate: true }
);

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

// ============================================================================
// Event Handlers - General Tab
// ============================================================================

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

// ============================================================================
// Event Handlers - Digital Tab
// ============================================================================

const saveDigital = () => {
    const maxDownloads = Number.isFinite(Number(settingsDraft.digitalMaxDownloads))
        ? Math.max(0, Number(settingsDraft.digitalMaxDownloads))
        : 0;
    const expiryDays = Number.isFinite(Number(settingsDraft.digitalExpiryDays))
        ? Math.max(0, Number(settingsDraft.digitalExpiryDays))
        : 0;
    const storagePath = (settingsDraft.digitalStoragePath || "").trim();
    const maxFileSize = Number.isFinite(Number(settingsDraft.digitalMaxFileSize))
        ? Math.max(1, Math.min(2048, Number(settingsDraft.digitalMaxFileSize)))
        : 200;

    const allPredefined = getAllPredefinedExtensions();
    const selectedExtensions = settingsDraft.digitalAllowedExtensions || [];
    const allSelected =
        allPredefined.length === selectedExtensions.length &&
        allPredefined.every((ext) => selectedExtensions.includes(ext));

    const allowedExtensions = allSelected ? null : [...selectedExtensions];
    const customExtensions = (settingsDraft.digitalCustomExtensions || "").trim();

    emit("save-settings", {
        digital: {
            max_downloads: maxDownloads,
            expiry_days: expiryDays,
            storage_path: storagePath,
            auto_fulfill: Boolean(settingsDraft.digitalAutoFulfill),
            max_file_size: maxFileSize,
            allowed_extensions: allowedExtensions,
            custom_extensions: customExtensions,
        },
    });
};

const resetDigital = () => {
    applySettings(settingsState.values ?? {});
};

// ============================================================================
// Event Handlers - Security Tab
// ============================================================================

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

// ============================================================================
// Event Handlers - Payments Tab
// ============================================================================

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

// ============================================================================
// Event Handlers - Visual Tab
// ============================================================================

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
</script>

<style scoped>
/* Tab Navigation */
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

/* Settings Panel (child tab components) */
:deep(.nxp-ec-settings-panel) {
    display: grid;
    gap: 1.5rem;
    padding: 1.5rem;
}

:deep(.nxp-ec-settings-panel__header) {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--nxp-ec-border, #e4e7ec);
    margin-bottom: 0.5rem;
}

:deep(.nxp-ec-settings-panel__header h3) {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--nxp-ec-text-heading, #1f2933);
}

/* Settings Form */
:deep(.nxp-ec-settings-form) {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

:deep(.nxp-ec-form-grid) {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.25rem;
}

/* Fieldsets for Section Differentiation */
:deep(fieldset) {
    border: 1px solid var(--nxp-ec-border, #e4e7ec);
    border-radius: 8px;
    padding: 1.25rem;
    margin: 0;
    background: var(--nxp-ec-surface-alt, #f9fafb);
}

:deep(legend) {
    font-weight: 600;
    font-size: 1rem;
    color: var(--nxp-ec-text-heading, #1f2933);
    padding: 0 0.5rem;
    margin-left: -0.5rem;
}

:deep(.nxp-ec-fieldset-legend-with-help) {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    width: 100%;
}

:deep(.nxp-ec-fieldset-legend-with-help span) {
    flex-shrink: 0;
}

/* Form Sections */
:deep(.nxp-ec-form-section) {
    border: 1px solid var(--nxp-ec-border, #e4e7ec);
    border-radius: 8px;
    padding: 1.25rem;
    background: var(--nxp-ec-surface-alt, #f9fafb);
}

:deep(.nxp-ec-form-section__title) {
    margin: 0 0 0.75rem 0;
    font-size: 1rem;
    font-weight: 600;
    color: var(--nxp-ec-text-heading, #1f2933);
}

/* Settings Actions */
:deep(.nxp-ec-settings-actions) {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding-top: 1rem;
    border-top: 1px solid var(--nxp-ec-border, #e4e7ec);
    margin-top: 0.5rem;
}

/* Visual Tab Specific */
:deep(.nxp-ec-visual-grid) {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.25rem;
}

:deep(.nxp-ec-visual-field) {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

:deep(.nxp-ec-color-input-group) {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

:deep(.nxp-ec-color-input-group .nxp-ec-form-input) {
    max-width: 100px;
}

:deep(.nxp-ec-color-picker) {
    width: 40px;
    height: 40px;
    border: 1px solid var(--nxp-ec-border, #d0d5dd);
    border-radius: 6px;
    padding: 2px;
    cursor: pointer;
}

:deep(.nxp-ec-visual-preview) {
    margin-top: 1rem;
    padding: 1rem;
    border: 1px solid var(--nxp-ec-border, #e4e7ec);
    border-radius: 8px;
    background: var(--nxp-ec-surface, #fff);
}

:deep(.nxp-ec-visual-preview h4) {
    margin: 0 0 0.75rem 0;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--nxp-ec-text-muted, #6b7280);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

:deep(.nxp-ec-preview-box) {
    padding: 1.5rem;
    border-radius: 8px;
    background: var(--nxp-ec-color-surface, #fff);
    border: 1px solid var(--nxp-ec-color-border, #e4e7ec);
}

:deep(.nxp-ec-preview-btn) {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 6px;
    background: var(--nxp-ec-color-primary, #4f46e5);
    color: #fff;
    font-weight: 500;
    cursor: pointer;
    margin-bottom: 0.75rem;
}

:deep(.nxp-ec-preview-text) {
    margin: 0 0 0.5rem 0;
    color: var(--nxp-ec-color-text, #1f2933);
}

:deep(.nxp-ec-preview-muted) {
    margin: 0;
    color: var(--nxp-ec-color-muted, #6b7280);
    font-size: 0.9rem;
}

/* Digital Tab File Types */
:deep(.nxp-ec-filetypes-actions) {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

:deep(.nxp-ec-filetypes-grid) {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

:deep(.nxp-ec-filetypes-category) {
    border: 1px solid var(--nxp-ec-border, #e4e7ec);
    border-radius: 6px;
    padding: 0.75rem;
    background: var(--nxp-ec-surface, #fff);
}

:deep(.nxp-ec-filetypes-category__title) {
    margin: 0 0 0.5rem 0;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--nxp-ec-text-heading, #1f2933);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

:deep(.nxp-ec-filetypes-category__list) {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

:deep(.nxp-ec-filetypes-item) {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    cursor: pointer;
    font-size: 0.85rem;
}

:deep(.nxp-ec-filetypes-item__checkbox) {
    width: 1rem;
    height: 1rem;
}

:deep(.nxp-ec-filetypes-item__label) {
    color: var(--nxp-ec-text, #374151);
}

/* Help link styling */
:deep(.nxp-ec-field-help-link),
:deep(.nxp-ec-help-link) {
    text-decoration: none;
    color: var(--nxp-ec-primary, #4f46e5);
    font-size: 0.85rem;
}

:deep(.nxp-ec-form-help--warning) {
    color: var(--nxp-ec-warning, #d97706);
}

:deep(.nxp-ec-form-help--muted) {
    color: var(--nxp-ec-text-muted, #9ca3af);
    font-style: italic;
}

/* Responsive */
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

    :deep(.nxp-ec-settings-panel) {
        padding: 1rem;
    }

    :deep(.nxp-ec-settings-panel__header) {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }

    :deep(.nxp-ec-form-grid) {
        grid-template-columns: 1fr;
    }

    :deep(.nxp-ec-visual-grid) {
        grid-template-columns: 1fr;
    }

    :deep(.nxp-ec-filetypes-grid) {
        grid-template-columns: 1fr;
    }
}
</style>
