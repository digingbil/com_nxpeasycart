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

    <ProductPanel
      :state="state"
      :translate="__"
      :base-currency="baseCurrency"
      @create="onCreate"
      @update="onUpdate"
      @delete="onDelete"
      @refresh="onRefresh"
      @search="onSearch"
    />
  </section>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import ProductPanel from './components/ProductPanel.vue';
import { useTranslations } from './composables/useTranslations.js';
import { useProducts } from './composables/useProducts.js';

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

const baseCurrency = computed(() => {
  const value = props.dataset?.baseCurrency ?? '';

  if (typeof value === 'string' && value.trim() !== '') {
    return value.trim().toUpperCase();
  }

  return 'USD';
});

const { state, refresh, search, createProduct, updateProduct, deleteProducts } = useProducts({
  endpoints: props.productsEndpoints,
  token: props.csrfToken,
});

onMounted(() => {
  if (typeof window !== 'undefined') {
    window.__NXP_EASYCART__ = {
      ...(window.__NXP_EASYCART__ || {}),
      adminMounted: true,
      dataset: props.dataset,
    };
  }

  console.info('[NXP Easy Cart] Admin App mounted', props.dataset);
});

const onRefresh = () => {
  refresh();
};

const onSearch = () => {
  search();
};

const onCreate = async (payload) => {
  await createProduct(payload);
  refresh();
};

const onUpdate = async ({ id, data }) => {
  await updateProduct(id, data);
  refresh();
};

const onDelete = async (ids) => {
  await deleteProducts(ids);
  refresh();
};
</script>
