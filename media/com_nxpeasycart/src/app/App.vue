<template>
    <ErrorBoundary>
        <section class="nxp-ec-admin-app__shell">
        <header class="nxp-ec-admin-app__header">
            <div>
                <h1 class="nxp-ec-admin-app__title">
                    {{
                        __(
                            appTitleKey,
                            props.dataset?.appTitle ?? "NXP Easy Cart",
                            [],
                            "appTitle"
                        )
                    }}
                </h1>
                <p class="nxp-ec-admin-app__lead">
                    {{
                        __(
                            appLeadKey,
                            props.dataset?.appLead ??
                                "Manage your storefront from one place.",
                            [],
                            "appLead"
                        )
                    }}
                </p>
            </div>
            <button
                v-show="hasIncompleteOnboarding"
                class="nxp-ec-btn nxp-ec-btn--ghost"
                type="button"
                @click="openOnboarding"
            >
                {{
                    __(
                        "COM_NXPEASYCART_ONBOARDING_OPEN",
                        "Open onboarding",
                        [],
                        "onboardingOpen"
                    )
                }}
            </button>
        </header>

        <nav v-if="navItems.length" class="nxp-ec-admin-nav">
            <a
                v-for="item in navItems"
                :key="item.id"
                :href="item.link"
                :class="{ 'is-active': item.id === activeSection }"
            >
                {{ item.title }}
            </a>
        </nav>

        <DashboardPanel
            v-if="activeSection === 'dashboard'"
            :state="dashboardState"
            :translate="__"
            @refresh="onDashboardRefresh"
        />

        <ProductPanel
            v-else-if="activeSection === 'products'"
            :state="productState"
            :translate="__"
            :base-currency="baseCurrency"
            :category-options="categoryOptions"
            :media-modal-url="mediaModalUrl"
            @create="onProductCreate"
            @update="onProductUpdate"
            @delete="onProductDelete"
            @refresh="onProductRefresh"
            @search="onProductSearch"
        />

        <CategoryPanel
            v-else-if="activeSection === 'categories'"
            :state="categoriesState"
            :translate="__"
            :load-options="loadCategoryOptions"
            @refresh="onCategoriesRefresh"
            @search="onCategoriesSearch"
            @page="onCategoriesPage"
            @save="onCategoriesSave"
            @delete="onCategoriesDelete"
        />

        <OrdersPanel
            v-else-if="activeSection === 'orders'"
            :state="ordersState"
            :translate="__"
            :site-root="siteRoot"
            @refresh="onOrdersRefresh"
            @search="onOrdersSearch"
            @filter="onOrdersFilter"
            @view="onOrdersView"
            @close="onOrdersClose"
            @transition="onOrdersTransition"
            @page="onOrdersPage"
            @bulk-transition="onOrdersBulkTransition"
            @toggle-selection="onOrdersToggleSelection"
            @clear-selection="onOrdersClearSelection"
            @add-note="onOrdersAddNote"
            @save-tracking="onOrdersSaveTracking"
            @invoice="onOrdersInvoice"
            @export="onOrdersExport"
        />

        <CustomersPanel
            v-else-if="activeSection === 'customers'"
            :state="customersState"
            :translate="__"
            :base-currency="baseCurrency"
            @refresh="onCustomersRefresh"
            @search="onCustomersSearch"
            @page="onCustomersPage"
            @view="onCustomersView"
            @close="onCustomersClose"
        />

        <CouponsPanel
            v-else-if="activeSection === 'coupons'"
            :state="couponsState"
            :translate="__"
            :base-currency="baseCurrency"
            @refresh="onCouponsRefresh"
            @search="onCouponsSearch"
            @page="onCouponsPage"
            @save="onCouponsSave"
            @delete="onCouponsDelete"
        />

        <SettingsPanel
            v-else-if="activeSection === 'settings'"
            :settings-state="settingsState"
            :tax-state="taxState"
            :shipping-state="shippingState"
            :payments-state="paymentsState"
            :translate="__"
            :base-currency="baseCurrency"
            @refresh-settings="onSettingsRefresh"
            @save-settings="onSettingsSave"
            @refresh-tax="onTaxRefresh"
            @save-tax="onTaxSave"
            @delete-tax="onTaxDelete"
            @refresh-shipping="onShippingRefresh"
            @save-shipping="onShippingSave"
            @delete-shipping="onShippingDelete"
            @refresh-payments="onPaymentsRefresh"
            @save-payments="onPaymentsSave"
        />

        <LogsPanel
            v-else-if="activeSection === 'logs'"
            :state="logsState"
            :translate="__"
            @refresh="onLogsRefresh"
            @search="onLogsSearch"
            @filter="onLogsFilter"
            @page="onLogsPage"
        />

        <div v-else class="nxp-ec-admin-panel nxp-ec-admin-panel--placeholder">
            <div class="nxp-ec-admin-panel__body">
                <p class="nxp-ec-admin-panel__lead">
                    {{
                        __(
                            appLeadKey,
                            props.dataset?.appLead ??
                                "This section will be available soon.",
                            [],
                            "appLead"
                        )
                    }}
                </p>
            </div>
        </div>
    </section>
    <OnboardingWizard
        :visible="onboardingVisible"
        :steps="onboardingSteps"
        :translate="__"
        @close="dismissOnboarding"
        @navigate="navigateOnboarding"
    />
    </ErrorBoundary>
