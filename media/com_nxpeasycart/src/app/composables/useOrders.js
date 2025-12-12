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
    url.searchParams.set("task", `api.orders.${action}`);

    return `${url.pathname}?${url.searchParams.toString()}`;
};

export function useOrders({
    endpoints,
    token,
    states = [],
    preload = {},
    autoload = true,
    cacheTTL = 300000,
}) {
    const api = createApiClient({ token });
    const perf = usePerformance("orders");

    const listEndpoint = endpoints?.list ?? "";
    const showEndpoint =
        endpoints?.show ?? deriveEndpoint(listEndpoint, "show");
    const transitionEndpoint =
        endpoints?.transition ?? deriveEndpoint(listEndpoint, "transition");
    const bulkTransitionEndpoint =
        endpoints?.bulkTransition ??
        deriveEndpoint(listEndpoint, "bulkTransition");
    const noteEndpoint =
        endpoints?.note ?? deriveEndpoint(listEndpoint, "note");
    const trackingEndpoint =
        endpoints?.tracking ?? deriveEndpoint(listEndpoint, "tracking");
    const invoiceEndpoint =
        endpoints?.invoice ?? deriveEndpoint(listEndpoint, "invoice");
    const exportEndpoint =
        endpoints?.export ?? deriveEndpoint(listEndpoint, "export");
    const sendEmailEndpoint =
        endpoints?.sendEmail ?? deriveEndpoint(listEndpoint, "sendEmail");
    const recordTransactionEndpoint =
        endpoints?.recordTransaction ?? deriveEndpoint(listEndpoint, "recordTransaction");
    const resendDownloadsEndpoint =
        endpoints?.resendDownloads ?? deriveEndpoint(listEndpoint, "resendDownloads");
    const resetDownloadEndpoint =
        endpoints?.resetDownload ?? deriveEndpoint(listEndpoint, "resetDownload");

    const abortSupported = typeof AbortController !== "undefined";
    const abortRef = ref(null);
    const preloadItems = Array.isArray(preload?.items) ? preload.items : [];
    const preloadPagination =
        preload?.pagination && typeof preload.pagination === "object"
            ? preload.pagination
            : {};

    const state = reactive({
        loading: false,
        saving: false,
        exporting: false,
        error: "",
        transitionError: "",
        items: [...preloadItems],
        pagination: {
            total: preloadPagination.total ?? preloadItems.length,
            limit: preloadPagination.limit ?? 20,
            pages: preloadPagination.pages ?? 0,
            current: preloadPagination.current ?? 1,
        },
        search: "",
        filterState: "",
        orderStates:
            Array.isArray(states) && states.length
                ? states
                : ["pending", "paid", "fulfilled", "refunded", "canceled"],
        activeOrder: null,
        selection: new Set(),
        lastUpdated: null,
        invoiceLoading: false,
    });

    const buildCacheKey = () => {
        const page = state.pagination.current || 1;
        const limit = state.pagination.limit || 20;
        const search = state.search.trim();
        const filterState = state.filterState || "";

        return `orders:page=${page}:limit=${limit}:search=${search}:state=${filterState}`;
    };

    const loadOrders = async (forceRefresh = false) => {
        if (!listEndpoint) {
            state.error = "Orders endpoint unavailable.";
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
            const { items, pagination } = await api.fetchOrders({
                endpoint: listEndpoint,
                limit: state.pagination.limit,
                start,
                search: state.search.trim(),
                state: state.filterState,
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
            state.selection.clear();
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
        loadOrders(true);
    };

    const search = () => {
        state.pagination.current = 1;
        loadOrders();
    };

    const setFilterState = (value) => {
        state.filterState = value || "";
        state.pagination.current = 1;
        loadOrders();
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
        loadOrders(true);
    };

    const clearSelection = () => {
        state.selection.clear();
    };

    const toggleSelection = (orderId) => {
        if (!orderId) {
            return;
        }

        if (state.selection.has(orderId)) {
            state.selection.delete(orderId);
        } else {
            state.selection.add(orderId);
        }
    };

    const nextPage = () => {
        if (state.pagination.current >= state.pagination.pages) {
            return;
        }

        state.pagination.current += 1;
        loadOrders(true);
    };

    const previousPage = () => {
        if (state.pagination.current <= 1) {
            return;
        }

        state.pagination.current -= 1;
        loadOrders(true);
    };

    const fetchOrder = async (id, orderNo = "") => {
        if (!showEndpoint) {
            return null;
        }

        try {
            return await api.fetchOrder({
                endpoint: showEndpoint,
                id,
                orderNumber: orderNo,
            });
        } catch (error) {
            state.transitionError = error?.message ?? "Unknown error";

            return null;
        }
    };

    const viewOrder = async (order) => {
        if (!order) {
            state.activeOrder = null;

            return;
        }

        state.transitionError = "";

        try {
            const detailed = await fetchOrder(order.id, order.order_no);

            state.activeOrder =
                detailed || {
                    ...order,
                    items: Array.isArray(order.items) ? order.items : [],
                    transactions: Array.isArray(order.transactions)
                        ? order.transactions
                        : [],
                    timeline: Array.isArray(order.timeline)
                        ? order.timeline
                        : [],
                    billing: order.billing ?? {},
                };
        } catch (error) {
            state.transitionError = error?.message ?? "Unknown error";
            state.activeOrder = {
                ...order,
                items: Array.isArray(order.items) ? order.items : [],
                transactions: Array.isArray(order.transactions)
                    ? order.transactions
                    : [],
                timeline: Array.isArray(order.timeline) ? order.timeline : [],
                billing: order.billing ?? {},
            };
        }
    };

    const closeOrder = () => {
        state.transitionError = "";
        state.activeOrder = null;
    };

    const updateOrderList = (order) => {
        if (!order?.id) {
            return;
        }

        const index = state.items.findIndex(
            (existing) => existing.id === order.id
        );

        if (index !== -1) {
            state.items.splice(index, 1, order);
        }
    };

    const transitionOrder = async (id, nextState) => {
        if (!transitionEndpoint) {
            throw new Error("Transition endpoint unavailable.");
        }

        state.saving = true;
        state.transitionError = "";

        try {
            const updated = await api.transitionOrder({
                endpoint: transitionEndpoint,
                id,
                state: nextState,
            });

            if (updated) {
                updateOrderList(updated);

                if (state.activeOrder && state.activeOrder.id === updated.id) {
                    state.activeOrder = updated;
                }

                clearCachedData(buildCacheKey());
            }

            return updated;
        } catch (error) {
            state.transitionError = error?.message ?? "Unknown error";

            throw error;
        } finally {
            state.saving = false;
        }
    };

    const updateTracking = async (id, tracking) => {
        if (!trackingEndpoint) {
            throw new Error("Tracking endpoint unavailable.");
        }

        state.saving = true;
        state.transitionError = "";

        try {
            const order = await api.updateOrderTracking({
                endpoint: trackingEndpoint,
                id,
                tracking,
            });

            if (order) {
                updateOrderList(order);

                if (state.activeOrder && state.activeOrder.id === order.id) {
                    state.activeOrder = order;
                }

                clearCachedData(buildCacheKey());
            }

            return order;
        } catch (error) {
            state.transitionError = error?.message ?? "Unknown error";
            throw error;
        } finally {
            state.saving = false;
        }
    };

    const downloadInvoice = async (id, orderNo = "") => {
        if (!invoiceEndpoint) {
            throw new Error("Invoice endpoint unavailable.");
        }

        state.invoiceLoading = true;
        state.transitionError = "";

        try {
            const invoice = await api.fetchOrderInvoice({
                endpoint: invoiceEndpoint,
                id,
                orderNumber: orderNo,
            });

            if (!invoice) {
                throw new Error("Invoice not available.");
            }

            return invoice;
        } catch (error) {
            state.transitionError = error?.message ?? "Unknown error";
            throw error;
        } finally {
            state.invoiceLoading = false;
        }
    };

    const exportOrders = async () => {
        if (!exportEndpoint) {
            throw new Error("Export endpoint unavailable.");
        }

        state.exporting = true;
        state.error = "";

        try {
            const exportData = await api.exportOrders({
                endpoint: exportEndpoint,
                search: state.search.trim(),
                state: state.filterState,
            });

            if (!exportData) {
                throw new Error("Export not available.");
            }

            return exportData;
        } catch (error) {
            state.error = error?.message ?? "Export failed";
            throw error;
        } finally {
            state.exporting = false;
        }
    };

    const sendEmail = async (id, emailType) => {
        if (!sendEmailEndpoint) {
            throw new Error("Send email endpoint unavailable.");
        }

        state.saving = true;
        state.transitionError = "";

        try {
            const order = await api.sendOrderEmail({
                endpoint: sendEmailEndpoint,
                id,
                type: emailType,
            });

            if (order) {
                updateOrderList(order);

                if (state.activeOrder && state.activeOrder.id === order.id) {
                    state.activeOrder = order;
                }

                clearCachedData(buildCacheKey());
            }

            return order;
        } catch (error) {
            state.transitionError = error?.message ?? "Failed to send email";
            throw error;
        } finally {
            state.saving = false;
        }
    };

    const recordTransaction = async (id, { amountCents, reference, note }) => {
        if (!recordTransactionEndpoint) {
            throw new Error("Record transaction endpoint unavailable.");
        }

        state.saving = true;
        state.transitionError = "";

        try {
            const order = await api.recordTransaction({
                endpoint: recordTransactionEndpoint,
                id,
                amountCents,
                reference,
                note,
            });

            if (order) {
                updateOrderList(order);

                if (state.activeOrder && state.activeOrder.id === order.id) {
                    state.activeOrder = order;
                }

                clearCachedData(buildCacheKey());
            }

            return order;
        } catch (error) {
            state.transitionError = error?.message ?? "Failed to record payment";
            throw error;
        } finally {
            state.saving = false;
        }
    };

    const resendDownloads = async (id) => {
        if (!resendDownloadsEndpoint) {
            throw new Error("Resend downloads endpoint unavailable.");
        }

        state.saving = true;
        state.transitionError = "";

        try {
            const order = await api.resendDownloads({
                endpoint: resendDownloadsEndpoint,
                id,
            });

            if (order) {
                updateOrderList(order);

                if (state.activeOrder && state.activeOrder.id === order.id) {
                    state.activeOrder = order;
                }

                clearCachedData(buildCacheKey());
            }

            return order;
        } catch (error) {
            state.transitionError = error?.message ?? "Failed to resend downloads email";
            throw error;
        } finally {
            state.saving = false;
        }
    };

    const resetDownload = async (downloadId, orderId) => {
        if (!resetDownloadEndpoint) {
            throw new Error("Reset download endpoint unavailable.");
        }

        state.saving = true;
        state.transitionError = "";

        try {
            const result = await api.resetDownload({
                endpoint: resetDownloadEndpoint,
                downloadId,
                orderId,
            });

            // If the API returned an updated order, refresh the active order
            if (result?.order) {
                updateOrderList(result.order);

                if (state.activeOrder && state.activeOrder.id === result.order.id) {
                    state.activeOrder = result.order;
                }

                clearCachedData(buildCacheKey());
            }

            return result;
        } catch (error) {
            state.transitionError = error?.message ?? "Failed to reset download count";
            throw error;
        } finally {
            state.saving = false;
        }
    };

    const bulkTransition = async (ids, nextState) => {
        if (!bulkTransitionEndpoint) {
            throw new Error("Bulk transition endpoint unavailable.");
        }

        if (!Array.isArray(ids) || !ids.length) {
            return { updated: [] };
        }

        state.saving = true;

        try {
            const payload = await api.bulkTransitionOrders({
                endpoint: bulkTransitionEndpoint,
                ids,
                state: nextState,
            });

            const updatedOrders = Array.isArray(payload.updated)
                ? payload.updated
                : [];

            updatedOrders.forEach((order) => {
                updateOrderList(order);

                if (state.activeOrder && state.activeOrder.id === order.id) {
                    state.activeOrder = order;
                }
            });

            clearSelection();

            return { updated: updatedOrders };
        } catch (error) {
            state.transitionError = error?.message ?? "Unknown error";
            throw error;
        } finally {
            state.saving = false;
        }
    };

    onMounted(() => {
        if (autoload) {
            loadOrders();
        }
    });

    return {
        state,
        loadOrders,
        refresh,
        search,
        setFilterState,
        goToPage,
        nextPage,
        previousPage,
        viewOrder,
        closeOrder,
        transitionOrder,
        bulkTransition,
        updateTracking,
        downloadInvoice,
        exportOrders,
        sendEmail,
        recordTransaction,
        resendDownloads,
        resetDownload,
        toggleSelection,
        clearSelection,
        metrics: perf.metrics,
        addNote: async (orderId, message) => {
            if (!noteEndpoint) {
                throw new Error("Note endpoint unavailable.");
            }

            if (!orderId || !message || !message.trim()) {
                return null;
            }

            state.transitionError = "";
            state.saving = true;

            try {
                const order = await api.addOrderNote({
                    endpoint: noteEndpoint,
                    id: orderId,
                    message,
                });

                if (order) {
                    updateOrderList(order);

                    if (
                        state.activeOrder &&
                        state.activeOrder.id === order.id
                    ) {
                        state.activeOrder = order;
                    }
                }

                return order;
            } catch (error) {
                state.transitionError = error?.message ?? "Unknown error";
                throw error;
            } finally {
                state.saving = false;
            }
        },
    };
}

export default useOrders;
