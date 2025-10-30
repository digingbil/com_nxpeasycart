<template>
  <section class="nxp-admin-app__shell">
    <header class="nxp-admin-app__header">
      <h1 class="nxp-admin-app__title">
        {{ __(appTitleKey, props.dataset?.appTitle ?? 'NXP Easy Cart', [], 'appTitle') }}
      </h1>
      <p class="nxp-admin-app__lead">
        {{ __(appLeadKey, props.dataset?.appLead ?? 'Manage your storefront from one place.', [], 'appLead') }}
      </p>
    </header>

    <nav v-if="navItems.length" class="nxp-admin-nav">
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
      @create="onProductCreate"
      @update="onProductUpdate"
      @delete="onProductDelete"
      @refresh="onProductRefresh"
      @search="onProductSearch"
    />

    <OrdersPanel
      v-else-if="activeSection === 'orders'"
      :state="ordersState"
      :translate="__"
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

    <div v-else class="nxp-admin-panel nxp-admin-panel--placeholder">
      <div class="nxp-admin-panel__body">
        <p class="nxp-admin-panel__lead">
          {{ __(appLeadKey, props.dataset?.appLead ?? 'This section will be available soon.', [], 'appLead') }}
        </p>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import DashboardPanel from './components/DashboardPanel.vue';
import ProductPanel from './components/ProductPanel.vue';
import OrdersPanel from './components/OrdersPanel.vue';
import CustomersPanel from './components/CustomersPanel.vue';
import CouponsPanel from './components/CouponsPanel.vue';
import SettingsPanel from './components/SettingsPanel.vue';
import LogsPanel from './components/LogsPanel.vue';
import { useTranslations } from './composables/useTranslations.js';
import { useProducts } from './composables/useProducts.js';
import { useOrders } from './composables/useOrders.js';
import { useDashboard } from './composables/useDashboard.js';
import { useCustomers } from './composables/useCustomers.js';
import { useCoupons } from './composables/useCoupons.js';
import { useSettings } from './composables/useSettings.js';
import { useTaxRates } from './composables/useTaxRates.js';
import { useShippingRules } from './composables/useShippingRules.js';
import { useLogs } from './composables/useLogs.js';

const props = defineProps({
  csrfToken: {
    type: String,
    default: '',
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

const appTitleKey = computed(() => props.dataset?.appTitleKey || 'COM_NXPEASYCART');
const appLeadKey = computed(() => props.dataset?.appLeadKey || 'COM_NXPEASYCART_ADMIN_PLACEHOLDER');

const parseJSON = (value, fallback) => {
  if (!value || typeof value !== 'string') {
    return fallback;
  }

  try {
    return JSON.parse(value);
  } catch (error) {
    return fallback;
  }
};

const baseCurrency = computed(() => {
  const value = props.dataset?.baseCurrency ?? '';

  if (typeof value === 'string' && value.trim() !== '') {
    return value.trim().toUpperCase();
  }

  return 'USD';
});

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

const activeSection = computed(() => props.config?.activeSection || props.dataset?.activeSection || 'dashboard');

const productsEndpoints = props.endpoints?.products ?? {
  list: props.dataset?.productsEndpoint ?? '',
  create: props.dataset?.productsEndpointCreate ?? '',
  update: props.dataset?.productsEndpointUpdate ?? '',
  delete: props.dataset?.productsEndpointDelete ?? '',
};

const ordersEndpoints = props.endpoints?.orders ?? {
  list: props.dataset?.ordersEndpoint ?? '',
  show: props.dataset?.ordersEndpointShow ?? '',
  transition: props.dataset?.ordersEndpointTransition ?? '',
  bulkTransition: props.dataset?.ordersEndpointBulk ?? '',
  note: props.dataset?.ordersEndpointNote ?? '',
};

const customersEndpoints = props.endpoints?.customers ?? {
  list: props.dataset?.customersEndpoint ?? '',
  show: props.dataset?.customersEndpointShow ?? '',
};

const couponsEndpoints = props.endpoints?.coupons ?? {
  list: props.dataset?.couponsEndpoint ?? '',
  create: props.dataset?.couponsEndpointCreate ?? '',
  update: props.dataset?.couponsEndpointUpdate ?? '',
  delete: props.dataset?.couponsEndpointDelete ?? '',
};

const taxEndpoints = props.endpoints?.tax ?? {
  list: props.dataset?.taxEndpoint ?? '',
  create: props.dataset?.taxEndpointCreate ?? '',
  update: props.dataset?.taxEndpointUpdate ?? '',
  delete: props.dataset?.taxEndpointDelete ?? '',
};

const shippingEndpoints = props.endpoints?.shipping ?? {
  list: props.dataset?.shippingEndpoint ?? '',
  create: props.dataset?.shippingEndpointCreate ?? '',
  update: props.dataset?.shippingEndpointUpdate ?? '',
  delete: props.dataset?.shippingEndpointDelete ?? '',
};

const settingsEndpoints = props.endpoints?.settings ?? {
  show: props.dataset?.settingsEndpointShow ?? '',
  update: props.dataset?.settingsEndpointUpdate ?? '',
};

const logsEndpoints = props.endpoints?.logs ?? {
  list: props.dataset?.logsEndpoint ?? '',
};

const orderStates = computed(() => parseJSON(props.dataset?.orderStates, []));

const {
  state: dashboardState,
  refresh: refreshDashboard,
} = useDashboard({
  endpoint: props.endpoints?.dashboard ?? props.config?.endpoints?.dashboard ?? props.dataset?.dashboardEndpoint ?? '',
  token: props.csrfToken,
  preload: props.config?.preload?.dashboard ?? {
    summary: parseJSON(props.dataset?.dashboardSummary, {}),
    checklist: parseJSON(props.dataset?.dashboardChecklist, []),
  },
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
} = useOrders({
  endpoints: ordersEndpoints,
  token: props.csrfToken,
  states: orderStates.value,
  preload: props.config?.preload?.orders ?? {
    items: parseJSON(props.dataset?.ordersPreload, []),
    pagination: parseJSON(props.dataset?.ordersPreloadPagination, {}),
  },
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
});

const settingsPreload = props.config?.preload?.settings ?? parseJSON(props.dataset?.settingsPreload, {});

const {
  state: settingsState,
  refresh: refreshSettings,
  save: saveSettingsValues,
} = useSettings({
  endpoints: settingsEndpoints,
  token: props.csrfToken,
  preload: settingsPreload,
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
});

onMounted(() => {
  if (typeof window !== 'undefined') {
    window.__NXP_EASYCART__ = {
      ...(window.__NXP_EASYCART__ || {}),
      adminMounted: true,
      dataset: props.dataset,
    };
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
  await createProduct(payload);
  refreshProducts();
};

const onProductUpdate = async ({ id, data }) => {
  await updateProduct(id, data);
  refreshProducts();
};

const onProductDelete = async (ids) => {
  await deleteProducts(ids);
  refreshProducts();
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
</script>