</template>

<script setup>
import { computed, onMounted, ref, watch } from "vue";
import ErrorBoundary from "./components/ErrorBoundary.vue";
import DashboardPanel from "./components/DashboardPanel.vue";
import ProductPanel from "./components/ProductPanel.vue";
import CategoryPanel from "./components/CategoryPanel.vue";
import OrdersPanel from "./components/OrdersPanel.vue";
import CustomersPanel from "./components/CustomersPanel.vue";
import CouponsPanel from "./components/CouponsPanel.vue";
import SettingsPanel from "./components/SettingsPanel.vue";
import LogsPanel from "./components/LogsPanel.vue";
import OnboardingWizard from "./components/OnboardingWizard.vue";
import { useTranslations } from "./composables/useTranslations.js";
import { useProducts } from "./composables/useProducts.js";
import { useCategories } from "./composables/useCategories.js";
import { useOrders } from "./composables/useOrders.js";
import { useDashboard } from "./composables/useDashboard.js";
import { useCustomers } from "./composables/useCustomers.js";
import { useCoupons } from "./composables/useCoupons.js";
import { useSettings } from "./composables/useSettings.js";
import { useTaxRates } from "./composables/useTaxRates.js";
import { useShippingRules } from "./composables/useShippingRules.js";
import { useLogs } from "./composables/useLogs.js";
import { usePayments } from "./composables/usePayments.js";

const props = defineProps({
    csrfToken: {
        type: String,
        default: "",
    },
    dataset: {
        type: Object,
        default: () => ({}),
    },
    config: {
        type: Object,
        default: () => ({}),
    },
    endpoints: {
        type: Object,
        default: () => ({}),
    },
});

const { __ } = useTranslations(props.dataset);

const appTitleKey = computed(
    () => props.dataset?.appTitleKey || "COM_NXPEASYCART"
);
const appLeadKey = computed(
    () => props.dataset?.appLeadKey || "COM_NXPEASYCART_ADMIN_PLACEHOLDER"
);

const parseJSON = (value, fallback) => {
    if (!value || typeof value !== "string") {
        return fallback;
    }

    try {
        return JSON.parse(value);
    } catch (error) {
        return fallback;
    }
};

const navItems = computed(() => {
    if (Array.isArray(props.config?.navItems)) {
        return props.config.navItems.map((item) => ({
            id: item.id,
            title: item.title,
            link: item.link,
        }));
    }

    const items = parseJSON(props.dataset?.navItems, []);

    return Array.isArray(items)
        ? items.map((item) => ({
              id: item.id,
              title: item.title,
              link: item.link,
          }))
        : [];
});

