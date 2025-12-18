<template>
    <div class="nxp-ec-admin-panel__table">
        <div class="nxp-ec-admin-panel__selection" v-if="hasSelection">
            <span>{{ selectionSummary }}</span>
            <select
                class="nxp-ec-admin-select"
                v-model="localBulkState"
                :aria-label="__('COM_NXPEASYCART_ORDERS_BULK_STATE', 'Select target state')"
            >
                <option value="">
                    {{ __("COM_NXPEASYCART_ORDERS_BULK_STATE_PLACEHOLDER", "Choose state…") }}
                </option>
                <option
                    v-for="option in orderStates"
                    :key="`bulk-${option}`"
                    :value="option"
                >
                    {{ stateLabel(option) }}
                </option>
            </select>
            <button
                class="nxp-ec-btn nxp-ec-btn--primary"
                type="button"
                :disabled="!localBulkState || saving"
                @click="emitBulkTransition"
            >
                <i class="fa-solid fa-check"></i>
                {{ __("COM_NXPEASYCART_ORDERS_BULK_APPLY", "Apply") }}
            </button>
            <button
                class="nxp-ec-link-button nxp-ec-btn--icon"
                type="button"
                @click="$emit('clear-selection')"
                :title="__('COM_NXPEASYCART_ORDERS_CLEAR_SELECTION', 'Clear')"
                :aria-label="__('COM_NXPEASYCART_ORDERS_CLEAR_SELECTION', 'Clear')"
            >
                <i class="fa-solid fa-xmark"></i>
                <span class="nxp-ec-sr-only">
                    {{ __("COM_NXPEASYCART_ORDERS_CLEAR_SELECTION", "Clear") }}
                </span>
            </button>
        </div>

        <table class="nxp-ec-admin-table">
            <thead>
                <tr>
                    <th scope="col" class="nxp-ec-admin-table__select">
                        {{ __("COM_NXPEASYCART_ORDERS_TABLE_SELECT", "Select") }}
                    </th>
                    <th scope="col">
                        {{ __("COM_NXPEASYCART_ORDERS_TABLE_ORDER", "Order") }}
                    </th>
                    <th scope="col">
                        {{ __("COM_NXPEASYCART_ORDERS_TABLE_CUSTOMER", "Customer") }}
                    </th>
                    <th scope="col">
                        {{ __("COM_NXPEASYCART_ORDERS_TABLE_TOTAL", "Total") }}
                    </th>
                    <th scope="col">
                        {{ __("COM_NXPEASYCART_ORDERS_TABLE_STATE", "State") }}
                    </th>
                    <th scope="col">
                        {{ __("COM_NXPEASYCART_ORDERS_TABLE_UPDATED", "Updated") }}
                    </th>
                    <th scope="col" class="nxp-ec-admin-table__actions">
                        {{ __("COM_NXPEASYCART_ORDERS_CHANGE_STATE", "Change state") }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="!items.length">
                    <td colspan="7">
                        {{ __("COM_NXPEASYCART_ORDERS_EMPTY", "No orders found.") }}
                    </td>
                </tr>
                <tr
                    v-for="order in items"
                    :key="order.id"
                    :class="{ 'is-active': activeOrderId === order.id }"
                >
                    <td class="nxp-ec-admin-table__select">
                        <input
                            type="checkbox"
                            class="nxp-ec-admin-checkbox"
                            :checked="isSelected(order.id)"
                            @change="$emit('toggle-selection', order.id)"
                            :disabled="isLocked(order)"
                            :aria-label="__('COM_NXPEASYCART_ORDERS_SELECT_ORDER', 'Select order')"
                        />
                    </td>
                    <th scope="row" class="nxp-ec-admin-table__primary">
                        <button
                            class="nxp-ec-link-button"
                            type="button"
                            @click="$emit('view', order)"
                            :disabled="saving"
                        >
                            {{ order.order_no }}
                        </button>
                        <div v-if="order.checked_out" class="nxp-ec-admin-table__meta">
                            <span class="nxp-ec-status nxp-ec-status--muted">
                                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                                {{ lockLabel(order) }}
                            </span>
                        </div>
                    </th>
                    <td :data-label="__('COM_NXPEASYCART_ORDERS_TABLE_CUSTOMER', 'Customer')">
                        <div>{{ order.email }}</div>
                        <div class="nxp-ec-admin-table__meta">
                            {{ itemsLabel(order.items_count ?? (order.items?.length ?? 0)) }}
                        </div>
                    </td>
                    <td :data-label="__('COM_NXPEASYCART_ORDERS_TABLE_TOTAL', 'Total')">
                        {{ formatCurrency(order.total_cents, order.currency) }}
                    </td>
                    <td :data-label="__('COM_NXPEASYCART_ORDERS_TABLE_STATE', 'State')">
                        <span class="nxp-ec-badge">
                            {{ stateLabel(order.state) }}
                        </span>
                        <span
                            v-if="order.needs_review"
                            class="nxp-ec-badge nxp-ec-badge--warning"
                            :title="order.review_reason || __('COM_NXPEASYCART_ORDERS_NEEDS_REVIEW', 'Needs review')"
                        >
                            {{ __("COM_NXPEASYCART_ORDERS_NEEDS_REVIEW_SHORT", "Review") }}
                        </span>
                    </td>
                    <td :data-label="__('COM_NXPEASYCART_ORDERS_TABLE_UPDATED', 'Updated')">
                        {{ formatDate(order.modified || order.created) }}
                    </td>
                    <td class="nxp-ec-admin-table__actions">
                        <select
                            class="nxp-ec-admin-select"
                            :value="selections[order.id] || order.state"
                            @change="updateSelection(order.id, $event.target.value)"
                            :disabled="isLocked(order)"
                            :aria-label="__('COM_NXPEASYCART_ORDERS_CHANGE_STATE', 'Change state')"
                        >
                            <option :value="order.state">
                                {{ stateLabel(order.state) }}
                            </option>
                            <option
                                v-for="option in nextStates(order)"
                                :key="option"
                                :value="option"
                            >
                                {{ stateLabel(option) }}
                            </option>
                        </select>
                        <button
                            class="nxp-ec-btn"
                            type="button"
                            :disabled="saving || !hasStateChanged(order) || isLocked(order)"
                            @click="emitTransition(order)"
                        >
                            {{ __("COM_NXPEASYCART_ORDERS_TRANSITIONS", "State transitions") }}
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>

        <nav
            class="nxp-ec-admin-pagination"
            v-if="pagination.pages > 1"
            :aria-label="__('COM_NXPEASYCART_PAGINATION', 'Pagination')"
        >
            <button
                class="nxp-ec-btn"
                type="button"
                :disabled="pagination.current <= 1"
                @click="$emit('page', pagination.current - 1)"
                :aria-label="__('COM_NXPEASYCART_PAGINATION_PREV', 'Previous page')"
            >
                ‹
            </button>
            <span class="nxp-ec-admin-pagination__status" aria-current="page">
                {{ pagination.current }} / {{ pagination.pages }}
            </span>
            <button
                class="nxp-ec-btn"
                type="button"
                :disabled="pagination.current >= pagination.pages"
                @click="$emit('page', pagination.current + 1)"
                :aria-label="__('COM_NXPEASYCART_PAGINATION_NEXT', 'Next page')"
            >
                ›
            </button>
        </nav>
    </div>
</template>

<script setup>
import { ref, computed, watch, reactive } from "vue";
import { useOrderFormatters } from "../../composables/useOrderFormatters";
import { useOrderLock } from "../../composables/useOrderLock";

const props = defineProps({
    items: {
        type: Array,
        required: true,
    },
    orderStates: {
        type: Array,
        required: true,
    },
    selection: {
        type: Set,
        default: () => new Set(),
    },
    pagination: {
        type: Object,
        required: true,
    },
    activeOrderId: {
        type: Number,
        default: null,
    },
    saving: {
        type: Boolean,
        default: false,
    },
    currentUserId: {
        type: Number,
        default: 0,
    },
    translate: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits([
    "view",
    "transition",
    "bulk-transition",
    "toggle-selection",
    "clear-selection",
    "page",
]);

const __ = props.translate;
const currentUserIdRef = computed(() => props.currentUserId);

const { formatCurrency, formatDate, stateLabel, itemsLabel } = useOrderFormatters(__);
const { isLocked, lockLabel } = useOrderLock(__, currentUserIdRef);

const selections = reactive({});
const localBulkState = ref("");

const selectedIds = computed(() => {
    if (!props.selection || typeof props.selection.values !== "function") {
        return [];
    }
    return Array.from(props.selection.values());
});

const hasSelection = computed(() => selectedIds.value.length > 0);

const selectionSummary = computed(() => {
    const count = selectedIds.value.length;
    if (count === 1) {
        return __("COM_NXPEASYCART_ORDERS_SELECTED_ONE", "1 order selected");
    }
    return __("COM_NXPEASYCART_ORDERS_SELECTED_MANY", "%s orders selected", [String(count)]);
});

const isSelected = (orderId) => props.selection?.has?.(orderId) ?? false;

const nextStates = (order) =>
    props.orderStates.filter((candidate) => candidate !== order.state);

const hasStateChanged = (order) =>
    (selections[order.id] || order.state) !== order.state;

const updateSelection = (orderId, value) => {
    selections[orderId] = value;
};

const emitTransition = (order) => {
    if (!order || isLocked(order)) {
        return;
    }

    const targetState = selections[order.id] || order.state;

    if (targetState === order.state) {
        return;
    }

    emit("transition", { id: order.id, state: targetState });
};

const emitBulkTransition = () => {
    if (!hasSelection.value || !localBulkState.value) {
        return;
    }

    const ids = [...selectedIds.value].filter((id) => {
        const order = props.items?.find?.((item) => item.id === id) ?? null;
        return !isLocked(order);
    });

    if (!ids.length) {
        return;
    }

    emit("bulk-transition", {
        ids,
        state: localBulkState.value,
    });
};

// Sync selections with items
watch(
    () => props.items,
    (items) => {
        if (!Array.isArray(items)) {
            return;
        }

        items.forEach((order) => {
            if (!order || typeof order.id === "undefined") {
                return;
            }

            if (!selections[order.id]) {
                selections[order.id] = order.state;
            }
        });
    },
    { immediate: true }
);

// Clear bulk state when selection is cleared
watch(selectedIds, (ids) => {
    if (!ids.length) {
        localBulkState.value = "";
    }
});

// Expose for parent to reset selections on error
defineExpose({
    resetSelections: () => {
        props.items?.forEach((order) => {
            if (order?.id && order.state) {
                selections[order.id] = order.state;
            }
        });
    },
});
</script>
