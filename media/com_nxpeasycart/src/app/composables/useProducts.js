import { onMounted, reactive, ref } from 'vue';
import { createApiClient } from '../../api.js';

export function useProducts({ endpoint, token }) {
  const api = createApiClient({ token });

  const state = reactive({
    loading: false,
    error: '',
    items: [],
    pagination: {
      total: 0,
      limit: 20,
      pages: 0,
      current: 1,
    },
    search: '',
  });

  const abortRef = ref(null);

  const loadProducts = async () => {
    if (!endpoint) {
      state.error = 'Products endpoint unavailable.';
      state.items = [];
      return;
    }

    state.loading = true;
    state.error = '';

    if (abortRef.value) {
      abortRef.value.abort();
    }

    const controller = new AbortController();
    abortRef.value = controller;

    try {
      const { items, pagination } = await api.fetchProducts({
        endpoint,
        signal: controller.signal,
        limit: state.pagination.limit,
        start: Math.max(0, (state.pagination.current - 1) * state.pagination.limit),
        search: state.search.trim(),
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
      if (abortRef.value === controller) {
        abortRef.value = null;
      }

      state.loading = false;
    }
  };

  const refresh = () => {
    state.pagination.current = 1;
    loadProducts();
  };

  const search = () => {
    state.pagination.current = 1;
    loadProducts();
  };

  onMounted(() => {
    loadProducts();
  });

  return {
    state,
    loadProducts,
    refresh,
    search,
  };
}

export default useProducts;