const navLinkFor = (id) => {
    const items = navItems.value;

    if (!Array.isArray(items)) {
        return "";
    }

    const match = items.find((item) => item.id === id);

    return match ? match.link : "";
};

const activeSection = computed(
    () =>
        props.config?.activeSection ||
        props.dataset?.activeSection ||
        "dashboard"
);

const sectionIs = (id) => activeSection.value === id;

const shouldLoadDashboard = computed(() => sectionIs("dashboard"));
const shouldLoadProducts = computed(() => sectionIs("products"));
const shouldLoadCategories = computed(
    () => sectionIs("categories") || sectionIs("products")
);
const shouldLoadOrders = computed(() => sectionIs("orders"));
const shouldLoadCustomers = computed(() => sectionIs("customers"));
const shouldLoadCoupons = computed(() => sectionIs("coupons"));
const shouldLoadSettings = computed(() => sectionIs("settings"));
const shouldLoadLogs = computed(() => sectionIs("logs"));

const productsEndpoints = props.endpoints?.products ?? {
    list: props.dataset?.productsEndpoint ?? "",
    create: props.dataset?.productsEndpointCreate ?? "",
    update: props.dataset?.productsEndpointUpdate ?? "",
    delete: props.dataset?.productsEndpointDelete ?? "",
};

const categoriesEndpoints = props.endpoints?.categories ?? {
    list: props.dataset?.categoriesEndpoint ?? "",
    create: props.dataset?.categoriesEndpointCreate ?? "",
    update: props.dataset?.categoriesEndpointUpdate ?? "",
    delete: props.dataset?.categoriesEndpointDelete ?? "",
};

const ordersEndpoints = props.endpoints?.orders ?? {
    list: props.dataset?.ordersEndpoint ?? "",
    show: props.dataset?.ordersEndpointShow ?? "",
    transition: props.dataset?.ordersEndpointTransition ?? "",
    bulkTransition: props.dataset?.ordersEndpointBulk ?? "",
    note: props.dataset?.ordersEndpointNote ?? "",
};

const customersEndpoints = props.endpoints?.customers ?? {
    list: props.dataset?.customersEndpoint ?? "",
    show: props.dataset?.customersEndpointShow ?? "",
};

const couponsEndpoints = props.endpoints?.coupons ?? {
    list: props.dataset?.couponsEndpoint ?? "",
    create: props.dataset?.couponsEndpointCreate ?? "",
    update: props.dataset?.couponsEndpointUpdate ?? "",
    delete: props.dataset?.couponsEndpointDelete ?? "",
};

const taxEndpoints = props.endpoints?.tax ?? {
    list: props.dataset?.taxEndpoint ?? "",
    create: props.dataset?.taxEndpointCreate ?? "",
    update: props.dataset?.taxEndpointUpdate ?? "",
    delete: props.dataset?.taxEndpointDelete ?? "",
};

const shippingEndpoints = props.endpoints?.shipping ?? {
    list: props.dataset?.shippingEndpoint ?? "",
    create: props.dataset?.shippingEndpointCreate ?? "",
    update: props.dataset?.shippingEndpointUpdate ?? "",
    delete: props.dataset?.shippingEndpointDelete ?? "",
};

const settingsEndpoints = props.endpoints?.settings ?? {
    show: props.dataset?.settingsEndpointShow ?? "",
    update: props.dataset?.settingsEndpointUpdate ?? "",
};

const logsEndpoints = props.endpoints?.logs ?? {
    list: props.dataset?.logsEndpoint ?? "",
};

const paymentsEndpoints = props.endpoints?.payments ?? {
    show: props.dataset?.paymentsEndpointShow ?? "",
    update: props.dataset?.paymentsEndpointUpdate ?? "",
};

const orderStates = computed(() => parseJSON(props.dataset?.orderStates, []));

