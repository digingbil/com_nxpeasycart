<template>
  <section class="nxp-admin-panel nxp-admin-panel--logs">
    <header class="nxp-admin-panel__header">
      <div>
        <h2 class="nxp-admin-panel__title">
          {{ __('COM_NXPEASYCART_MENU_LOGS', 'Logs', [], 'logsPanelTitle') }}
        </h2>
        <p class="nxp-admin-panel__lead">
          {{ __('COM_NXPEASYCART_LOGS_LEAD', 'Review audit events across the store.', [], 'logsPanelLead') }}
        </p>
      </div>
      <div class="nxp-admin-panel__actions">
        <input
          type="search"
          class="nxp-admin-search"
          v-model="state.search"
          :placeholder="__('COM_NXPEASYCART_LOGS_SEARCH_PLACEHOLDER', 'Search logs', [], 'logsSearchPlaceholder')"
          :aria-label="__('COM_NXPEASYCART_LOGS_SEARCH_PLACEHOLDER', 'Search logs', [], 'logsSearchPlaceholder')"
          @keyup.enter="emitSearch"
        />
        <select
          class="nxp-form-select nxp-admin-select"
          :value="state.entity"
          @change="emitFilter($event.target.value)"
          aria-label="Entity filter"
        >
          <option value="">
            {{ __('COM_NXPEASYCART_LOGS_FILTER_ALL', 'All entities', [], 'logsFilterAll') }}
          </option>
          <option
            v-for="entity in entityOptions"
            :key="entity"
            :value="entity"
          >
            {{ formatEntity(entity) }}
          </option>
        </select>
        <button class="nxp-btn" type="button" @click="emitRefresh" :disabled="state.loading">
          {{ __('COM_NXPEASYCART_COUPONS_REFRESH', 'Refresh', [], 'couponsRefresh') }}
        </button>
      </div>
    </header>

    <div v-if="state.error" class="nxp-admin-alert nxp-admin-alert--error">
      {{ state.error }}
    </div>

    <div v-else-if="state.loading" class="nxp-admin-panel__loading">
      {{ __('COM_NXPEASYCART_LOGS_LOADING', 'Loading logs…', [], 'logsLoading') }}
    </div>

    <div v-else class="nxp-admin-panel__body">
      <div class="nxp-admin-panel__table">
        <table class="nxp-admin-table">
          <thead>
            <tr>
              <th scope="col">{{ __('COM_NXPEASYCART_LOGS_TABLE_TIME', 'Time', [], 'logsTableTime') }}</th>
              <th scope="col">{{ __('COM_NXPEASYCART_LOGS_TABLE_ENTITY', 'Entity', [], 'logsTableEntity') }}</th>
              <th scope="col">{{ __('COM_NXPEASYCART_LOGS_TABLE_ACTION', 'Action', [], 'logsTableAction') }}</th>
              <th scope="col">{{ __('COM_NXPEASYCART_LOGS_TABLE_ACTOR', 'Actor', [], 'logsTableActor') }}</th>
              <th scope="col">{{ __('COM_NXPEASYCART_LOGS_TABLE_DETAILS', 'Details', [], 'logsTableDetails') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!state.items.length">
              <td colspan="5">
                {{ __('COM_NXPEASYCART_LOGS_EMPTY', 'No audit events captured yet.', [], 'logsEmpty') }}
              </td>
            </tr>
            <tr v-for="log in state.items" :key="log.id">
              <td>{{ formatTimestamp(log.created) }}</td>
              <td>
                <span class="nxp-log-entity">{{ formatEntity(log.entity_type) }}</span>
                <span class="nxp-log-entity-id">#{{ log.entity_id }}</span>
              </td>
              <td>{{ log.action }}</td>
              <td>{{ formatActor(log) }}</td>
              <td>
                <pre class="nxp-log-context">{{ formatContext(log.context) }}</pre>
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
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue';

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

const emit = defineEmits(['refresh', 'search', 'filter', 'page']);

const __ = props.translate;

const state = props.state;

const entityOptions = computed(() => {
  const unique = new Set();

  if (Array.isArray(state.items)) {
    state.items.forEach((item) => {
      if (item?.entity_type) {
        unique.add(item.entity_type);
      }
    });
  }

  if (state.entity) {
    unique.add(state.entity);
  }

  return Array.from(unique).sort();
});

const emitRefresh = () => emit('refresh');
const emitSearch = () => emit('search');
const emitFilter = (value) => emit('filter', value);
const emitPage = (page) => emit('page', page);

const formatTimestamp = (value) => {
  if (!value) {
    return '';
  }

  try {
    const date = new Date(value.replace(' ', 'T'));

    if (!Number.isNaN(date.getTime())) {
      return date.toLocaleString();
    }
  } catch (error) {
    // Ignore parsing errors and fall back to raw value.
  }

  return value;
};

const formatEntity = (entity) => {
  if (!entity) {
    return __('COM_NXPEASYCART_LOGS_FILTER_UNKNOWN', 'Unknown', [], 'logsFilterUnknown');
  }

  switch (entity) {
    case 'order':
      return __('COM_NXPEASYCART_MENU_ORDERS', 'Orders');
    case 'product':
      return __('COM_NXPEASYCART_MENU_PRODUCTS', 'Products');
    case 'coupon':
      return __('COM_NXPEASYCART_MENU_COUPONS', 'Coupons');
    case 'customer':
      return __('COM_NXPEASYCART_MENU_CUSTOMERS', 'Customers');
    default:
      return entity.charAt(0).toUpperCase() + entity.slice(1);
  }
};

const formatActor = (log) => {
  if (!log?.user) {
    return __('COM_NXPEASYCART_LOGS_ACTOR_UNKNOWN', 'System', [], 'logsActorUnknown');
  }

  const name = log.user.name || '';
  const username = log.user.username || '';

  if (name && username) {
    return `${name} (${username})`;
  }

  if (name) {
    return name;
  }

  if (username) {
    return username;
  }

  return __('COM_NXPEASYCART_LOGS_ACTOR_UNKNOWN', 'System', [], 'logsActorUnknown');
};

const formatContext = (context) => {
  if (!context || (Array.isArray(context) && !context.length) || (typeof context === 'object' && !Object.keys(context).length)) {
    return '—';
  }

  try {
    return JSON.stringify(context, null, 2);
  } catch (error) {
    return String(context);
  }
};
</script>

<style scoped>
.nxp-admin-panel--logs .nxp-admin-panel__table {
  overflow-x: auto;
}

.nxp-admin-panel--logs table {
  min-width: 720px;
}

.nxp-log-context {
  margin: 0;
  font-family: var(--nxp-font-mono, ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace);
  font-size: 0.8rem;
  white-space: pre-wrap;
  word-break: break-word;
}

.nxp-log-entity {
  display: inline-block;
  font-weight: 600;
}

.nxp-log-entity-id {
  display: inline-block;
  margin-left: 0.25rem;
  color: #475467;
}

.nxp-admin-select {
  min-width: 160px;
}
</style>
