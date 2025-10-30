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
        <button class="nxp-btn nxp-btn--primary" type="button" @click="openCreate">
          {{ __('COM_NXPEASYCART_PRODUCTS_ADD', 'Add product') }}
        </button>
      </div>
    </header>

    <div v-if="state.error" class="nxp-admin-alert nxp-admin-alert--error">
      {{ state.error }}
    </div>

    <div v-else-if="state.loading" class="nxp-admin-panel__loading">
      {{ __('COM_NXPEASYCART_PRODUCTS_LOADING', 'Loading products…', [], 'productsLoading') }}
    </div>

    <div v-else class="nxp-admin-panel__body">
      <ProductTable
        :items="state.items"
        :translate="__"
        :base-currency="baseCurrency"
        @edit="openEdit"
        @delete="confirmDelete"
      />
    </div>

    <ProductEditor
      :open="isEditorOpen"
      :product="editorProduct"
      :saving="state.saving"
      :base-currency="baseCurrency"
      :translate="__"
      :errors="state.validationErrors"
      @submit="handleSubmit"
      @cancel="closeEditor"
    />
  </section>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import ProductTable from './ProductTable.vue';
import ProductEditor from './ProductEditor.vue';

const props = defineProps({
  state: {
    type: Object,
    required: true,
  },
  translate: {
    type: Function,
    required: true,
  },
  baseCurrency: {
    type: String,
    default: 'USD',
  },
});

const emit = defineEmits(['create', 'update', 'delete', 'refresh', 'search']);

const __ = props.translate;

const editorState = reactive({
  mode: 'create',
  product: null,
});

const isEditorOpen = ref(false);

const baseCurrency = computed(() => (props.baseCurrency || 'USD').toUpperCase());

const editorProduct = computed(() => editorState.product);

const openCreate = () => {
  props.state.validationErrors = [];
  props.state.error = '';
  editorState.mode = 'create';
  editorState.product = {
    title: '',
    slug: '',
    short_desc: '',
    long_desc: '',
    active: true,
    images: [],
    categories: [],
    variants: [],
  };
  isEditorOpen.value = true;
};

const openEdit = (product) => {
  props.state.validationErrors = [];
  props.state.error = '';
  editorState.mode = 'edit';
  editorState.product = JSON.parse(JSON.stringify(product));
  isEditorOpen.value = true;
};

const closeEditor = () => {
  isEditorOpen.value = false;
  props.state.validationErrors = [];
};

const handleSubmit = async (payload) => {
  const data = {
    ...payload,
    active: payload.active ? 1 : 0,
  };

  if (editorState.mode === 'edit' && editorState.product?.id) {
    emit('update', { id: editorState.product.id, data });
  } else {
    emit('create', data);
  }
};

const confirmDelete = async (product) => {
  const name = product?.title ?? '';
  const message = __('COM_NXPEASYCART_PRODUCTS_DELETE_CONFIRM_NAME', 'Delete "%s"?', [name || '#']);

  if (window.confirm(message)) {
    emit('delete', [product.id]);
  }
};

const emitRefresh = () => {
  emit('refresh');
};

const emitSearch = () => {
  emit('search');
};

watch(
  () => props.state.saving,
  (saving, wasSaving) => {
    if (wasSaving && !saving && isEditorOpen.value) {
      if (!props.state.validationErrors.length && !props.state.error) {
        closeEditor();
      }
    }
  }
);
</script>
