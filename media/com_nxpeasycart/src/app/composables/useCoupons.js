import { onMounted, reactive, ref } from "vue";
import { createApiClient } from "../../api.js";

export function useCoupons({
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
        activeCoupon: null,
    });

    const abortSupported = typeof AbortController !== "undefined";
    const abortRef = ref(null);

    const loadCoupons = async () => {
        if (!listEndpoint) {
            state.error = "Coupons endpoint unavailable.";
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
            const { items, pagination } = await api.fetchCoupons({
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
        loadCoupons();
    };

    const searchCoupons = () => {
        state.pagination.current = 1;
        loadCoupons();
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
        loadCoupons();
    };

    const setActive = (coupon) => {
        state.activeCoupon = coupon;
    };

    const upsertCoupon = (coupon) => {
        const index = state.items.findIndex((item) => item.id === coupon.id);

        if (index === -1) {
            state.items.unshift(coupon);
        } else {
            state.items.splice(index, 1, coupon);
        }
    };

    const saveCoupon = async (payload) => {
        state.saving = true;
        state.error = "";

        try {
            let coupon = null;

            if (payload.id) {
                coupon = await api.updateCoupon({
                    endpoint: updateEndpoint,
                    id: payload.id,
                    data: payload,
                });
            } else {
                coupon = await api.createCoupon({
                    endpoint: createEndpoint,
                    data: payload,
                });
            }

            if (coupon) {
                upsertCoupon(coupon);

                if (state.activeCoupon && state.activeCoupon.id === coupon.id) {
                    state.activeCoupon = coupon;
                }
            }

            return coupon;
        } catch (error) {
            state.error = error?.message ?? "Unknown error";
            throw error;
        } finally {
            state.saving = false;
        }
    };

    const deleteCoupons = async (ids) => {
        if (!Array.isArray(ids) || !ids.length) {
            return 0;
        }

        state.saving = true;
        state.error = "";

        try {
            const deleted = await api.deleteCoupons({
                endpoint: deleteEndpoint,
                ids,
            });

            if (deleted) {
                state.items = state.items.filter(
                    (item) => !ids.includes(item.id)
                );

                if (state.activeCoupon && ids.includes(state.activeCoupon.id)) {
                    state.activeCoupon = null;
                }
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
            loadCoupons();
        }
    });

    return {
        state,
        loadCoupons,
        refresh,
        search: searchCoupons,
        goToPage,
        setActive,
        saveCoupon,
        deleteCoupons,
    };
}

export default useCoupons;
