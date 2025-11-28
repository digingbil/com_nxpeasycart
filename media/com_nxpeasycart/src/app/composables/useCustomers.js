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
    const gdprExportEndpoint = endpoints?.gdpr?.export ?? "";
    const gdprAnonymiseEndpoint = endpoints?.gdpr?.anonymise ?? "";

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
        gdprLoading: false,
        gdprMessage: "",
        gdprSuccess: false,
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
        loadCustomers(true);
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
        state.gdprMessage = "";
        state.gdprSuccess = false;
    };

    /**
     * Export customer data (GDPR Article 20 - Data Portability).
     * Downloads all customer data as a JSON file.
     */
    const gdprExport = async (email) => {
        if (!gdprExportEndpoint || !email) {
            state.gdprMessage = "GDPR export endpoint unavailable.";
            state.gdprSuccess = false;
            return;
        }

        state.gdprLoading = true;
        state.gdprMessage = "";

        try {
            const data = await api.gdprExport({
                endpoint: gdprExportEndpoint,
                email,
            });

            if (!data) {
                throw new Error("No data returned");
            }

            // Create and download JSON file
            const blob = new Blob([JSON.stringify(data, null, 2)], {
                type: "application/json",
            });
            const url = URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.href = url;
            link.download = `gdpr-export-${email.replace(/[^a-z0-9]/gi, "-")}-${Date.now()}.json`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);

            state.gdprMessage = "Data exported successfully.";
            state.gdprSuccess = true;
        } catch (error) {
            state.gdprMessage = error?.message ?? "Export failed";
            state.gdprSuccess = false;
        } finally {
            state.gdprLoading = false;
        }
    };

    /**
     * Anonymise customer data (GDPR Article 17 - Right to Erasure).
     * Replaces PII with anonymous data, preserving order records for accounting.
     */
    const gdprAnonymise = async (email) => {
        if (!gdprAnonymiseEndpoint || !email) {
            state.gdprMessage = "GDPR anonymise endpoint unavailable.";
            state.gdprSuccess = false;
            return;
        }

        // Require explicit confirmation
        const confirmed = window.confirm(
            `Are you sure you want to anonymise all data for ${email}?\n\n` +
                "This action:\n" +
                "• Replaces the email with an anonymous hash\n" +
                "• Removes billing and shipping addresses\n" +
                "• Removes tracking information\n" +
                "• CANNOT BE UNDONE\n\n" +
                "Order totals and items are preserved for accounting."
        );

        if (!confirmed) {
            return;
        }

        state.gdprLoading = true;
        state.gdprMessage = "";

        try {
            const result = await api.gdprAnonymise({
                endpoint: gdprAnonymiseEndpoint,
                email,
            });

            state.gdprMessage =
                result.message || `Anonymised ${result.affected} order(s).`;
            state.gdprSuccess = true;

            // Refresh the customer list since this customer is now anonymised
            clearCachedData(buildCacheKey());
            loadCustomers(true);

            // Close the sidebar after a short delay
            setTimeout(() => {
                state.activeCustomer = null;
                state.gdprMessage = "";
            }, 2000);
        } catch (error) {
            state.gdprMessage = error?.message ?? "Anonymisation failed";
            state.gdprSuccess = false;
        } finally {
            state.gdprLoading = false;
        }
    };

    onMounted(() => {
        if (autoload && (!Array.isArray(state.items) || !state.items.length)) {
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
        gdprExport,
        gdprAnonymise,
        metrics: perf.metrics,
    };
}

export default useCustomers;