const { state: dashboardState, refresh: refreshDashboard } = useDashboard({
    endpoint:
        props.endpoints?.dashboard ??
        props.config?.endpoints?.dashboard ??
        props.dataset?.dashboardEndpoint ??
        "",
    token: props.csrfToken,
    preload: props.config?.preload?.dashboard ?? {
        summary: parseJSON(props.dataset?.dashboardSummary, {}),
        checklist: parseJSON(props.dataset?.dashboardChecklist, []),
    },
    autoload: shouldLoadDashboard.value,
});

const {
    state: productState,
    refresh: refreshProducts,
    search: searchProducts,
    createProduct,
    updateProduct,
    deleteProducts,
} = useProducts({
    endpoints: productsEndpoints,
    token: props.csrfToken,
    autoload: shouldLoadProducts.value,
});

const {
    state: categoriesState,
    refresh: refreshCategories,
    search: searchCategories,
    goToPage: goToCategoriesPage,
    saveCategory,
    deleteCategories: removeCategories,
    loadOptions: loadCategoryOptions,
} = useCategories({
    endpoints: categoriesEndpoints,
    token: props.csrfToken,
    preload: props.config?.preload?.categories ?? {
        items: parseJSON(props.dataset?.categoriesPreload, []),
        pagination: parseJSON(props.dataset?.categoriesPreloadPagination, {}),
    },
    autoload: shouldLoadCategories.value,
});

const categoryOptions = computed(() => {
    const items = Array.isArray(categoriesState.items)
        ? categoriesState.items
        : [];

    return items
        .map((item) => ({
            id: Number.parseInt(item?.id ?? 0, 10) || 0,
            title: String(item?.title ?? "").trim(),
            slug: String(item?.slug ?? "").trim(),
            parent_id: item?.parent_id !== null && item?.parent_id !== undefined
                ? (Number.parseInt(item.parent_id, 10) || null)
                : null,
        }))
        .filter((item) => item.title !== "");
});

const {
    state: ordersState,
    refresh: refreshOrders,
    search: searchOrders,
    setFilterState: setOrdersFilter,
    viewOrder,
    closeOrder,
    transitionOrder,
    goToPage: goToOrdersPage,
    bulkTransition: bulkTransitionOrders,
    toggleSelection: toggleOrderSelection,
    clearSelection: clearOrderSelection,
    addNote: addOrderNote,
    updateTracking: updateOrderTracking,
    downloadInvoice: downloadOrderInvoice,
    exportOrders,
} = useOrders({
    endpoints: ordersEndpoints,
    token: props.csrfToken,
    states: orderStates.value,
    preload: props.config?.preload?.orders ?? {
        items: parseJSON(props.dataset?.ordersPreload, []),
        pagination: parseJSON(props.dataset?.ordersPreloadPagination, {}),
    },
    autoload: shouldLoadOrders.value,
});

const {
    state: customersState,
    refresh: refreshCustomers,
    search: searchCustomers,
    goToPage: goToCustomersPage,
    viewCustomer,
    closeCustomer,
} = useCustomers({
    endpoints: customersEndpoints,
    token: props.csrfToken,
    preload: props.config?.preload?.customers ?? {
        items: parseJSON(props.dataset?.customersPreload, []),
        pagination: parseJSON(props.dataset?.customersPreloadPagination, {}),
    },
    autoload: shouldLoadCustomers.value,
});

const {
    state: couponsState,
    refresh: refreshCoupons,
    search: searchCoupons,
    goToPage: goToCouponsPage,
    saveCoupon,
    deleteCoupons,
} = useCoupons({
    endpoints: props.endpoints?.coupons ?? couponsEndpoints,
    token: props.csrfToken,
    preload: props.config?.preload?.coupons ?? {
        items: parseJSON(props.dataset?.couponsPreload, []),
        pagination: parseJSON(props.dataset?.couponsPreloadPagination, {}),
    },
    autoload: shouldLoadCoupons.value,
});

const settingsPreload =
    props.config?.preload?.settings ??
    parseJSON(props.dataset?.settingsPreload, {});

const {
    state: settingsState,
    refresh: refreshSettings,
    save: saveSettingsValues,
} = useSettings({
    endpoints: settingsEndpoints,
    token: props.csrfToken,
    preload: settingsPreload,
    autoload: shouldLoadSettings.value,
});

