import { onMounted, reactive, ref } from "vue";
import { createApiClient } from "../../api.js";
import {
    usePerformance,
    getCachedData,
    setCachedData,
    clearCachedData,
} from "./usePerformance.js";

export function useCustomers({
    endpoints,
    token,
    preload = {},
    autoload = true,
    cacheTTL = 300000,
}) {
    const api = createApiClient({ token });
    const perf = usePerformance("customers");

    const listEndpoint = endpoints?.list ?? "";
    const showEndpoint = endpoints?.show ?? "";

    const state = reactive({
        loading: false,
        error: "",
        items: Array.isArray(preload.items) ? preload.items : [],
        pagination: {
            total: preload.pagination?.total ?? preload.items?.length ?? 0,
            limit: preload.pagination?.limit ?? 20,
            pages: preload.pagination?.pages ?? 0,
            current: preload.pagination?.current ?? 1,
        },
        search: "",
        activeCustomer: preload.active ?? null,
        lastUpdated: null,
    });

    const abortSupported = typeof AbortController !== "undefined";
    const abortRef = ref(null);

    const buildCacheKey = () => {
        const page = state.pagination.current || 1;
        const limit = state.pagination.limit || 20;
        const search = state.search.trim();

        return `customers:page=${page}:limit=${limit}:search=${search}`;
    };

    const loadCustomers = async (forceRefresh = false) => {
        if (!listEndpoint) {
            state.error = "Customers endpoint unavailable.";
            state.items = [];
            return;
        }

        const cacheKey = buildCacheKey();
        const cached = !forceRefresh ? getCachedData(cacheKey, cacheTTL) : null;

        if (cached) {
            perf.recordCacheHit();
            state.items = cached.items;
            state.pagination = {
                ...state.pagination,
                ...cached.pagination,
            };
            state.lastUpdated = cached.lastUpdated;

            return;
        }

        perf.recordCacheMiss();

        state.loading = true;
        state.error = "";

        if (abortRef.value && abortSupported) {
            abortRef.value.abort();
        }

        const controller = abortSupported ? new AbortController() : null;
        abortRef.value = controller;

        const startMark = perf.startFetch();

        try {
            const start = Math.max(
                0,
                (state.pagination.current - 1) * state.pagination.limit
            );
            const { items, pagination } = await api.fetchCustomers({
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
                current:
                    pagination.current && pagination.current > 0
                        ? pagination.current
                        : 1,
            };
            state.lastUpdated = new Date().toISOString();

            setCachedData(cacheKey, {
                items,
                pagination: state.pagination,
                lastUpdated: state.lastUpdated,
            });
        } catch (error) {
            if (error?.name === "AbortError") {
                return;
            }

            state.error = error?.message ?? "Unknown error";
        } finally {
            perf.endFetch(startMark);

            if (abortSupported && abortRef.value === controller) {
                abortRef.value = null;
            }

            state.loading = false;
        }
    };

    const refresh = () => {
        state.pagination.current = 1;
        clearCachedData(buildCacheKey());
        loadCustomers(true);
    };

    const search = () => {
        state.pagination.current = 1;
        loadCustomers();
    };

    const goToPage = (page) => {
        const target = Number(page);

        if (
            Number.isNaN(target) ||
            target < 1 ||
            target === state.pagination.current
        ) {
            return;
        }

        state.pagination.current = target;
        loadCustomers();
    };

    const viewCustomer = async (customer) => {
        if (!showEndpoint || !customer?.email) {
            state.activeCustomer = customer || null;
            return;
        }

        try {
            const full = await api.fetchCustomer({
                endpoint: showEndpoint,
                email: customer.email,
            });

            state.activeCustomer = full || customer;
        } catch (error) {
            state.error = error?.message ?? "Unknown error";
        }
    };

    const closeCustomer = () => {
        state.activeCustomer = null;
    };

    onMounted(() => {
        if (
            autoload &&
            (!Array.isArray(state.items) || !state.items.length)
        ) {
            loadCustomers();
        }
    });

    return {
        state,
        loadCustomers,
        refresh,
        search,
        goToPage,
        viewCustomer,
        closeCustomer,
        metrics: perf.metrics,
    };
}

export default useCustomers;
