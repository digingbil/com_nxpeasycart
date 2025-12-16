import { onMounted, reactive, ref } from "vue";
import { createApiClient } from "../../api.js";
import {
    usePerformance,
    getCachedData,
    setCachedData,
    clearCachedData,
} from "./usePerformance.js";

const deriveEndpoint = (listEndpoint, action) => {
    if (!listEndpoint) {
        return "";
    }

    const origin =
        typeof window !== "undefined"
            ? window.location.origin
            : "http://localhost";
    const url = new URL(listEndpoint, origin);
    url.searchParams.set("task", `api.products.${action}`);

    return `${url.pathname}?${url.searchParams.toString()}`;
};

export function useProducts({ endpoints, token, autoload = true, cacheTTL = 300000 }) {
    const api = createApiClient({ token });
    const perf = usePerformance("products");

    const listEndpoint = endpoints?.list ?? "";
    const createEndpoint =
        endpoints?.create ?? deriveEndpoint(listEndpoint, "store");
    const updateEndpoint =
        endpoints?.update ?? deriveEndpoint(listEndpoint, "update");
    const deleteEndpoint =
        endpoints?.delete ?? deriveEndpoint(listEndpoint, "delete");
    const checkoutEndpoint =
        endpoints?.checkout ?? deriveEndpoint(listEndpoint, "checkout");
    const checkinEndpoint =
        endpoints?.checkin ?? deriveEndpoint(listEndpoint, "checkin");

    const abortSupported = typeof AbortController !== "undefined";

    const state = reactive({
        loading: false,
        saving: false,
        deleting: false,
        locking: false,
        error: "",
        validationErrors: [],
        items: [],
        pagination: {
            total: 0,
            limit: 20,
            pages: 0,
            current: 1,
        },
        search: "",
        lastUpdated: null,
    });

    const abortRef = ref(null);

    const buildCacheKey = () => {
        const page = state.pagination.current || 1;
        const limit = state.pagination.limit || 20;
        const search = state.search.trim();

        return `products:page=${page}:limit=${limit}:search=${search}`;
    };

    const loadProducts = async (forceRefresh = false) => {
        if (!listEndpoint) {
            state.error = "Products endpoint unavailable.";
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
        state.validationErrors = [];

        if (abortRef.value && abortSupported) {
            abortRef.value.abort();
        }

        const controller = abortSupported ? new AbortController() : null;
        abortRef.value = controller;

        const startMark = perf.startFetch();

        try {
            const { items, pagination } = await api.fetchProducts({
                endpoint: listEndpoint,
                signal: controller ? controller.signal : undefined,
                limit: state.pagination.limit,
                start: Math.max(
                    0,
                    (state.pagination.current - 1) * state.pagination.limit
                ),
                search: state.search.trim(),
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
        loadProducts(true);
    };

    const search = () => {
        state.pagination.current = 1;
        loadProducts();
    };

    const goToPage = (page) => {
        const target = Number(page);

        if (
            Number.isNaN(target) ||
            target < 1 ||
            target > state.pagination.pages
        ) {
            return;
        }

        state.pagination.current = target;
        loadProducts();
    };

    const toMessage = (value) => {
        if (!value) {
            return "";
        }

        if (typeof value === "string") {
            return value;
        }

        if (typeof value === "object" && value.message) {
            return String(value.message);
        }

        return String(value);
    };

    const handleApiError = (error, { setValidation = false } = {}) => {
        if (error?.code === 422 && setValidation) {
            const details = Array.isArray(error.details)
                ? error.details
                : [error.details].filter(Boolean);
            state.validationErrors = details
                .map(toMessage)
                .filter((message) => message !== "");
            return;
        }

        state.error = error?.message ?? "Unknown error";
    };

    const checkoutProduct = async (id) => {
        if (!checkoutEndpoint || !id) {
            return null;
        }

        state.locking = true;
        state.error = "";

        try {
            const item = await api.checkoutProduct({
                endpoint: checkoutEndpoint,
                id,
            });

            if (item) {
                const index = state.items.findIndex(
                    (existing) => existing.id === item.id
                );

                if (index !== -1) {
                    state.items.splice(index, 1, item);
                }
            }

            return item;
        } catch (error) {
            state.error = error?.message ?? "Unknown error";
            throw error;
        } finally {
            state.locking = false;
        }
    };

    const checkinProduct = async (id, { force = false } = {}) => {
        if (!checkinEndpoint || !id) {
            return null;
        }

        try {
            const item = await api.checkinProduct({
                endpoint: checkinEndpoint,
                id,
                force,
            });

            if (item) {
                const index = state.items.findIndex(
                    (existing) => existing.id === item.id
                );

                if (index !== -1) {
                    state.items.splice(index, 1, item);
                }
            }

            return item;
        } catch (error) {
            state.error = error?.message ?? "Unknown error";
            return null;
        }
    };

    const forceCheckinProduct = async (id) => checkinProduct(id, { force: true });

    const createProduct = async (payload) => {
        if (!createEndpoint) {
            throw new Error("Create endpoint unavailable.");
        }

        state.saving = true;
        state.validationErrors = [];

        try {
            const item = await api.createProduct({
                endpoint: createEndpoint,
                data: payload,
            });

            if (item) {
                state.items = [item, ...state.items];
                state.pagination.total += 1;
                clearCachedData(buildCacheKey());
            }

            return item;
        } catch (error) {
            handleApiError(error, { setValidation: true });
            throw error;
        } finally {
            state.saving = false;
        }
    };

    const updateProduct = async (id, payload) => {
        if (!updateEndpoint) {
            throw new Error("Update endpoint unavailable.");
        }

        state.saving = true;
        state.validationErrors = [];

        try {
            const item = await api.updateProduct({
                endpoint: updateEndpoint,
                id,
                data: payload,
            });

            if (item) {
                const index = state.items.findIndex(
                    (existing) => existing.id === item.id
                );

                if (index !== -1) {
                    state.items.splice(index, 1, item);
                }

                clearCachedData(buildCacheKey());
            }

            return item;
        } catch (error) {
            handleApiError(error, { setValidation: true });
            throw error;
        } finally {
            state.saving = false;
        }
    };

    const deleteProducts = async (ids) => {
        if (!deleteEndpoint) {
            throw new Error("Delete endpoint unavailable.");
        }

        state.deleting = true;
        state.validationErrors = [];

        try {
            const deleted = await api.deleteProducts({
                endpoint: deleteEndpoint,
                ids,
            });

            if (deleted.length) {
                state.items = state.items.filter(
                    (item) => !deleted.includes(item.id)
                );
                state.pagination.total = Math.max(
                    0,
                    state.pagination.total - deleted.length
                );
                clearCachedData(buildCacheKey());
            }

            return deleted;
        } catch (error) {
            handleApiError(error);
            throw error;
        } finally {
            state.deleting = false;
        }
    };

    onMounted(() => {
        if (autoload) {
            loadProducts();
        }
    });

    return {
        state,
        loadProducts,
        refresh,
        search,
        goToPage,
        createProduct,
        updateProduct,
        deleteProducts,
        checkoutProduct,
        checkinProduct,
        forceCheckinProduct,
        metrics: perf.metrics,
    };
}

export default useProducts;
