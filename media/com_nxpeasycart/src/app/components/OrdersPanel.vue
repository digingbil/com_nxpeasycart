<template>
    <section class="nxp-ec-admin-panel nxp-ec-admin-panel--orders">
        <header class="nxp-ec-admin-panel__header">
            <div>
                <h2 class="nxp-ec-admin-panel__title">
                    {{ __("COM_NXPEASYCART_MENU_ORDERS", "Orders") }}
                </h2>
                <p class="nxp-ec-admin-panel__lead">
                    {{ __("COM_NXPEASYCART_ORDERS_LEAD", "Track orders and manage fulfilment from this screen.") }}
                </p>
            </div>
            <div class="nxp-ec-admin-panel__actions">
                <input
                    type="search"
                    class="nxp-ec-admin-search"
                    :placeholder="__('COM_NXPEASYCART_ORDERS_SEARCH_PLACEHOLDER', 'Search orders')"
                    v-model="state.search"
                    @keyup.enter="emit('search')"
                    :aria-label="__('COM_NXPEASYCART_ORDERS_SEARCH_PLACEHOLDER', 'Search orders')"
                />
                <select
                    class="nxp-ec-admin-select"
                    v-model="state.filterState"
                    @change="emit('filter', state.filterState)"
                    :aria-label="__('COM_NXPEASYCART_ORDERS_FILTER_STATE', 'Filter by state')"
                >
                    <option value="">
                        — {{ __("COM_NXPEASYCART_ORDERS_FILTER_STATE", "State") }} —
                    </option>
                    <option
                        v-for="option in state.orderStates"
                        :key="option"
                        :value="option"
                    >
                        {{ stateLabel(option) }}
                    </option>
                </select>
                <button
                    class="nxp-ec-btn nxp-ec-btn--icon"
                    type="button"
                    @click="emit('export')"
                    :disabled="state.loading || state.exporting"
                    :title="__('COM_NXPEASYCART_ORDERS_EXPORT', 'Export CSV')"
                    :aria-label="__('COM_NXPEASYCART_ORDERS_EXPORT', 'Export CSV')"
                >
                    <i class="fa-solid fa-file-csv"></i>
                    <span class="nxp-ec-sr-only">{{ __("COM_NXPEASYCART_ORDERS_EXPORT", "Export CSV") }}</span>
                </button>
                <button
                    class="nxp-ec-btn nxp-ec-btn--icon"
                    type="button"
                    @click="emit('refresh')"
                    :disabled="state.loading"
                    :title="__('COM_NXPEASYCART_ORDERS_REFRESH', 'Refresh')"
                    :aria-label="__('COM_NXPEASYCART_ORDERS_REFRESH', 'Refresh')"
                >
                    <i class="fa-solid fa-rotate"></i>
                    <span class="nxp-ec-sr-only">{{ __("COM_NXPEASYCART_ORDERS_REFRESH", "Refresh") }}</span>
                </button>
            </div>
        </header>

        <div v-if="state.error" class="nxp-ec-admin-alert nxp-ec-admin-alert--error">
            {{ state.error }}
        </div>

        <div v-if="state.transitionError && !state.error" class="nxp-ec-admin-alert nxp-ec-admin-alert--error">
            {{ state.transitionError }}
        </div>

        <div v-if="state.loading && !state.error" class="nxp-ec-admin-panel__body">
            <SkeletonLoader type="table" :rows="5" :columns="7" />
        </div>

        <div v-if="!state.loading && !state.error" class="nxp-ec-admin-panel__body nxp-ec-admin-panel__body--orders">
            <OrdersTable
                ref="ordersTableRef"
                :items="state.items"
                :order-states="state.orderStates"
                :selection="state.selection"
                :pagination="state.pagination"
                :active-order-id="state.activeOrder?.id"
                :saving="state.saving"
                :current-user-id="currentUserId"
                :translate="translate"
                @view="handleView"
                @transition="handleTransition"
                @bulk-transition="handleBulkTransition"
                @toggle-selection="emit('toggle-selection', $event)"
                @clear-selection="emit('clear-selection')"
                @page="emit('page', $event)"
            />

            <div
                v-if="state.lastUpdated"
                class="nxp-ec-admin-panel__metadata"
                :title="state.lastUpdated"
            >
                {{ __("COM_NXPEASYCART_LAST_UPDATED", "Last updated") }}:
                {{ formatTimestamp(state.lastUpdated) }}
            </div>

            <OrderDetailSidebar
                v-if="state.activeOrder"
                :order="state.activeOrder"
                :saving="state.saving"
                :is-locked="activeOrderLocked"
                :invoice-loading="state.invoiceLoading"
                :transition-error="state.transitionError"
                :site-root="siteRoot"
                :translate="translate"
                @close="handleClose"
                @add-note="emit('add-note', $event)"
                @save-tracking="emit('save-tracking', $event)"
                @invoice="emit('invoice', $event)"
                @send-email="emit('send-email', $event)"
                @record-payment="emit('record-payment', $event)"
                @resend-downloads="emit('resend-downloads', $event)"
                @reset-download="emit('reset-download', $event)"
            />
        </div>
    </section>
