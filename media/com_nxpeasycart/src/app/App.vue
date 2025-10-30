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

    <ProductPanel
      v-if="activeSection === 'products'"
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
import ProductPanel from './components/ProductPanel.vue';
import OrdersPanel from './components/OrdersPanel.vue';
import { useTranslations } from './composables/useTranslations.js';
import { useProducts } from './composables/useProducts.js';
import { useOrders } from './composables/useOrders.js';

const props = defineProps({
  csrfToken: {
    type: String,
    default: '',
  },
  productsEndpoints: {
    type: Object,
    default: () => ({ list: '', create: '', update: '', delete: '' }),
  },
  dataset: {
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
  const items = parseJSON(props.dataset?.navItems, []);

  return Array.isArray(items)
    ? items.map((item) => ({
        id: item.id,
        title: item.title,
        link: item.link,
      }))
    : [];
});

const activeSection = computed(() => props.dataset?.activeSection || 'dashboard');

const productsEndpoints = {
  list: props.dataset?.productsEndpoint ?? '',
  create: props.dataset?.productsEndpointCreate ?? '',
  update: props.dataset?.productsEndpointUpdate ?? '',
  delete: props.dataset?.productsEndpointDelete ?? '',
};

const ordersEndpoints = {
  list: props.dataset?.ordersEndpoint ?? '',
  show: props.dataset?.ordersEndpointShow ?? '',
  transition: props.dataset?.ordersEndpointTransition ?? '',
};

const orderStates = computed(() => parseJSON(props.dataset?.orderStates, []));

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
} = useOrders({
  endpoints: ordersEndpoints,
  token: props.csrfToken,
  states: orderStates.value,
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
</script>
