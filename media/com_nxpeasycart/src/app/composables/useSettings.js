import { onMounted, reactive } from 'vue';
import { createApiClient } from '../../api.js';

const normaliseSettings = (data = {}) => {
  const store = data.store ?? {};
  const payments = data.payments ?? {};

  const baseCurrency = typeof data.base_currency === 'string' && data.base_currency.trim() !== ''
    ? data.base_currency.trim().toUpperCase()
    : 'USD';

  return {
    store: {
      name: store.name ?? '',
      email: store.email ?? '',
      phone: store.phone ?? '',
    },
    payments: {
      configured: Boolean(payments.configured),
    },
    base_currency: baseCurrency,
  };
};

export function useSettings({ endpoints, token, preload = {} }) {
  const api = createApiClient({ token });

  const showEndpoint = endpoints?.show ?? '';
  const updateEndpoint = endpoints?.update ?? showEndpoint;

  const state = reactive({
    loading: false,
    saving: false,
    error: '',
    values: normaliseSettings(preload),
  });

  const refresh = async () => {
    if (!showEndpoint) {
      state.error = 'Settings endpoint unavailable.';
      return;
    }

    state.loading = true;
    state.error = '';

    try {
      const data = await api.fetchSettings({ endpoint: showEndpoint });
      state.values = normaliseSettings(data);
    } catch (error) {
      state.error = error?.message ?? 'Unknown error';
      throw error;
    } finally {
      state.loading = false;
    }
  };

  const save = async (payload) => {
    if (!updateEndpoint) {
      state.error = 'Settings endpoint unavailable.';
      return null;
    }

    state.saving = true;
    state.error = '';

    try {
      const data = await api.updateSettings({ endpoint: updateEndpoint, data: payload });
      state.values = normaliseSettings(data);

      return state.values;
    } catch (error) {
      state.error = error?.message ?? 'Unknown error';
      throw error;
    } finally {
      state.saving = false;
    }
  };

  onMounted(() => {
    if (!preload || !preload.store) {
      refresh();
    }
  });

  return {
    state,
    refresh,
    save,
  };
}

export default useSettings;