const datasetBaseCurrency = computed(() => {
    const value = props.dataset?.baseCurrency ?? "";
    return typeof value === "string" ? value.trim().toUpperCase() : "";
});

const settingsBaseCurrency = computed(() => {
    const value = settingsState?.values?.base_currency ?? "";
    return typeof value === "string" ? value.trim().toUpperCase() : "";
});

const baseCurrency = computed(() => {
    const currency = settingsBaseCurrency.value || datasetBaseCurrency.value;
    return currency !== "" ? currency : "USD";
});

const mediaModalUrl = computed(() => {
    const value = props.dataset?.mediaModalUrl ?? "";
    return typeof value === "string" ? value.trim() : "";
});

const siteRoot = computed(() => {
    const configured =
        props.config?.siteRoot ?? props.dataset?.siteRoot ?? "";

    if (typeof configured === "string" && configured.trim() !== "") {
        return configured.trim();
    }

    if (typeof window !== "undefined" && window.location?.origin) {
        return window.location.origin;
    }

    return "";
});

const onboardingCopy = (label) => {
    switch (label) {
        case "COM_NXPEASYCART_CHECKLIST_SET_CURRENCY":
            return {
                id: "set-currency",
                titleKey: "COM_NXPEASYCART_ONBOARDING_STEP_SET_CURRENCY_TITLE",
                descriptionKey:
                    "COM_NXPEASYCART_ONBOARDING_STEP_SET_CURRENCY_DESC",
                titleFallback: "Set your base currency",
                descriptionFallback:
                    "Lock in the store currency so product pricing and orders stay consistent.",
                link:
                    navLinkFor("settings") ||
                    "index.php?option=com_nxpeasycart&view=settings",
            };
        case "COM_NXPEASYCART_CHECKLIST_ADD_PRODUCT":
            return {
                id: "add-product",
                titleKey: "COM_NXPEASYCART_ONBOARDING_STEP_ADD_PRODUCT_TITLE",
                descriptionKey:
                    "COM_NXPEASYCART_ONBOARDING_STEP_ADD_PRODUCT_DESC",
                titleFallback: "Create your first product",
                descriptionFallback:
                    "Add product details, images, and variants to populate your catalogue.",
                link:
                    navLinkFor("products") ||
                    "index.php?option=com_nxpeasycart&view=products",
            };
        case "COM_NXPEASYCART_CHECKLIST_CONFIGURE_PAYMENTS":
            return {
                id: "configure-payments",
                titleKey:
                    "COM_NXPEASYCART_ONBOARDING_STEP_CONFIGURE_PAYMENTS_TITLE",
                descriptionKey:
                    "COM_NXPEASYCART_ONBOARDING_STEP_CONFIGURE_PAYMENTS_DESC",
                titleFallback: "Configure payment gateways",
                descriptionFallback:
                    "Connect Stripe or PayPal so you can capture orders securely.",
                link:
                    navLinkFor("settings") ||
                    "index.php?option=com_nxpeasycart&view=settings",
            };
        case "COM_NXPEASYCART_CHECKLIST_REVIEW_ORDERS":
            return {
                id: "review-orders",
                titleKey: "COM_NXPEASYCART_ONBOARDING_STEP_REVIEW_ORDERS_TITLE",
                descriptionKey:
                    "COM_NXPEASYCART_ONBOARDING_STEP_REVIEW_ORDERS_DESC",
                titleFallback: "Review the orders workspace",
                descriptionFallback:
                    "Verify fulfilment workflows, notes, and state transitions.",
                link:
                    navLinkFor("orders") ||
                    "index.php?option=com_nxpeasycart&view=orders",
            };
        case "COM_NXPEASYCART_CHECKLIST_INVITE_CUSTOMERS":
            return {
                id: "invite-customers",
                titleKey:
                    "COM_NXPEASYCART_ONBOARDING_STEP_INVITE_CUSTOMERS_TITLE",
                descriptionKey:
                    "COM_NXPEASYCART_ONBOARDING_STEP_INVITE_CUSTOMERS_DESC",
                titleFallback: "Invite customers or teammates",
                descriptionFallback:
                    "Send invites or import contacts so customers can access their orders.",
                link:
                    navLinkFor("customers") ||
                    "index.php?option=com_nxpeasycart&view=customers",
            };
        default:
            return {
                id: label || "step",
                titleKey:
                    label || "COM_NXPEASYCART_ONBOARDING_STEP_DEFAULT_TITLE",
                descriptionKey:
                    label || "COM_NXPEASYCART_ONBOARDING_STEP_DEFAULT_DESC",
                titleFallback: "Complete setup",
                descriptionFallback:
                    "Work through this step to finish onboarding.",
                link: navLinkFor("dashboard") || "",
            };
    }
};