</template>

<script setup>
import { computed, ref, watch, onBeforeUnmount } from "vue";
import SkeletonLoader from "./SkeletonLoader.vue";
import { OrdersTable, OrderDetailSidebar } from "./orders";
import { useOrderFormatters } from "../composables/useOrderFormatters";
import { useOrderLock } from "../composables/useOrderLock";

const props = defineProps({
    state: {
        type: Object,
        required: true,
    },
    translate: {
        type: Function,
        required: true,
    },
    siteRoot: {
        type: String,
        default: "",
    },
    currentUserId: {
        type: Number,
        default: 0,
    },
    forceCheckinOrder: {
        type: Function,
        default: null,
    },
});

const emit = defineEmits([
    "refresh",
    "search",
    "filter",
    "view",
    "close",
    "transition",
    "page",
    "bulk-transition",
    "toggle-selection",
    "clear-selection",
    "add-note",
    "save-tracking",
    "invoice",
    "export",
    "send-email",
    "record-payment",
    "resend-downloads",
    "reset-download",
]);

const __ = props.translate;
const currentUserIdRef = computed(() => Number(props.currentUserId || 0));

const { stateLabel, formatTimestamp } = useOrderFormatters(__);
const { isLocked, createActiveOrderLocked } = useOrderLock(__, currentUserIdRef);

const ordersTableRef = ref(null);
const activeOrderRef = computed(() => props.state?.activeOrder);
const activeOrderLocked = createActiveOrderLocked(activeOrderRef);

// Reset table selections when transition error occurs
watch(
    () => props.state.transitionError,
    (error) => {
        if (error && ordersTableRef.value?.resetSelections) {
            ordersTableRef.value.resetSelections();
        }
    }
);

onBeforeUnmount(() => {
    handleClose();
});

const forceCheckinIfAllowed = async (order) => {
    if (!order?.id || typeof props.forceCheckinOrder !== "function") {
        return null;
    }

    const prompt = __(
        "COM_NXPEASYCART_FORCE_CHECKIN_ORDER",
        "This order is checked out by %s. Force check-in?",
        [order.checked_out_user?.name || __("COM_NXPEASYCART_ERROR_ORDER_CHECKED_OUT_GENERIC", "another user")]
    );

    if (typeof window !== "undefined" && !window.confirm(prompt)) {
        return null;
    }

    try {
        return await props.forceCheckinOrder(order.id);
    } catch (error) {
        return null;
    }
};

const handleView = async (order) => {
    if (!order) return;

    if (isLocked(order)) {
        const forced = await forceCheckinIfAllowed(order);
        if (!forced) return;
    }

    emit("view", order);
};

const handleClose = () => {
    emit("close");
};

const handleTransition = (payload) => {
    emit("transition", payload);
};

const handleBulkTransition = (payload) => {
    emit("bulk-transition", payload);
};
</script>

<style scoped>
.nxp-ec-admin-panel__body--orders {
    position: relative;
}

.nxp-ec-admin-panel__metadata {
    margin-top: 1rem;
    font-size: 0.875rem;
    color: var(--nxp-ec-text-muted, #6c757d);
}
</style>
