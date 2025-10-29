<template>
  <table class="nxp-admin-table" aria-describedby="nxp-products-caption">
    <caption id="nxp-products-caption" class="visually-hidden">
      {{ __('COM_NXPEASYCART_MENU_PRODUCTS', 'Products', [], 'productsPanelTitle') }}
    </caption>
    <thead>
      <tr>
        <th scope="col">{{ __('JGRID_HEADING_ID', 'ID') }}</th>
        <th scope="col">{{ __('JGLOBAL_TITLE', 'Title') }}</th>
        <th scope="col">{{ __('JFIELD_ALIAS_LABEL', 'Slug') }}</th>
        <th scope="col">{{ __('JSTATUS', 'Status') }}</th>
        <th scope="col">{{ __('JGLOBAL_CREATED_DATE', 'Created') }}</th>
        <th scope="col" class="nxp-admin-table__actions">
          {{ __('JGLOBAL_ACTIONS', 'Actions') }}
        </th>
      </tr>
    </thead>
    <tbody>
      <tr v-if="!items.length">
        <td colspan="5" class="nxp-admin-table__empty">
          {{ __('COM_NXPEASYCART_PRODUCTS_EMPTY', 'No products found.', [], 'productsEmpty') }}
        </td>
      </tr>
      <tr v-for="item in items" :key="item.id">
        <td>{{ item.id }}</td>
        <td>{{ item.title }}</td>
        <td>{{ item.slug }}</td>
        <td>
          <span :class="['nxp-status', item.active ? 'nxp-status--active' : 'nxp-status--inactive']">
            {{ item.active
              ? __('COM_NXPEASYCART_STATUS_ACTIVE', 'Active', [], 'statusActive')
              : __('COM_NXPEASYCART_STATUS_INACTIVE', 'Inactive', [], 'statusInactive')
            }}
          </span>
        </td>
        <td>{{ item.created }}</td>
        <td class="nxp-admin-table__actions">
          <button
            class="nxp-btn nxp-btn--link"
            type="button"
            @click="$emit('edit', item)"
          >
            {{ __('JGLOBAL_EDIT', 'Edit') }}
          </button>
          <button
            class="nxp-btn nxp-btn--link nxp-btn--danger"
            type="button"
            @click="$emit('delete', item.id)"
          >
            {{ __('JTRASH', 'Delete') }}
          </button>
        </td>
      </tr>
    </tbody>
  </table>
</template>

<script setup>
const props = defineProps({
  items: {
    type: Array,
    default: () => [],
  },
  translate: {
    type: Function,
    required: true,
  },
});

defineEmits(['edit', 'delete']);

const __ = props.translate;
</script>
