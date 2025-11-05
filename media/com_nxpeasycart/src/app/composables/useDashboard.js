import { onMounted, reactive } from "vue";
import { createApiClient } from "../../api.js";
import {
    usePerformance,
    getCachedData,
    setCachedData,
    clearCachedData,
} from "./usePerformance.js";

export function useDashboard({
    endpoint = "",
    token = "",
    preload = {},
    autoload = true,
    cacheTTL = 300000,
}) {
    const api = createApiClient({ token });
    const perf = usePerformance("dashboard");

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
        lastUpdated: null,
    });

    const buildCacheKey = () => {
        return `dashboard`;
    };

    const refresh = async (forceRefresh = false) => {
        if (!endpoint) {
            return;
        }

        const cacheKey = buildCacheKey();
        const cached = !forceRefresh ? getCachedData(cacheKey, cacheTTL) : null;

        if (cached) {
            perf.recordCacheHit();
            state.summary = cached.summary;
            state.checklist = cached.checklist;
            state.lastUpdated = cached.lastUpdated;

            return;
        }

        perf.recordCacheMiss();

        state.loading = true;
        state.error = "";

        const startMark = perf.startFetch();

        try {
            const data = await api.fetchDashboard({ endpoint });
            state.summary = data.summary ?? state.summary;
            state.checklist = Array.isArray(data.checklist)
                ? data.checklist
                : state.checklist;
            state.lastUpdated = new Date().toISOString();

            setCachedData(cacheKey, {
                summary: state.summary,
                checklist: state.checklist,
                lastUpdated: state.lastUpdated,
            });
        } catch (error) {
            state.error = error?.message ?? "Unknown error";
        } finally {
            perf.endFetch(startMark);
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
        metrics: perf.metrics,
    };
}

export default useDashboard;
