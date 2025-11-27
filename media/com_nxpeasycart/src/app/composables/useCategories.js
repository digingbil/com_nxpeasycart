import { onMounted, reactive, ref } from "vue";
import { createApiClient } from "../../api.js";
import {
    usePerformance,
    getCachedData,
    setCachedData,
    clearCachedData,
} from "./usePerformance.js";

export function useCategories({
    endpoints,
    token,
    preload = {},
    autoload = true,
    cacheTTL = 300000,
}) {
    const api = createApiClient({ token });
    const perf = usePerformance("categories");

    const listEndpoint = endpoints?.list ?? "";
    const createEndpoint = endpoints?.create ?? listEndpoint;
    const updateEndpoint = endpoints?.update ?? listEndpoint;
    const deleteEndpoint = endpoints?.delete ?? listEndpoint;

    const state = reactive({
        loading: false,
        saving: false,
        deleting: false,
        error: "",
        validationErrors: [],
        items: Array.isArray(preload.items) ? preload.items : [],
        pagination: {
            total: preload.pagination?.total ?? preload.items?.length ?? 0,
            limit: preload.pagination?.limit ?? 20,
            pages: preload.pagination?.pages ?? 0,
            current: preload.pagination?.current ?? 1,
        },
        search: "",
        lastUpdated: null,
    });

    const abortSupported = typeof AbortController !== "undefined";
    const abortRef = ref(null);

    const buildCacheKey = () => {
        const page = state.pagination.current || 1;
        const limit = state.pagination.limit || 20;
        const search = state.search.trim();

        return `categories:page=${page}:limit=${limit}:search=${search}`;
    };

    const loadCategories = async (forceRefresh = false) => {
        if (!listEndpoint) {
            state.error = "Categories endpoint unavailable.";
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
            const start = Math.max(
                0,
                (state.pagination.current - 1) * state.pagination.limit
            );
            const { items, pagination } = await api.fetchCategories({
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
        loadCategories(true);
    };

    const searchCategories = () => {
        state.pagination.current = 1;
        loadCategories();
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
        loadCategories(true);
    };

    const handleApiError = (error, { validation = false } = {}) => {
        if (validation && error?.code === 422) {
            const details = error.details;
            const messages = Array.isArray(details)
                ? details
                : [details].filter(Boolean);
            state.validationErrors = messages
                .map((message) => {
                    if (!message) {
                        return "";
                    }

                    if (typeof message === "string") {
                        return message;
                    }

                    if (typeof message === "object" && message.message) {
                        return String(message.message);
                    }

                    return String(message);
                })
                .filter(Boolean);
            return;
        }

        state.error = error?.message ?? "Unknown error";
    };

    const saveCategory = async (payload) => {
        if (!createEndpoint || !updateEndpoint) {
            throw new Error("Category endpoints unavailable.");
        }

        state.saving = true;
        state.error = "";
        state.validationErrors = [];

        try {
            let category = null;

            if (payload.id) {
                category = await api.updateCategory({
                    endpoint: updateEndpoint,
                    id: payload.id,
                    data: payload,
                });
            } else {
                category = await api.createCategory({
                    endpoint: createEndpoint,
                    data: payload,
                });
            }

            if (category) {
                clearCachedData(buildCacheKey());
                await loadCategories();
            }

            return category;
        } catch (error) {
            handleApiError(error, { validation: true });
            throw error;
        } finally {
            state.saving = false;
        }
    };

    const deleteCategories = async (ids) => {
        if (!Array.isArray(ids) || !ids.length || !deleteEndpoint) {
            return 0;
        }

        state.deleting = true;
        state.error = "";

        try {
            const deleted = await api.deleteCategories({
                endpoint: deleteEndpoint,
                ids,
            });

            if (deleted) {
                clearCachedData(buildCacheKey());
                await loadCategories();
            }

            return deleted;
        } catch (error) {
            handleApiError(error);
            throw error;
        } finally {
            state.deleting = false;
        }
    };

    const loadOptions = async () => {
        if (!listEndpoint) {
            return [];
        }

        try {
            const { items } = await api.fetchCategories({
                endpoint: listEndpoint,
                limit: 200,
                start: 0,
                search: "",
            });

            return items;
        } catch (error) {
            return [];
        }
    };

    onMounted(() => {
        if (
            autoload &&
            (!Array.isArray(state.items) || !state.items.length)
        ) {
            loadCategories();
        }
    });

    return {
        state,
        loadCategories,
        refresh,
        search: searchCategories,
        goToPage,
        saveCategory,
        deleteCategories,
        loadOptions,
        metrics: perf.metrics,
    };
}

export default useCategories;
