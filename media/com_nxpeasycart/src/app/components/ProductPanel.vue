<template>
  <section class="nxp-admin-panel">
    <header class="nxp-admin-panel__header">
      <div>
        <h2 class="nxp-admin-panel__title">
          {{ __('COM_NXPEASYCART_MENU_PRODUCTS', 'Products', [], 'productsPanelTitle') }}
        </h2>
        <p class="nxp-admin-panel__lead">
          {{ __('COM_NXPEASYCART_PRODUCTS_LEAD', 'Manage products from a single dashboard.', [], 'productsPanelLead') }}
        </p>
      </div>
      <div class="nxp-admin-panel__actions">
        <input
          type="search"
          class="nxp-admin-search"
          :placeholder="__('COM_NXPEASYCART_PRODUCTS_SEARCH_PLACEHOLDER', 'Search products', [], 'productsSearchPlaceholder')"
          v-model="state.search"
          @keyup.enter="emitSearch"
          :aria-label="__('COM_NXPEASYCART_PRODUCTS_SEARCH_PLACEHOLDER', 'Search products', [], 'productsSearchPlaceholder')"
        />
        <button class="nxp-btn" type="button" @click="emitRefresh" :disabled="state.loading">
          {{ __('COM_NXPEASYCART_PRODUCTS_REFRESH', 'Refresh', [], 'productsRefresh') }}
        </button>
      </div>
    </header>

    <div v-if="state.error" class="nxp-admin-alert nxp-admin-alert--error">
      {{ state.error }}
    </div>

    <div v-else-if="state.loading" class="nxp-admin-panel__loading">
      {{ __('COM_NXPEASYCART_PRODUCTS_LOADING', 'Loading products…', [], 'productsLoading') }}
    </div>

    <ProductTable
      v-else
      :items="state.items"
      :translate="__"
    />
  </section>
</template>

<script setup>
import ProductTable from './ProductTable.vue';

const props = defineProps({
  state: {
    type: Object,
    required: true,
  },
  translate: {
    type: Function,
    required: true,
  },
});

const __ = props.translate;

const emit = defineEmits(['refresh', 'search']);

const emitRefresh = () => {
  emit('refresh');
};

const emitSearch = () => {
  emit('search');
};
</script>
