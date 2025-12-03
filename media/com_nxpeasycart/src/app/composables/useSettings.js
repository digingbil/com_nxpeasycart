import { onMounted, reactive } from "vue";
import { createApiClient } from "../../api.js";
import {
    usePerformance,
    getCachedData,
    setCachedData,
    clearCachedData,
} from "./usePerformance.js";

const normaliseSettings = (data = {}) => {
    const store = data.store ?? {};
    const payments = data.payments ?? {};
    const visual = data.visual ?? {};
    const visualDefaults = data.visual_defaults ?? {};
    const security = data.security?.rate_limits ?? {};

    // Don't default to USD here - let the caller handle the fallback
    // so that datasetBaseCurrency from the page can be used
    const baseCurrency =
        typeof data.base_currency === "string" &&
        data.base_currency.trim() !== ""
            ? data.base_currency.trim().toUpperCase()
            : "";

    return {
        store: {
            name: store.name ?? "",
            email: store.email ?? "",
            phone: store.phone ?? "",
        },
        payments: {
            configured: Boolean(payments.configured),
        },
        base_currency: baseCurrency,
        checkout_phone_required: Boolean(data.checkout_phone_required),
        auto_send_order_emails: Boolean(data.auto_send_order_emails),
        category_page_size: Number.isFinite(Number(data.category_page_size))
            ? Number(data.category_page_size)
            : 12,
        category_pagination_mode:
            data.category_pagination_mode === "infinite" ? "infinite" : "paged",
        stale_order_cleanup_enabled: Boolean(data.stale_order_cleanup_enabled),
        stale_order_hours: Number.isFinite(Number(data.stale_order_hours))
            ? Math.max(1, Math.min(720, Number(data.stale_order_hours)))
            : 48,
        show_advanced_mode: Boolean(data.show_advanced_mode),
        visual: {
            primary_color: visual.primary_color ?? "",
            text_color: visual.text_color ?? "",
            surface_color: visual.surface_color ?? "",
            border_color: visual.border_color ?? "",
            muted_color: visual.muted_color ?? "",
        },
        visual_defaults: {
            primary_color: visualDefaults.primary_color ?? "#4f6d7a",
            text_color: visualDefaults.text_color ?? "#1f2933",
            surface_color: visualDefaults.surface_color ?? "#ffffff",
            border_color: visualDefaults.border_color ?? "#e4e7ec",
            muted_color: visualDefaults.muted_color ?? "#6b7280",
        },
        security: {
            rate_limits: {
                checkout_ip_limit: Number.isFinite(
                    Number(security.checkout_ip_limit)
                )
                    ? Number(security.checkout_ip_limit)
                    : 10,
                checkout_email_limit: Number.isFinite(
                    Number(security.checkout_email_limit)
                )
                    ? Number(security.checkout_email_limit)
                    : 5,
                checkout_session_limit: Number.isFinite(
                    Number(security.checkout_session_limit)
                )
                    ? Number(security.checkout_session_limit)
                    : 15,
                checkout_window_minutes: Number.isFinite(
                    Number(security.checkout_window_minutes)
                )
                    ? Number(security.checkout_window_minutes)
                    : Number.isFinite(Number(security.checkout_window))
                      ? Math.max(
                            0,
                            Math.ceil(Number(security.checkout_window) / 60)
                        )
                      : 10,
                offline_ip_limit: Number.isFinite(
                    Number(security.offline_ip_limit)
                )
                    ? Number(security.offline_ip_limit)
                    : 3,
                offline_email_limit: Number.isFinite(
                    Number(security.offline_email_limit)
                )
                    ? Number(security.offline_email_limit)
                    : 3,
                offline_window_minutes: Number.isFinite(
                    Number(security.offline_window_minutes)
                )
                    ? Number(security.offline_window_minutes)
                    : Number.isFinite(Number(security.offline_window))
                      ? Math.max(
                            0,
                            Math.ceil(Number(security.offline_window) / 60)
                        )
                      : 30,
            },
        },
    };
};

export function useSettings({
    endpoints,
    token,
    preload = {},
    autoload = true,
    cacheTTL = 300000,
}) {
    const api = createApiClient({ token });
    const perf = usePerformance("settings");

    const showEndpoint = endpoints?.show ?? "";
    const updateEndpoint = endpoints?.update ?? showEndpoint;

    const state = reactive({
        loading: false,
        saving: false,
        error: "",
        message: "",
        values: normaliseSettings(preload),
        lastUpdated: null,
    });

    const refresh = async (forceRefresh = false) => {
        if (!showEndpoint) {
            state.error = "Settings endpoint unavailable.";
            return;
        }

        const cacheKey = "settings:data";
        const cached = !forceRefresh ? getCachedData(cacheKey, cacheTTL) : null;

        if (cached) {
            perf.recordCacheHit();
            state.values = cached.values;
            state.lastUpdated = cached.lastUpdated;

            return;
        }

        perf.recordCacheMiss();

        state.loading = true;
        state.error = "";

        const startMark = perf.startFetch();

        try {
            const data = await api.fetchSettings({ endpoint: showEndpoint });
            state.values = normaliseSettings(data);
            state.lastUpdated = new Date().toISOString();

            setCachedData(cacheKey, {
                values: state.values,
                lastUpdated: state.lastUpdated,
            });
        } catch (error) {
            state.error = error?.message ?? "Unknown error";
            throw error;
        } finally {
            perf.endFetch(startMark);
            state.loading = false;
        }
    };

    const save = async (payload) => {
        if (!updateEndpoint) {
            state.error = "Settings endpoint unavailable.";
            return null;
        }

        state.saving = true;
        state.error = "";
        state.message = "";

        try {
            const data = await api.updateSettings({
                endpoint: updateEndpoint,
                data: payload,
            });
            state.values = normaliseSettings(data);
            state.lastUpdated = new Date().toISOString();
            state.message = "Settings saved.";

            clearCachedData("settings:data");
            setCachedData("settings:data", {
                values: state.values,
                lastUpdated: state.lastUpdated,
            });

            return state.values;
        } catch (error) {
            state.error = error?.message ?? "Unknown error";
            throw error;
        } finally {
            state.saving = false;
        }
    };

    onMounted(() => {
        if (autoload && (!preload || !preload.store)) {
            refresh();
        }
    });

    return {
        state,
        refresh,
        save,
        metrics: perf.metrics,
    };
}

export default useSettings;