const onboardingSteps = computed(() => {
    const list = Array.isArray(dashboardState.checklist)
        ? dashboardState.checklist
        : [];

    return list.map((item) => {
        const copy = onboardingCopy(item.label);
        return {
            id: copy.id,
            completed: Boolean(item.completed),
            link: item.link || copy.link || "",
            label: item.label,
            titleKey: copy.titleKey,
            descriptionKey: copy.descriptionKey,
            titleFallback: copy.titleFallback,
            descriptionFallback: copy.descriptionFallback,
        };
    });
});

const hasIncompleteOnboarding = computed(() =>
    onboardingSteps.value.some((step) => !step.completed)
);
const onboardingVisible = ref(false);
const onboardingStorageKey = "nxp:admin:onboarding:dismissed";

const openOnboarding = () => {
    if (typeof window !== "undefined") {
        try {
            window.localStorage.setItem(onboardingStorageKey, "0");
        } catch (error) {
            // Ignore storage errors (private mode, etc.).
        }
    }

    onboardingVisible.value = true;
};

const dismissOnboarding = () => {
    onboardingVisible.value = false;

    if (typeof window !== "undefined") {
        try {
            window.localStorage.setItem(onboardingStorageKey, "1");
        } catch (error) {
            // Ignore storage errors.
        }
    }
};

const navigateOnboarding = (step) => {
    if (step && typeof step.link === "string" && step.link !== "") {
        dismissOnboarding();

        if (typeof window !== "undefined") {
            window.location.href = step.link;
        }
    }
};

watch(hasIncompleteOnboarding, (incomplete) => {
    if (!incomplete) {
        onboardingVisible.value = false;

        if (typeof window !== "undefined") {
            try {
                window.localStorage.setItem(onboardingStorageKey, "1");
            } catch (error) {
                // Ignore storage errors.
            }
        }
    }
});

const taxPreload = props.config?.preload?.tax ?? {
    items: parseJSON(props.dataset?.taxPreload, []),
    pagination: parseJSON(props.dataset?.taxPreloadPagination, {}),
};

const {
    state: taxState,
    refresh: refreshTaxRates,
    saveRate: saveTaxRate,
    deleteRates: deleteTaxRates,
} = useTaxRates({
    endpoints: taxEndpoints,
    token: props.csrfToken,
    preload: taxPreload,
    autoload: shouldLoadSettings.value,
});

const shippingPreload = props.config?.preload?.shipping ?? {
    items: parseJSON(props.dataset?.shippingPreload, []),
    pagination: parseJSON(props.dataset?.shippingPreloadPagination, {}),
};

const {
    state: shippingState,
    refresh: refreshShippingRules,
    saveRule: saveShippingRule,
    deleteRules: deleteShippingRules,
} = useShippingRules({
    endpoints: shippingEndpoints,
    token: props.csrfToken,
    preload: shippingPreload,
    autoload: shouldLoadSettings.value,
});

const logsPreload = props.config?.preload?.logs ?? {
    items: parseJSON(props.dataset?.logsPreload, []),
    pagination: parseJSON(props.dataset?.logsPreloadPagination, {}),
};

