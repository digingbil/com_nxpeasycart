import { onMounted, reactive, ref } from 'vue';
import { createApiClient } from '../../api.js';

export function useLogs({ endpoints, token, preload = {} }) {
  const api = createApiClient({ token });

  const listEndpoint = endpoints?.list ?? '';

  const state = reactive({
    loading: false,
    error: '',
    items: Array.isArray(preload.items) ? preload.items : [],
    pagination: {
      total: preload.pagination?.total ?? (preload.items?.length ?? 0),
      limit: preload.pagination?.limit ?? 20,
      pages: preload.pagination?.pages ?? 0,
      current: preload.pagination?.current ?? 1,
    },
    search: '',
    entity: '',
  });

  const abortSupported = typeof AbortController !== 'undefined';
  const abortRef = ref(null);

  const loadLogs = async () => {
    if (!listEndpoint) {
      state.error = 'Logs endpoint unavailable.';
      state.items = [];
      return;
    }

    state.loading = true;
    state.error = '';

    if (abortSupported && abortRef.value) {
      abortRef.value.abort();
    }

    const controller = abortSupported ? new AbortController() : null;
    abortRef.value = controller;

    try {
      const start = Math.max(0, (state.pagination.current - 1) * state.pagination.limit);
      const { items, pagination } = await api.fetchLogs({
        endpoint: listEndpoint,
        limit: state.pagination.limit,
        start,
        search: state.search.trim(),
        entity: state.entity,
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
    loadLogs();
  };

  const searchLogs = () => {
    state.pagination.current = 1;
    loadLogs();
  };

  const setEntity = (entity) => {
    const value = typeof entity === 'string' ? entity.trim() : '';

    if (state.entity === value) {
      return;
    }

    state.entity = value;
    state.pagination.current = 1;
    loadLogs();
  };

  const goToPage = (page) => {
    const target = Number(page);

    if (Number.isNaN(target) || target < 1 || target === state.pagination.current) {
      return;
    }

    state.pagination.current = target;
    loadLogs();
  };

  onMounted(() => {
    if (!Array.isArray(state.items) || !state.items.length) {
      loadLogs();
    }
  });

  return {
    state,
    loadLogs,
    refresh,
    search: searchLogs,
    setEntity,
    goToPage,
  };
}

export default useLogs;
