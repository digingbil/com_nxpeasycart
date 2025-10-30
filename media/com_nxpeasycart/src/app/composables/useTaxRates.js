import { onMounted, reactive, ref } from 'vue';
import { createApiClient } from '../../api.js';

export function useTaxRates({ endpoints, token, preload = {} }) {
  const api = createApiClient({ token });

  const listEndpoint = endpoints?.list ?? '';
  const createEndpoint = endpoints?.create ?? listEndpoint;
  const updateEndpoint = endpoints?.update ?? listEndpoint;
  const deleteEndpoint = endpoints?.delete ?? listEndpoint;

  const state = reactive({
    loading: false,
    saving: false,
    error: '',
    items: Array.isArray(preload.items) ? preload.items : [],
    pagination: {
      total: preload.pagination?.total ?? (preload.items?.length ?? 0),
      limit: preload.pagination?.limit ?? 20,
      pages: preload.pagination?.pages ?? 0,
      current: preload.pagination?.current ?? 1,
    },
    search: '',
  });

  const abortSupported = typeof AbortController !== 'undefined';
  const abortRef = ref(null);

  const loadRates = async () => {
    if (!listEndpoint) {
      state.error = 'Tax endpoint unavailable.';
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
      const { items, pagination } = await api.fetchTaxRates({
        endpoint: listEndpoint,
        limit: state.pagination.limit,
        start,
        search: state.search.trim(),
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
    loadRates();
  };

  const saveRate = async (payload) => {
    state.saving = true;
    state.error = '';

    try {
      const rate = payload.id
        ? await api.updateTaxRate({ endpoint: updateEndpoint, id: payload.id, data: payload })
        : await api.createTaxRate({ endpoint: createEndpoint, data: payload });

      if (rate) {
        const index = state.items.findIndex((item) => item.id === rate.id);

        if (index === -1) {
          state.items.unshift(rate);
        } else {
          state.items.splice(index, 1, rate);
        }
      }

      return rate;
    } catch (error) {
      state.error = error?.message ?? 'Unknown error';
      throw error;
    } finally {
      state.saving = false;
    }
  };

  const deleteRates = async (ids) => {
    if (!Array.isArray(ids) || !ids.length) {
      return 0;
    }

    state.saving = true;
    state.error = '';

    try {
      const deleted = await api.deleteTaxRates({ endpoint: deleteEndpoint, ids });

      if (deleted) {
        state.items = state.items.filter((item) => !ids.includes(item.id));
      }

      return deleted;
    } catch (error) {
      state.error = error?.message ?? 'Unknown error';
      throw error;
    } finally {
      state.saving = false;
    }
  };

  onMounted(() => {
    if (!Array.isArray(state.items) || !state.items.length) {
      loadRates();
    }
  });

  return {
    state,
    loadRates,
    refresh,
    saveRate,
    deleteRates,
  };
}

export default useTaxRates;
