import { onMounted, reactive, ref } from 'vue';
import { createApiClient } from '../../api.js';

const deriveEndpoint = (listEndpoint, action) => {
  if (!listEndpoint) {
    return '';
  }

  const origin = typeof window !== 'undefined' ? window.location.origin : 'http://localhost';
  const url = new URL(listEndpoint, origin);
  url.searchParams.set('task', `api.orders.${action}`);

  return `${url.pathname}?${url.searchParams.toString()}`;
};

export function useOrders({ endpoints, token, states = [] }) {
  const api = createApiClient({ token });

  const listEndpoint = endpoints?.list ?? '';
  const showEndpoint = endpoints?.show ?? deriveEndpoint(listEndpoint, 'show');
  const transitionEndpoint = endpoints?.transition ?? deriveEndpoint(listEndpoint, 'transition');

  const abortSupported = typeof AbortController !== 'undefined';
  const abortRef = ref(null);

  const state = reactive({
    loading: false,
    saving: false,
    error: '',
    transitionError: '',
    items: [],
    pagination: {
      total: 0,
      limit: 20,
      pages: 0,
      current: 1,
    },
    search: '',
    filterState: '',
    orderStates: Array.isArray(states) && states.length ? states : ['pending', 'paid', 'fulfilled', 'refunded', 'canceled'],
    activeOrder: null,
  });

  const loadOrders = async () => {
    if (!listEndpoint) {
      state.error = 'Orders endpoint unavailable.';
      state.items = [];

      return;
    }

    state.loading = true;
    state.error = '';

    if (abortRef.value && abortSupported) {
      abortRef.value.abort();
    }

    const controller = abortSupported ? new AbortController() : null;
    abortRef.value = controller;

    try {
      const start = Math.max(0, (state.pagination.current - 1) * state.pagination.limit);
      const { items, pagination } = await api.fetchOrders({
        endpoint: listEndpoint,
        limit: state.pagination.limit,
        start,
        search: state.search.trim(),
        state: state.filterState,
        signal: controller ? controller.signal : undefined,
      });

      state.items = items;
      state.pagination = {
        ...state.pagination,
        ...pagination,
        current: pagination.current && pagination.current > 0 ? pagination.current : 1,
      };
    } catch (error) {
      if (error?.name === 'AbortError') {
        return;
      }

      state.error = error?.message ?? 'Unknown error';
    } finally {
      if (abortSupported && abortRef.value === controller) {
        abortRef.value = null;
      }

      state.loading = false;
    }
  };

  const refresh = () => {
    state.pagination.current = 1;
    loadOrders();
  };

  const search = () => {
    state.pagination.current = 1;
    loadOrders();
  };

  const setFilterState = (value) => {
    state.filterState = value || '';
    state.pagination.current = 1;
    loadOrders();
  };

  const goToPage = (page) => {
    const target = Number(page);

    if (Number.isNaN(target) || target < 1 || target === state.pagination.current) {
      return;
    }

    state.pagination.current = target;
    loadOrders();
  };

  const nextPage = () => {
    if (state.pagination.current >= state.pagination.pages) {
      return;
    }

    state.pagination.current += 1;
    loadOrders();
  };

  const previousPage = () => {
    if (state.pagination.current <= 1) {
      return;
    }

    state.pagination.current -= 1;
    loadOrders();
  };

  const fetchOrder = async (id, orderNo = '') => {
    if (!showEndpoint) {
      return null;
    }

    try {
      return await api.fetchOrder({
        endpoint: showEndpoint,
        id,
        orderNumber: orderNo,
      });
    } catch (error) {
      state.transitionError = error?.message ?? 'Unknown error';

      return null;
    }
  };

  const viewOrder = async (order) => {
    if (!order) {
      state.activeOrder = null;

      return;
    }

    state.transitionError = '';

    if (order.items && order.items.length && order.billing) {
      state.activeOrder = order;

      return;
    }

    const detailed = await fetchOrder(order.id, order.order_no);

    state.activeOrder = detailed || order;
  };

  const closeOrder = () => {
    state.transitionError = '';
    state.activeOrder = null;
  };

  const updateOrderList = (order) => {
    if (!order?.id) {
      return;
    }

    const index = state.items.findIndex((existing) => existing.id === order.id);

    if (index !== -1) {
      state.items.splice(index, 1, order);
    }
  };

  const transitionOrder = async (id, nextState) => {
    if (!transitionEndpoint) {
      throw new Error('Transition endpoint unavailable.');
    }

    state.saving = true;
    state.transitionError = '';

    try {
      const updated = await api.transitionOrder({
        endpoint: transitionEndpoint,
        id,
        state: nextState,
      });

      if (updated) {
        updateOrderList(updated);

        if (state.activeOrder && state.activeOrder.id === updated.id) {
          state.activeOrder = updated;
        }
      }

      return updated;
    } catch (error) {
      state.transitionError = error?.message ?? 'Unknown error';

      throw error;
    } finally {
      state.saving = false;
    }
  };

  onMounted(() => {
    loadOrders();
  });

  return {
    state,
    loadOrders,
    refresh,
    search,
    setFilterState,
    goToPage,
    nextPage,
    previousPage,
    viewOrder,
    closeOrder,
    transitionOrder,
  };
}

export default useOrders;
