<template>
    <section class="nxp-admin-panel nxp-admin-panel--customers">
        <header class="nxp-admin-panel__header">
            <div>
                <h2 class="nxp-admin-panel__title">
                    {{
                        __(
                            "COM_NXPEASYCART_MENU_CUSTOMERS",
                            "Customers",
                            [],
                            "customersPanelTitle"
                        )
                    }}
                </h2>
                <p class="nxp-admin-panel__lead">
                    {{
                        __(
                            "COM_NXPEASYCART_CUSTOMERS_LEAD",
                            "Understand your buyers and their order history.",
                            [],
                            "customersPanelLead"
                        )
                    }}
                </p>
            </div>
            <div class="nxp-admin-panel__actions">
                <input
                    type="search"
                    class="nxp-admin-search"
                    :placeholder="
                        __(
                            'COM_NXPEASYCART_CUSTOMERS_SEARCH_PLACEHOLDER',
                            'Search customers',
                            [],
                            'customersSearchPlaceholder'
                        )
                    "
                    v-model="state.search"
                    @keyup.enter="emitSearch"
                    :aria-label="
                        __(
                            'COM_NXPEASYCART_CUSTOMERS_SEARCH_PLACEHOLDER',
                            'Search customers',
                            [],
                            'customersSearchPlaceholder'
                        )
                    "
                />
                <button
                    class="nxp-btn"
                    type="button"
                    @click="emitRefresh"
                    :disabled="state.loading"
                >
                    {{
                        __(
                            "COM_NXPEASYCART_CUSTOMERS_REFRESH",
                            "Refresh",
                            [],
                            "customersRefresh"
                        )
                    }}
                </button>
            </div>
        </header>

        <div v-if="state.error" class="nxp-admin-alert nxp-admin-alert--error">
            {{ state.error }}
        </div>

        <div v-else-if="state.loading" class="nxp-admin-panel__loading">
            {{
                __(
                    "COM_NXPEASYCART_CUSTOMERS_LOADING",
                    "Loading customers…",
                    [],
                    "customersLoading"
                )
            }}
        </div>

        <div v-else class="nxp-admin-panel__body">
            <div class="nxp-admin-panel__table">
                <table class="nxp-admin-table">
                    <thead>
                        <tr>
                            <th scope="col">
                                {{
                                    __(
                                        "COM_NXPEASYCART_CUSTOMERS_TABLE_EMAIL",
                                        "Email",
                                        [],
                                        "customersTableEmail"
                                    )
                                }}
                            </th>
                            <th scope="col">
                                {{
                                    __(
                                        "COM_NXPEASYCART_CUSTOMERS_TABLE_NAME",
                                        "Name",
                                        [],
                                        "customersTableName"
                                    )
                                }}
                            </th>
                            <th scope="col">
                                {{
                                    __(
                                        "COM_NXPEASYCART_CUSTOMERS_TABLE_ORDERS",
                                        "Orders",
                                        [],
                                        "customersTableOrders"
                                    )
                                }}
                            </th>
                            <th scope="col">
                                {{
                                    __(
                                        "COM_NXPEASYCART_CUSTOMERS_TABLE_TOTAL",
                                        "Total spent",
                                        [],
                                        "customersTableTotal"
                                    )
                                }}
                            </th>
                            <th scope="col">
                                {{
                                    __(
                                        "COM_NXPEASYCART_CUSTOMERS_TABLE_LAST",
                                        "Last order",
                                        [],
                                        "customersTableLast"
                                    )
                                }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!state.items.length">
                            <td colspan="5">
                                {{
                                    __(
                                        "COM_NXPEASYCART_CUSTOMERS_EMPTY",
                                        "No customers yet.",
                                        [],
                                        "customersEmpty"
                                    )
                                }}
                            </td>
                        </tr>
                        <tr
                            v-for="customer in state.items"
                            :key="customer.email"
                            :class="{
                                'is-active':
                                    state.activeCustomer &&
                                    state.activeCustomer.email ===
                                        customer.email,
                            }"
                        >
                            <th scope="row">
                                <button
                                    class="nxp-link-button"
                                    type="button"
                                    @click="emitView(customer)"
                                >
                                    {{ customer.email }}
                                </button>
                            </th>
                            <td>{{ customer.meta?.name || "—" }}</td>
                            <td>{{ customer.orders_count }}</td>
                            <td>
                                {{
                                    formatCurrency(
                                        customer.total_spent_cents,
                                        customer.currency || baseCurrency
                                    )
                                }}
                            </td>
                            <td>{{ formatDate(customer.last_order) }}</td>
                        </tr>
                    </tbody>
                </table>

                <div
                    class="nxp-admin-pagination"
                    v-if="state.pagination.pages > 1"
                >
                    <button
                        class="nxp-btn"
                        type="button"
                        :disabled="state.pagination.current <= 1"
                        @click="emitPage(state.pagination.current - 1)"
                    >
                        ‹
                    </button>
                    <span class="nxp-admin-pagination__status">
                        {{ state.pagination.current }} /
                        {{ state.pagination.pages }}
                    </span>
                    <button
                        class="nxp-btn"
                        type="button"
                        :disabled="
                            state.pagination.current >= state.pagination.pages
                        "
                        @click="emitPage(state.pagination.current + 1)"
                    >
                        ›
                    </button>
                </div>
            </div>

            <aside
                v-if="state.activeCustomer"
                class="nxp-admin-panel__sidebar"
                aria-live="polite"
            >
                <header class="nxp-admin-panel__sidebar-header">
                    <h3>
                        {{ state.activeCustomer.email }}
                    </h3>
                    <button
                        class="nxp-link-button"
                        type="button"
                        @click="emitClose"
                    >
                        {{
                            __(
                                "COM_NXPEASYCART_CUSTOMERS_DETAILS_CLOSE",
                                "Close details",
                                [],
                                "customersDetailsClose"
                            )
                        }}
                    </button>
                </header>

                <section class="nxp-admin-panel__section">
                    <h4>
                        {{
                            __(
                                "COM_NXPEASYCART_CUSTOMERS_DETAILS_SUMMARY",
                                "Summary",
                                [],
                                "customersDetailsSummary"
                            )
                        }}
                    </h4>
                    <dl class="nxp-admin-summary">
                        <div>
                            <dt>
                                {{
                                    __(
                                        "COM_NXPEASYCART_CUSTOMERS_DETAILS_NAME",
                                        "Name",
                                        [],
                                        "customersDetailsName"
                                    )
                                }}
                            </dt>
                            <dd>
                                {{ state.activeCustomer.meta?.name || "—" }}
                            </dd>
                        </div>
                        <div>
                            <dt>
                                {{
                                    __(
                                        "COM_NXPEASYCART_CUSTOMERS_DETAILS_TOTAL",
                                        "Total spent",
                                        [],
                                        "customersDetailsTotal"
                                    )
                                }}
                            </dt>
                            <dd>
                                {{
                                    formatCurrency(
                                        state.activeCustomer.total_spent_cents,
                                        state.activeCustomer.currency ||
                                            baseCurrency
                                    )
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt>
                                {{
                                    __(
                                        "COM_NXPEASYCART_CUSTOMERS_DETAILS_ORDERS",
                                        "Orders",
                                        [],
                                        "customersDetailsOrders"
                                    )
                                }}
                            </dt>
                            <dd>{{ state.activeCustomer.orders_count }}</dd>
                        </div>
                        <div>
                            <dt>
                                {{
                                    __(
                                        "COM_NXPEASYCART_CUSTOMERS_DETAILS_LAST",
                                        "Last order",
                                        [],
                                        "customersDetailsLast"
                                    )
                                }}
                            </dt>
                            <dd>
                                {{
                                    formatDate(state.activeCustomer.last_order)
                                }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section class="nxp-admin-panel__section">
                    <h4>
                        {{
                            __(
                                "COM_NXPEASYCART_CUSTOMERS_DETAILS_BILLING",
                                "Billing address",
                                [],
                                "customersDetailsBilling"
                            )
                        }}
                    </h4>
                    <address class="nxp-admin-address">
                        <span
                            v-for="line in addressLines(
                                state.activeCustomer.meta?.billing
                            )"
                            :key="line.key"
                        >
                            {{ line.value }}
                        </span>
                    </address>
                </section>

                <section class="nxp-admin-panel__section">
                    <h4>
                        {{
                            __(
                                "COM_NXPEASYCART_CUSTOMERS_DETAILS_SHIPPING",
                                "Shipping address",
                                [],
                                "customersDetailsShipping"
                            )
                        }}
                    </h4>
                    <address
                        class="nxp-admin-address"
                        v-if="state.activeCustomer.meta?.shipping"
                    >
                        <span
                            v-for="line in addressLines(
                                state.activeCustomer.meta.shipping
                            )"
                            :key="line.key"
                        >
                            {{ line.value }}
                        </span>
                    </address>
                    <p v-else class="nxp-admin-panel__muted">
                        {{
                            __(
                                "COM_NXPEASYCART_CUSTOMERS_DETAILS_NO_SHIPPING",
                                "No shipping address on file.",
                                [],
                                "customersDetailsNoShipping"
                            )
                        }}
                    </p>
                </section>

                <section class="nxp-admin-panel__section">
                    <h4>
                        {{
                            __(
                                "COM_NXPEASYCART_CUSTOMERS_DETAILS_ORDERS_LIST",
                                "Recent orders",
                                [],
                                "customersDetailsOrdersList"
                            )
                        }}
                    </h4>
                    <ul
                        class="nxp-admin-list"
                        v-if="
                            state.activeCustomer.orders &&
                            state.activeCustomer.orders.length
                        "
                    >
                        <li
                            v-for="order in state.activeCustomer.orders"
                            :key="order.id"
                        >
                            <div class="nxp-admin-list__title">
                                {{ order.order_no }} ·
                                {{
                                    formatCurrency(
                                        order.total_cents,
                                        order.currency || baseCurrency
                                    )
                                }}
                            </div>
                            <div class="nxp-admin-list__meta">
                                {{ stateLabel(order.state) }} ·
                                {{ formatDate(order.created) }}
                            </div>
                        </li>
                    </ul>
                    <p v-else class="nxp-admin-panel__muted">
                        {{
                            __(
                                "COM_NXPEASYCART_CUSTOMERS_DETAILS_NO_ORDERS",
                                "No orders yet.",
                                [],
                                "customersDetailsNoOrders"
                            )
                        }}
                    </p>
                </section>
            </aside>
        </div>
    </section>
</template>

<script setup>
const props = defineProps({
    state: {
        type: Object,
        required: true,
    },
    translate: {
        type: Function,
        required: true,
    },
    baseCurrency: {
        type: String,
        default: "USD",
    },
});

const emit = defineEmits(["refresh", "search", "page", "view", "close"]);

const __ = props.translate;

const formatCurrency = (cents, currency) => {
    const amount = (Number(cents) || 0) / 100;
    const code = (currency || "").toUpperCase() || props.baseCurrency;

    try {
        return new Intl.NumberFormat(undefined, {
            style: "currency",
            currency: code,
        }).format(amount);
    } catch (error) {
        return `${code} ${amount.toFixed(2)}`;
    }
};

const formatDate = (iso) => {
    if (!iso) {
        return "";
    }

    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return iso;
    }

    return date.toLocaleString();
};

const addressLines = (address) => {
    if (!address || typeof address !== "object") {
        return [];
    }

    return Object.entries(address)
        .filter(([, value]) => value != null && `${value}`.trim() !== "")
        .map(([key, value]) => ({
            key,
            value: `${value}`.trim(),
        }));
};

const stateLabel = (state) => {
    if (!state) {
        return "";
    }

    const key = `COM_NXPEASYCART_ORDERS_STATE_${String(state).toUpperCase()}`;

    return __(key, state);
};

const emitRefresh = () => emit("refresh");
const emitSearch = () => emit("search");
const emitPage = (page) => emit("page", page);
const emitView = (customer) => emit("view", customer);
const emitClose = () => emit("close");
</script>

<style scoped>
.nxp-admin-panel--customers .nxp-admin-panel__table {
    flex: 1;
}

.nxp-admin-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 0.75rem;
}

.nxp-admin-summary dt {
    font-size: 0.75rem;
    text-transform: uppercase;
    color: #667085;
    margin: 0;
}

.nxp-admin-summary dd {
    margin: 0;
    font-weight: 500;
}
</style>
