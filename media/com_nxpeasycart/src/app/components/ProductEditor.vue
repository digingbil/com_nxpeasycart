<template>
  <div v-if="open" class="nxp-modal" role="dialog" aria-modal="true">
    <div class="nxp-modal__backdrop"></div>
    <div class="nxp-modal__dialog">
      <header class="nxp-modal__header">
        <h2 class="nxp-modal__title">
          {{ mode === 'edit'
            ? __('COM_NXPEASYCART_PRODUCTS_EDIT', 'Edit product')
            : __('COM_NXPEASYCART_PRODUCTS_ADD', 'Add product')
          }}
        </h2>
        <button type="button" class="nxp-modal__close" @click="cancel" aria-label="Close">
          &times;
        </button>
      </header>

      <form class="nxp-form" @submit.prevent="submit">
        <div v-if="errors.length" class="nxp-admin-alert nxp-admin-alert--error">
          <p v-for="(error, index) in errors" :key="index">{{ error }}</p>
        </div>

        <div class="nxp-form-field">
          <label class="nxp-form-label" for="product-title">
            {{ __('COM_NXPEASYCART_FIELD_PRODUCT_TITLE', 'Title') }}
          </label>
          <input
            id="product-title"
            class="nxp-form-input"
            type="text"
            v-model.trim="form.title"
            required
          />
        </div>

        <div class="nxp-form-field">
          <label class="nxp-form-label" for="product-slug">
            {{ __('COM_NXPEASYCART_FIELD_PRODUCT_SLUG', 'Slug') }}
          </label>
          <input
            id="product-slug"
            class="nxp-form-input"
            type="text"
            v-model.trim="form.slug"
            placeholder="auto-generated"
          />
        </div>

        <div class="nxp-form-field">
          <label class="nxp-form-label" for="product-short-desc">
            {{ __('COM_NXPEASYCART_FIELD_PRODUCT_SHORT_DESC', 'Short description') }}
          </label>
          <textarea
            id="product-short-desc"
            class="nxp-form-textarea"
            rows="3"
            v-model="form.short_desc"
          ></textarea>
        </div>

        <div class="nxp-form-field">
          <label class="nxp-form-label" for="product-long-desc">
            {{ __('COM_NXPEASYCART_FIELD_PRODUCT_LONG_DESC', 'Long description') }}
          </label>
          <textarea
            id="product-long-desc"
            class="nxp-form-textarea"
            rows="5"
            v-model="form.long_desc"
          ></textarea>
        </div>

        <div class="nxp-form-field nxp-form-field--inline">
          <label class="nxp-form-label" for="product-active">
            {{ __('COM_NXPEASYCART_FIELD_PRODUCT_ACTIVE', 'Published') }}
          </label>
          <input
            id="product-active"
            type="checkbox"
            class="nxp-form-checkbox"
            v-model="form.active"
          />
        </div>

        <footer class="nxp-modal__actions">
          <button type="button" class="nxp-btn" @click="cancel" :disabled="saving">
            {{ __('JCANCEL', 'Cancel') }}
          </button>
          <button type="submit" class="nxp-btn nxp-btn--primary" :disabled="saving">
            {{ saving ? __('JPROCESSING_REQUEST', 'Saving…') : __('JSAVE', 'Save') }}
          </button>
        </footer>
      </form>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, watch } from 'vue';

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  product: {
    type: Object,
    default: () => null,
  },
  saving: {
    type: Boolean,
    default: false,
  },
  translate: {
    type: Function,
    required: true,
  },
  errors: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(['submit', 'cancel']);

const __ = props.translate;

const blank = () => ({
  title: '',
  slug: '',
  short_desc: '',
  long_desc: '',
  active: true,
});

const form = reactive(blank());

const mode = computed(() => (props.product && props.product.id ? 'edit' : 'create'));

watch(
  () => props.product,
  (product) => {
    const source = product || blank();

    form.title = source.title ?? '';
    form.slug = source.slug ?? '';
    form.short_desc = source.short_desc ?? '';
    form.long_desc = source.long_desc ?? '';
    form.active = source.active ?? true;
  },
  { immediate: true }
);

const submit = () => {
  emit('submit', { ...form });
};

const cancel = () => {
  emit('cancel');
};
</script>
