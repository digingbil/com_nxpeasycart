import { reactive, ref, computed } from "vue";
import { createApiClient } from "../../api.js";

const defaultConfig = () => ({
    stripe: {
        publishable_key: "",
        secret_key: "",
        webhook_secret: "",
        mode: "test",
    },
    paypal: {
        client_id: "",
        client_secret: "",
        webhook_id: "",
        mode: "sandbox",
    },
    cod: {
        enabled: true,
        label: "Cash on delivery",
    },
    bank_transfer: {
        enabled: false,
        label: "Bank transfer",
        instructions: "",
        account_name: "",
        iban: "",
        bic: "",
    },
});

export function usePayments({ endpoints = {}, token = "" }) {
    const api = createApiClient({ token });

    const state = reactive({
        loading: false,
        saving: false,
        error: "",
        config: defaultConfig(),
        message: "",
    });

    // Track original config for dirty detection
    const originalConfig = ref(null);

    /**
     * Check if current config differs from the last saved/loaded state.
     * Useful for showing "unsaved changes" warnings.
     */
    const isDirty = computed(() => {
        if (!originalConfig.value) return false;
        return JSON.stringify(state.config) !== JSON.stringify(originalConfig.value);
    });

    /**
     * Reset config to the last saved/loaded state.
     * Discards any unsaved changes.
     */
    const resetDraft = () => {
        if (originalConfig.value) {
            state.config = JSON.parse(JSON.stringify(originalConfig.value));
        }
    };

    const refresh = async () => {
        if (!endpoints.show) {
            return;
        }

        state.loading = true;
        state.error = "";

        try {
            const payload = await api.get(endpoints.show);
            state.config = normalize(
                payload?.data?.config ?? payload?.config ?? {}
            );

            // Store original config for dirty detection
            originalConfig.value = JSON.parse(JSON.stringify(state.config));
        } catch (error) {
            state.error =
                error.message ?? "Unable to load payment configuration.";
        } finally {
            state.loading = false;
        }
    };

    const save = async (config) => {
        if (!endpoints.update) {
            return;
        }

        state.saving = true;
        state.error = "";
        state.message = "";

        try {
            const payload = await api.post(endpoints.update, { config });
            state.config = normalize(
                payload?.data?.config ?? payload?.config ?? {}
            );
            state.message = payload?.data?.message ?? payload?.message ?? "";

            // Update original config after successful save (no longer dirty)
            originalConfig.value = JSON.parse(JSON.stringify(state.config));
        } catch (error) {
            state.error =
                error.message ?? "Unable to save payment configuration.";
        } finally {
            state.saving = false;
        }
    };

    const normalize = (raw) => {
        const template = defaultConfig();

        return {
            stripe: {
                ...template.stripe,
                ...(raw.stripe ?? {}),
            },
            paypal: {
                ...template.paypal,
                ...(raw.paypal ?? {}),
            },
            cod: {
                ...template.cod,
                ...(raw.cod ?? {}),
            },
            bank_transfer: {
                ...template.bank_transfer,
                ...(raw.bank_transfer ?? {}),
            },
        };
    };

    return {
        state,
        refresh,
        save,
        isDirty,
        resetDraft,
    };
}

export default usePayments;
