<template>
  <section class="nxp-admin-panel nxp-admin-panel--orders">
    <header class="nxp-admin-panel__header">
      <div>
        <h2 class="nxp-admin-panel__title">
          {{ __('COM_NXPEASYCART_MENU_ORDERS', 'Orders', [], 'ordersPanelTitle') }}
        </h2>
        <p class="nxp-admin-panel__lead">
          {{ __('COM_NXPEASYCART_ORDERS_LEAD', 'Track orders and manage fulfilment from this screen.', [], 'ordersPanelLead') }}
        </p>
      </div>
      <div class="nxp-admin-panel__actions">
        <input
          type="search"
          class="nxp-admin-search"
          :placeholder="__('COM_NXPEASYCART_ORDERS_SEARCH_PLACEHOLDER', 'Search orders', [], 'ordersSearchPlaceholder')"
          v-model="state.search"
          @keyup.enter="emitSearch"
          :aria-label="__('COM_NXPEASYCART_ORDERS_SEARCH_PLACEHOLDER', 'Search orders', [], 'ordersSearchPlaceholder')"
        />
        <select
          class="nxp-admin-select"
          v-model="state.filterState"
          @change="emitFilter"
          :aria-label="__('COM_NXPEASYCART_ORDERS_FILTER_STATE', 'Filter by state', [], 'ordersFilterState')"
        >
          <option value="">— {{ __('COM_NXPEASYCART_ORDERS_FILTER_STATE', 'State', [], 'ordersFilterState') }} —</option>
          <option
            v-for="option in state.orderStates"
            :key="option"
            :value="option"
          >
            {{ stateLabel(option) }}
          </option>
        </select>
        <button class="nxp-btn" type="button" @click="emitRefresh" :disabled="state.loading">
          {{ __('COM_NXPEASYCART_ORDERS_REFRESH', 'Refresh', [], 'ordersRefresh') }}
        </button>
      </div>
    </header>

    <div v-if="state.error" class="nxp-admin-alert nxp-admin-alert--error">
      {{ state.error }}
    </div>

    <div v-else-if="state.loading" class="nxp-admin-panel__loading">
      {{ __('COM_NXPEASYCART_ORDERS_LOADING', 'Loading orders…', [], 'ordersLoading') }}
    </div>

    <div v-else class="nxp-admin-panel__body nxp-admin-panel__body--orders">
      <div class="nxp-admin-panel__table">
        <div class="nxp-admin-panel__selection" v-if="hasSelection">
          <span>{{ selectionSummary }}</span>
          <select
            class="nxp-admin-select"
            v-model="bulkState"
            :aria-label="__('COM_NXPEASYCART_ORDERS_BULK_STATE', 'Select target state', [], 'ordersBulkState')"
          >
            <option value="">
              {{ __('COM_NXPEASYCART_ORDERS_BULK_STATE_PLACEHOLDER', 'Choose state…', [], 'ordersBulkStatePlaceholder') }}
            </option>
            <option
              v-for="option in state.orderStates"
              :key="`bulk-${option}`"
              :value="option"
            >
              {{ stateLabel(option) }}
            </option>
          </select>
          <button
            class="nxp-btn nxp-btn--primary"
            type="button"
            :disabled="!bulkState || state.saving"
            @click="emitBulkTransition"
          >
            {{ __('COM_NXPEASYCART_ORDERS_BULK_APPLY', 'Apply', [], 'ordersBulkApply') }}
          </button>
          <button
            class="nxp-link-button"
            type="button"
            @click="emitClearSelection"
          >
            {{ __('COM_NXPEASYCART_ORDERS_CLEAR_SELECTION', 'Clear', [], 'ordersClearSelection') }}
          </button>
        </div>

        <table class="nxp-admin-table">
          <thead>
            <tr>
              <th scope="col" class="nxp-admin-table__select">
                {{ __('COM_NXPEASYCART_ORDERS_TABLE_SELECT', 'Select', [], 'ordersTableSelect') }}
              </th>
              <th scope="col">{{ __('COM_NXPEASYCART_ORDERS_TABLE_ORDER', 'Order', [], 'ordersTableOrder') }}</th>
              <th scope="col">{{ __('COM_NXPEASYCART_ORDERS_TABLE_CUSTOMER', 'Customer', [], 'ordersTableCustomer') }}</th>
              <th scope="col">{{ __('COM_NXPEASYCART_ORDERS_TABLE_TOTAL', 'Total', [], 'ordersTableTotal') }}</th>
              <th scope="col">{{ __('COM_NXPEASYCART_ORDERS_TABLE_STATE', 'State', [], 'ordersTableState') }}</th>
              <th scope="col">{{ __('COM_NXPEASYCART_ORDERS_TABLE_UPDATED', 'Updated', [], 'ordersTableUpdated') }}</th>
              <th scope="col" class="nxp-admin-table__actions">
                {{ __('COM_NXPEASYCART_ORDERS_CHANGE_STATE', 'Change state', [], 'ordersChangeState') }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!state.items.length">
              <td colspan="7">
                {{ __('COM_NXPEASYCART_ORDERS_EMPTY', 'No orders found.', [], 'ordersEmpty') }}
              </td>
            </tr>
            <tr
              v-for="order in state.items"
              :key="order.id"
              :class="{ 'is-active': state.activeOrder && state.activeOrder.id === order.id }"
            >
              <td class="nxp-admin-table__select">
                <input
                  type="checkbox"
                  class="nxp-admin-checkbox"
                  :checked="isSelected(order.id)"
                  @change="emitToggleSelection(order.id)"
                  :aria-label="__('COM_NXPEASYCART_ORDERS_SELECT_ORDER', 'Select order', [], 'ordersSelectOrder')"
                />
              </td>
              <th scope="row">
                <button
                  class="nxp-link-button"
                  type="button"
                  @click="emitView(order)"
                >
                  {{ order.order_no }}
                </button>
              </th>
              <td>
                <div>{{ order.email }}</div>
                <div class="nxp-admin-table__meta">
                  {{ itemsLabel(order.items?.length ?? 0) }}
                </div>
              </td>
              <td>{{ formatCurrency(order.total_cents, order.currency) }}</td>
              <td>
                <span class="nxp-badge">
                  {{ stateLabel(order.state) }}
                </span>
              </td>
              <td>{{ formatDate(order.modified || order.created) }}</td>
              <td class="nxp-admin-table__actions">
                <select
                  class="nxp-admin-select"
                  v-model="selections[order.id]"
                  :aria-label="__('COM_NXPEASYCART_ORDERS_CHANGE_STATE', 'Change state', [], 'ordersChangeState')"
                >
                  <option :value="order.state">
                    {{ stateLabel(order.state) }}
                  </option>
                  <option
                    v-for="option in nextStates(order)"
                    :key="option"
                    :value="option"
                  >
                    {{ stateLabel(option) }}
                  </option>
                </select>
                <button
                  class="nxp-btn"
                  type="button"
                  :disabled="state.saving || !hasStateChanged(order)"
                  @click="emitTransition(order)"
                >
                  {{ __('COM_NXPEASYCART_ORDERS_TRANSITIONS', 'State transitions', [], 'ordersStateTransitions') }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <div class="nxp-admin-pagination" v-if="state.pagination.pages > 1">
          <button
            class="nxp-btn"
            type="button"
            :disabled="state.pagination.current <= 1"
            @click="emitPage(state.pagination.current - 1)"
          >
            ‹
          </button>
          <span class="nxp-admin-pagination__status">
            {{ state.pagination.current }} / {{ state.pagination.pages }}
          </span>
          <button
            class="nxp-btn"
            type="button"
            :disabled="state.pagination.current >= state.pagination.pages"
            @click="emitPage(state.pagination.current + 1)"
          >
            ›
          </button>
        </div>
      </div>

      <aside
        v-if="state.activeOrder"
        class="nxp-admin-panel__sidebar"
        aria-live="polite"
      >
        <header class="nxp-admin-panel__sidebar-header">
          <h3>
            {{ __('COM_NXPEASYCART_ORDERS_DETAILS_TITLE', 'Order details', [], 'ordersDetailsTitle') }} · {{ state.activeOrder.order_no }}
          </h3>
          <button class="nxp-link-button" type="button" @click="emitClose">
            {{ __('COM_NXPEASYCART_ORDERS_DETAILS_CLOSE', 'Close details', [], 'ordersDetailsClose') }}
          </button>
        </header>

        <div v-if="state.transitionError" class="nxp-admin-alert nxp-admin-alert--error">
          {{ state.transitionError }}
        </div>

        <section class="nxp-admin-panel__section">
          <h4>{{ __('COM_NXPEASYCART_ORDERS_ITEMS_LABEL', 'Items', [], 'ordersItemsLabel') }}</h4>
          <ul class="nxp-admin-list">
            <li v-for="item in state.activeOrder.items" :key="item.id">
              <div class="nxp-admin-list__title">
                {{ item.title }} <small>({{ item.sku }})</small>
              </div>
              <div class="nxp-admin-list__meta">
                × {{ item.qty }} · {{ formatCurrency(item.unit_price_cents, state.activeOrder.currency) }}
              </div>
            </li>
          </ul>
        </section>

        <section class="nxp-admin-panel__section">
          <h4>{{ __('COM_NXPEASYCART_ORDERS_TOTAL_LABEL', 'Total', [], 'ordersTotalLabel') }}</h4>
          <p class="nxp-admin-panel__total">
            {{ formatCurrency(state.activeOrder.total_cents, state.activeOrder.currency) }}
          </p>
        </section>

        <section class="nxp-admin-panel__section">
          <h4>{{ __('COM_NXPEASYCART_ORDERS_BILLING_LABEL', 'Billing', [], 'ordersBillingLabel') }}</h4>
          <address class="nxp-admin-address">
            <span v-for="line in addressLines(state.activeOrder.billing)" :key="line.key">
              {{ line.value }}
            </span>
          </address>
        </section>

        <section class="nxp-admin-panel__section">
          <h4>{{ __('COM_NXPEASYCART_ORDERS_SHIPPING_LABEL', 'Shipping', [], 'ordersShippingLabel') }}</h4>
          <address class="nxp-admin-address" v-if="state.activeOrder.shipping">
            <span v-for="line in addressLines(state.activeOrder.shipping)" :key="line.key">
              {{ line.value }}
            </span>
          </address>
          <p v-else class="nxp-admin-panel__muted">
            {{ __('COM_NXPEASYCART_ORDERS_NO_SHIPPING', 'Shipping information not provided.', [], 'ordersNoShipping') }}
          </p>
        </section>

        <section
          class="nxp-admin-panel__section"
          v-if="state.activeOrder.transactions && state.activeOrder.transactions.length"
        >
          <h4>{{ __('COM_NXPEASYCART_ORDERS_TRANSACTIONS_LABEL', 'Payments', [], 'ordersTransactionsLabel') }}</h4>
          <ul class="nxp-admin-list">
            <li v-for="transaction in state.activeOrder.transactions" :key="transaction.id">
              <div class="nxp-admin-list__title">
                {{ transaction.gateway }} · {{ formatCurrency(transaction.amount_cents, state.activeOrder.currency) }}
              </div>
              <div class="nxp-admin-list__meta">
                {{ transactionStatusLabel(transaction) }} · {{ formatDate(transaction.created) }}
              </div>
            </li>
          </ul>
        </section>

        <section class="nxp-admin-panel__section">
          <h4>{{ __('COM_NXPEASYCART_ORDERS_NOTE_LABEL', 'Add note', [], 'ordersNoteLabel') }}</h4>
          <form class="nxp-admin-form" @submit.prevent="emitAddNote">
            <textarea
              class="nxp-form-textarea"
              rows="3"
              v-model="noteDraft"
              :placeholder="__('COM_NXPEASYCART_ORDERS_NOTE_PLACEHOLDER', 'Leave a fulfilment note…', [], 'ordersNotePlaceholder')"
            ></textarea>
            <div class="nxp-admin-form__actions">
              <button class="nxp-btn" type="submit" :disabled="!noteReady || state.saving">
                {{ __('COM_NXPEASYCART_ORDERS_NOTE_SUBMIT', 'Save note', [], 'ordersNoteSubmit') }}
              </button>
            </div>
          </form>
        </section>

        <section class="nxp-admin-panel__section">
          <h4>{{ __('COM_NXPEASYCART_ORDERS_TIMELINE_LABEL', 'History', [], 'ordersTimelineLabel') }}</h4>
          <ul class="nxp-admin-list" v-if="state.activeOrder.timeline && state.activeOrder.timeline.length">
            <li v-for="entry in state.activeOrder.timeline" :key="entry.id">
              <div class="nxp-admin-list__title">
                {{ historyLabel(entry) }}
              </div>
              <div class="nxp-admin-list__meta">
                {{ formatDate(entry.created) }}
              </div>
            </li>
          </ul>
          <p v-else class="nxp-admin-panel__muted">
            {{ __('COM_NXPEASYCART_ORDERS_TIMELINE_EMPTY', 'No history recorded yet.', [], 'ordersTimelineEmpty') }}
          </p>
        </section>
      </aside>
    </div>
  </section>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';

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

const emit = defineEmits([
  'refresh',
  'search',
  'filter',
  'view',
  'close',
  'transition',
  'page',
  'bulk-transition',
  'toggle-selection',
  'clear-selection',
  'add-note',
]);

const __ = props.translate;

const formatWithPlaceholders = (text, replacements = []) => {
  if (!replacements.length) {
    return text;
  }

  if (typeof window !== 'undefined' && window?.Joomla?.sprintf) {
    return window.Joomla.sprintf(text, ...replacements);
  }

  return replacements.reduce((accumulator, value) => accumulator.replace('%s', value), text);
};

const selections = reactive({});
const bulkState = ref('');
const noteDraft = ref('');
const noteReady = computed(() => noteDraft.value.trim().length > 0);

const selectedIds = computed(() => {
  const selection = props.state?.selection;

  if (!selection || typeof selection.values !== 'function') {
    return [];
  }

  return Array.from(selection.values());
});

const hasSelection = computed(() => selectedIds.value.length > 0);

watch(selectedIds, (ids) => {
  if (!ids.length) {
    bulkState.value = '';
  }
});

watch(
  () => props.state.activeOrder?.id,
  () => {
    noteDraft.value = '';
  }
);

watch(
  () => props.state.items,
  (items) => {
    if (!Array.isArray(items)) {
      return;
    }

    items.forEach((order) => {
      if (!order || typeof order.id === 'undefined') {
        return;
      }

      if (!selections[order.id]) {
        selections[order.id] = order.state;
      }
    });
  },
  { immediate: true }
);

const formatCurrency = (cents, currency) => {
  const amount = (Number(cents) || 0) / 100;
  const code = (currency || '').toUpperCase() || 'USD';

  try {
    return new Intl.NumberFormat(undefined, {
      style: 'currency',
      currency: code,
    }).format(amount);
  } catch (error) {
    return `${code} ${amount.toFixed(2)}`;
  }
};

const formatDate = (iso) => {
  if (!iso) {
    return '';
  }

  const date = new Date(iso);

  if (Number.isNaN(date.getTime())) {
    return iso;
  }

  return date.toLocaleString();
};

const itemsLabel = (count) => {
  if (count === 1) {
    return __('COM_NXPEASYCART_ORDERS_BADGE_ITEM', '1 item');
  }

  const template = __('COM_NXPEASYCART_ORDERS_BADGE_ITEMS', '%s items');

  return formatWithPlaceholders(template, [String(count)]);
};

const stateLabel = (state) => {
  if (!state) {
    return '';
  }

  const key = `COM_NXPEASYCART_ORDERS_STATE_${String(state).toUpperCase()}`;

  return __(key, state);
};

const nextStates = (order) =>
  props.state.orderStates.filter((candidate) => candidate !== order.state);

const hasStateChanged = (order) => (selections[order.id] || order.state) !== order.state;

const addressLines = (address) => {
  if (!address || typeof address !== 'object') {
    return [];
  }

  return Object.entries(address)
    .filter(([, value]) => value != null && `${value}`.trim() !== '')
    .map(([key, value]) => ({
      key,
      value: `${value}`.trim(),
    }));
};

const emitRefresh = () => {
  emit('refresh');
};

const emitSearch = () => {
  emit('search');
};

const emitFilter = () => {
  emit('filter', props.state.filterState);
};

const emitView = (order) => {
  emit('view', order);
};

const emitClose = () => {
  emit('close');
};

const emitTransition = (order) => {
  const targetState = selections[order.id] || order.state;

  if (targetState === order.state) {
    return;
  }

  emit('transition', { id: order.id, state: targetState });
};

const emitPage = (page) => {
  emit('page', page);
};

const isSelected = (orderId) => props.state?.selection?.has?.(orderId) ?? false;

const emitToggleSelection = (orderId) => {
  emit('toggle-selection', orderId);
};

const emitClearSelection = () => {
  bulkState.value = '';
  emit('clear-selection');
};

const emitBulkTransition = () => {
  if (!hasSelection.value || !bulkState.value) {
    return;
  }

  emit('bulk-transition', {
    ids: [...selectedIds.value],
    state: bulkState.value,
  });
};

const emitAddNote = () => {
  if (!props.state.activeOrder) {
    return;
  }

  const message = noteDraft.value.trim();

  if (!message) {
    return;
  }

  emit('add-note', {
    id: props.state.activeOrder.id,
    message,
  });

  noteDraft.value = '';
};

const selectionSummary = computed(() =>
  formatWithPlaceholders(
    __('COM_NXPEASYCART_ORDERS_SELECTED_COUNT', '%s selected', [], 'ordersSelectedCount'),
    [String(selectedIds.value.length)]
  )
);

const historyLabel = (entry) => {
  if (!entry || !entry.action) {
    return '';
  }

  switch (entry.action) {
    case 'order.created':
      return __('COM_NXPEASYCART_ORDERS_TIMELINE_CREATED', 'Order created', [], 'ordersTimelineCreated');
    case 'order.state.transitioned': {
      const from = stateLabel(entry.context?.from ?? '');
      const to = stateLabel(entry.context?.to ?? '');
      return formatWithPlaceholders(
        __('COM_NXPEASYCART_ORDERS_TIMELINE_STATE', 'State changed from %s to %s', [], 'ordersTimelineState'),
        [from || entry.context?.from || '', to || entry.context?.to || '']
      );
    }
    case 'order.note':
      return (
        entry.context?.message
        || __('COM_NXPEASYCART_ORDERS_TIMELINE_NOTE', 'Note added', [], 'ordersTimelineNote')
      );
    default:
      return entry.action;
  }
};

const transactionStatusLabel = (transaction) =>
  __(
    `COM_NXPEASYCART_TRANSACTION_STATUS_${String(transaction.status || '').toUpperCase()}`,
    transaction.status || ''
  );
</script>

<style scoped>
.nxp-admin-panel__selection {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.nxp-admin-table__select {
  width: 3rem;
  text-align: center;
}

.nxp-admin-table__select .nxp-admin-checkbox {
  margin: 0 auto;
}

.nxp-admin-checkbox {
  width: 1rem;
  height: 1rem;
}

.nxp-admin-panel__section .nxp-admin-list {
  gap: 0.5rem;
}

.nxp-admin-form {
  display: grid;
  gap: 0.75rem;
}

.nxp-admin-form__actions {
  display: flex;
  justify-content: flex-end;
}
</style>
