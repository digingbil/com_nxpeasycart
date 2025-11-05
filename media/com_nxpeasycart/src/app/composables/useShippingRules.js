import { onMounted, reactive, ref } from "vue";
import { createApiClient } from "../../api.js";

export function useShippingRules({
    endpoints,
    token,
    preload = {},
    autoload = true,
}) {
    const api = createApiClient({ token });

    const listEndpoint = endpoints?.list ?? "";
    const createEndpoint = endpoints?.create ?? listEndpoint;
    const updateEndpoint = endpoints?.update ?? listEndpoint;
    const deleteEndpoint = endpoints?.delete ?? listEndpoint;

    const state = reactive({
        loading: false,
        saving: false,
        error: "",
        items: Array.isArray(preload.items) ? preload.items : [],
        pagination: {
            total: preload.pagination?.total ?? preload.items?.length ?? 0,
            limit: preload.pagination?.limit ?? 20,
            pages: preload.pagination?.pages ?? 0,
            current: preload.pagination?.current ?? 1,
        },
        search: "",
    });

    const abortSupported = typeof AbortController !== "undefined";
    const abortRef = ref(null);

    const loadRules = async () => {
        if (!listEndpoint) {
            state.error = "Shipping endpoint unavailable.";
            state.items = [];
            return;
        }

        state.loading = true;
        state.error = "";

        if (abortRef.value && abortSupported) {
            abortRef.value.abort();
        }

        const controller = abortSupported ? new AbortController() : null;
        abortRef.value = controller;

        try {
            const start = Math.max(
                0,
                (state.pagination.current - 1) * state.pagination.limit
            );
            const { items, pagination } = await api.fetchShippingRules({
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
        } catch (error) {
            if (error?.name === "AbortError") {
                return;
            }

            state.error = error?.message ?? "Unknown error";
        } finally {
            if (abortSupported && abortRef.value === controller) {
                abortRef.value = null;
            }

            state.loading = false;
        }
    };

    const refresh = () => {
        state.pagination.current = 1;
        loadRules();
    };

    const saveRule = async (payload) => {
        state.saving = true;
        state.error = "";

        try {
            const rule = payload.id
                ? await api.updateShippingRule({
                      endpoint: updateEndpoint,
                      id: payload.id,
                      data: payload,
                  })
                : await api.createShippingRule({
                      endpoint: createEndpoint,
                      data: payload,
                  });

            if (rule) {
                const index = state.items.findIndex(
                    (item) => item.id === rule.id
                );

                if (index === -1) {
                    state.items.unshift(rule);
                } else {
                    state.items.splice(index, 1, rule);
                }
            }

            return rule;
        } catch (error) {
            state.error = error?.message ?? "Unknown error";
            throw error;
        } finally {
            state.saving = false;
        }
    };

    const deleteRules = async (ids) => {
        if (!Array.isArray(ids) || !ids.length) {
            return 0;
        }

        state.saving = true;
        state.error = "";

        try {
            const deleted = await api.deleteShippingRules({
                endpoint: deleteEndpoint,
                ids,
            });

            if (deleted) {
                state.items = state.items.filter(
                    (item) => !ids.includes(item.id)
                );
            }

            return deleted;
        } catch (error) {
            state.error = error?.message ?? "Unknown error";
            throw error;
        } finally {
            state.saving = false;
        }
    };

    onMounted(() => {
        if (
            autoload &&
            (!Array.isArray(state.items) || !state.items.length)
        ) {
            loadRules();
        }
    });

    return {
        state,
        loadRules,
        refresh,
        saveRule,
        deleteRules,
    };
}

export default useShippingRules;