const {
    state: logsState,
    refresh: refreshLogs,
    search: searchLogs,
    setEntity: setLogsEntity,
    goToPage: goToLogsPage,
} = useLogs({
    endpoints: logsEndpoints,
    token: props.csrfToken,
    preload: logsPreload,
    autoload: shouldLoadLogs.value,
});

const {
    state: paymentsState,
    refresh: refreshPayments,
    save: savePaymentsConfig,
} = usePayments({
    endpoints: paymentsEndpoints,
    token: props.csrfToken,
});

watch(activeSection, (section) => {
    switch (section) {
        case "dashboard":
            refreshDashboard();
            break;
        case "products":
            refreshProducts();
            refreshCategories();
            break;
        case "categories":
            refreshCategories();
            break;
        case "orders":
            refreshOrders();
            break;
        case "customers":
            refreshCustomers();
            break;
        case "coupons":
            refreshCoupons();
            break;
        case "settings":
            refreshSettings();
            refreshTaxRates();
            refreshShippingRules();
            refreshPayments();
            break;
        case "logs":
            refreshLogs();
            break;
        default:
            break;
    }
});

onMounted(() => {
    if (typeof window !== "undefined") {
        window.__NXP_EASYCART__ = {
            ...(window.__NXP_EASYCART__ || {}),
            adminMounted: true,
            dataset: props.dataset,
        };

        try {
            const dismissed = window.localStorage.getItem(onboardingStorageKey);

            if (hasIncompleteOnboarding.value) {
                if (dismissed !== "1") {
                    onboardingVisible.value = true;
                }
            } else {
                window.localStorage.setItem(onboardingStorageKey, "1");
            }
        } catch (error) {
            // Ignore storage availability issues.
        }
    }

    if (shouldLoadSettings.value) {
        refreshPayments();
    }

    //console.info('[NXP Easy Cart] Admin App mounted', props.dataset);
});

const onProductRefresh = () => {
    refreshProducts();
};

const onProductSearch = () => {
    searchProducts();
};

const onProductCreate = async (payload) => {
    try {
        await createProduct(payload);
        refreshProducts();
        refreshCategories();
    } catch (error) {
        // Errors surface via products state; suppress unhandled promise rejections.
    }
};

const onProductUpdate = async ({ id, data }) => {
    try {
        await updateProduct(id, data);
        refreshProducts();
        refreshCategories();
    } catch (error) {
        // Errors surface via products state.
    }
};

const onProductDelete = async (ids) => {
    try {
        await deleteProducts(ids);
        refreshProducts();
        refreshCategories();
    } catch (error) {
        // Errors surface via products state.
    }
};

const onCategoriesRefresh = () => {
    refreshCategories();
};

const onCategoriesSearch = () => {
    searchCategories();
};

const onCategoriesPage = (page) => {
    goToCategoriesPage(page);
};

const onCategoriesSave = async (payload) => {
    try {
        await saveCategory(payload);
        refreshCategories();
    } catch (error) {
        // Validation errors bubble via categories state.
    }
};

const onCategoriesDelete = async (ids) => {
    try {
        await removeCategories(ids);
        refreshCategories();
    } catch (error) {
        // Errors surface via categories state.
    }
};

const onDashboardRefresh = () => {
    refreshDashboard();
};

const onOrdersRefresh = () => {
    refreshOrders();
};

const onOrdersSearch = () => {
    searchOrders();
};

const onOrdersFilter = (value) => {
    setOrdersFilter(value);
};

const onOrdersView = (order) => {
    viewOrder(order);
};

const onOrdersClose = () => {
    closeOrder();
};

const onOrdersTransition = async ({ id, state }) => {
    await transitionOrder(id, state);
};

const onOrdersPage = (page) => {
    goToOrdersPage(page);
};

const onOrdersBulkTransition = async ({ ids, state }) => {
    await bulkTransitionOrders(ids, state);
};

const onOrdersToggleSelection = (orderId) => {
    toggleOrderSelection(orderId);
};

