import { reactive } from "vue";
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
        };
    };

    return {
        state,
        refresh,
        save,
    };
}

export default usePayments;
