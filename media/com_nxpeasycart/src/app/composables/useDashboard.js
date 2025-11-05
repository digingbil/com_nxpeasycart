import { onMounted, reactive } from "vue";
import { createApiClient } from "../../api.js";

export function useDashboard({
    endpoint = "",
    token = "",
    preload = {},
    autoload = true,
}) {
    const api = createApiClient({ token });

    const state = reactive({
        loading: false,
        error: "",
        summary: preload.summary ?? {
            products: { total: 0, active: 0 },
            orders: {
                total: 0,
                pending: 0,
                paid: 0,
                fulfilled: 0,
                refunded: 0,
                revenue_today: 0,
                revenue_month: 0,
            },
            customers: { total: 0 },
            currency: preload.summary?.currency ?? "USD",
        },
        checklist: Array.isArray(preload.checklist) ? preload.checklist : [],
    });

    const refresh = async () => {
        if (!endpoint) {
            return;
        }

        state.loading = true;
        state.error = "";

        try {
            const data = await api.fetchDashboard({ endpoint });
            state.summary = data.summary ?? state.summary;
            state.checklist = Array.isArray(data.checklist)
                ? data.checklist
                : state.checklist;
        } catch (error) {
            state.error = error?.message ?? "Unknown error";
        } finally {
            state.loading = false;
        }
    };

    onMounted(() => {
        if (
            autoload &&
            (!Array.isArray(state.checklist) || !state.checklist.length)
        ) {
            refresh();
        }
    });

    return {
        state,
        refresh,
    };
}

export default useDashboard;