const onOrdersClearSelection = () => {
    clearOrderSelection();
};

const onOrdersAddNote = async ({ id, message }) => {
    await addOrderNote(id, message);
};

const onOrdersSaveTracking = async (payload) => {
    if (!payload?.id) {
        return;
    }

    const { id, ...tracking } = payload;
    await updateOrderTracking(id, tracking);
};

const onOrdersInvoice = async (orderId) => {
    if (!orderId) {
        return;
    }

    const invoice = await downloadOrderInvoice(orderId);

    if (!invoice?.content) {
        return;
    }

    const byteString = atob(invoice.content);
    const byteArray = new Uint8Array(byteString.length);

    for (let i = 0; i < byteString.length; i += 1) {
        byteArray[i] = byteString.charCodeAt(i);
    }

    const blob = new Blob([byteArray], { type: "application/pdf" });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = invoice.filename || "invoice.pdf";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    setTimeout(() => window.URL.revokeObjectURL(url), 1000);
};

const onOrdersExport = async () => {
    const exportData = await exportOrders();

    if (!exportData?.content) {
        return;
    }

    const byteString = atob(exportData.content);
    const byteArray = new Uint8Array(byteString.length);

    for (let i = 0; i < byteString.length; i += 1) {
        byteArray[i] = byteString.charCodeAt(i);
    }

    const blob = new Blob([byteArray], { type: "text/csv;charset=utf-8" });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = exportData.filename || "orders-export.csv";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    setTimeout(() => window.URL.revokeObjectURL(url), 1000);
};

const onCustomersRefresh = () => {
    refreshCustomers();
};

const onCustomersSearch = () => {
    searchCustomers();
};

const onCustomersPage = (page) => {
    goToCustomersPage(page);
};

const onCustomersView = (customer) => {
    viewCustomer(customer);
};

const onCustomersClose = () => {
    closeCustomer();
};

const onCouponsRefresh = () => {
    refreshCoupons();
};

const onCouponsSearch = () => {
    searchCoupons();
};

const onCouponsPage = (page) => {
    goToCouponsPage(page);
};

const onCouponsSave = async (payload) => {
    try {
        await saveCoupon(payload);
    } catch (error) {
        // Errors surface through state.error inside the coupons panel.
    }
};

const onCouponsDelete = async (ids) => {
    try {
        await deleteCoupons(ids);
    } catch (error) {
        // Errors surface through state.error inside the coupons panel.
    }
};

const onSettingsRefresh = () => {
    refreshSettings();
};

const onSettingsSave = async (payload) => {
    try {
        await saveSettingsValues(payload);
    } catch (error) {
        // Validation errors bubble via settingsState.error.
    }
};

const onTaxRefresh = () => {
    refreshTaxRates();
};

const onTaxSave = async (payload) => {
    try {
        await saveTaxRate(payload);
        refreshTaxRates();
    } catch (error) {
        // Validation errors bubble via taxState.error.
    }
};

const onTaxDelete = async (ids) => {
    try {
        await deleteTaxRates(ids);
        refreshTaxRates();
    } catch (error) {
        // Validation errors bubble via taxState.error.
    }
};

const onShippingRefresh = () => {
    refreshShippingRules();
};

const onShippingSave = async (payload) => {
    try {
        await saveShippingRule(payload);
        refreshShippingRules();
    } catch (error) {
        // Validation errors bubble via shippingState.error.
    }
};

const onShippingDelete = async (ids) => {
    try {
        await deleteShippingRules(ids);
        refreshShippingRules();
    } catch (error) {
        // Validation errors bubble via shippingState.error.
    }
};

const onLogsRefresh = () => {
    refreshLogs();
};

const onLogsSearch = () => {
    searchLogs();
};

const onLogsFilter = (entity) => {
    setLogsEntity(entity);
};

const onLogsPage = (page) => {
    goToLogsPage(page);
};

const onPaymentsRefresh = () => {
    refreshPayments();
};

const onPaymentsSave = async (config) => {
    await savePaymentsConfig(config);
};
</script>
