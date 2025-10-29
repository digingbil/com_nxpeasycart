<template>
  <section class="nxp-admin-app__shell">
    <header class="nxp-admin-app__header">
      <h1 class="nxp-admin-app__title">
        {{ __('COM_NXPEASYCART', 'NXP Easy Cart', [], 'appTitle') }}
      </h1>
      <p class="nxp-admin-app__lead">
        {{ __('COM_NXPEASYCART_ADMIN_PLACEHOLDER', 'Manage your storefront from one place.', [], 'appLead') }}
      </p>
    </header>

    <ProductPanel
      :state="state"
      :translate="__"
      @refresh="onRefresh"
      @search="onSearch"
    />
  </section>
</template>

<script setup>
import ProductPanel from './components/ProductPanel.vue';
import { useTranslations } from './composables/useTranslations.js';
import { useProducts } from './composables/useProducts.js';

const props = defineProps({
  csrfToken: {
    type: String,
    default: '',
  },
  productsEndpoint: {
    type: String,
    default: '',
  },
  dataset: {
    type: Object,
    default: () => ({}),
  },
});

const { __ } = useTranslations(props.dataset);

const { state, refresh, search } = useProducts({
  endpoint: props.productsEndpoint,
  token: props.csrfToken,
});

const onRefresh = () => {
  refresh();
};

const onSearch = () => {
  search();
};
</script>
