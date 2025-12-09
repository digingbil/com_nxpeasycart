<template>
    <section class="nxp-ec-admin-panel nxp-ec-admin-panel--customers">
        <header class="nxp-ec-admin-panel__header">
            <div>
                <h2 class="nxp-ec-admin-panel__title">
                    {{
                        __(
                            "COM_NXPEASYCART_MENU_CUSTOMERS",
                            "Customers",
                            [],
                            "customersPanelTitle"
                        )
                    }}
                </h2>
                <p class="nxp-ec-admin-panel__lead">
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
            <div class="nxp-ec-admin-panel__actions">
                <input
                    type="search"
                    class="nxp-ec-admin-search"
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
                    class="nxp-ec-btn nxp-ec-btn--icon"
                    type="button"
                    @click="emitRefresh"
                    :disabled="state.loading"
                    :title="__(
                        'COM_NXPEASYCART_CUSTOMERS_REFRESH',
                        'Refresh',
                        [],
                        'customersRefresh'
                    )"
                    :aria-label="__(
                        'COM_NXPEASYCART_CUSTOMERS_REFRESH',
                        'Refresh',
                        [],
                        'customersRefresh'
                    )"
                >
                    <i class="fa-solid fa-rotate"></i>
                    <span class="nxp-ec-sr-only">
                        {{
                            __(
                                "COM_NXPEASYCART_CUSTOMERS_REFRESH",
                                "Refresh",
                                [],
                                "customersRefresh"
                            )
                        }}
                    </span>
                </button>
            </div>
        </header>

        <div v-if="state.error" class="nxp-ec-admin-alert nxp-ec-admin-alert--error">
            {{ state.error }}
        </div>

        <div v-if="state.loading" class="nxp-ec-admin-panel__body">
            <SkeletonLoader type="table" :rows="5" :columns="5" />
        </div>

        <div v-if="!state.loading" class="nxp-ec-admin-panel__body">
            <div class="nxp-ec-admin-panel__table">
                <table class="nxp-ec-admin-table">
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
                            <th scope="row" class="nxp-ec-admin-table__primary">
                                <button
                                    class="nxp-ec-link-button"
                                    type="button"
                                    @click="emitView(customer)"
                                >
                                    {{ customer.email }}
                                </button>
                            </th>
                            <td :data-label="__('COM_NXPEASYCART_CUSTOMERS_TABLE_NAME', 'Name')">{{ customer.meta?.name || "—" }}</td>
                            <td :data-label="__('COM_NXPEASYCART_CUSTOMERS_TABLE_ORDERS', 'Orders')">{{ customer.orders_count }}</td>
                            <td :data-label="__('COM_NXPEASYCART_CUSTOMERS_TABLE_TOTAL', 'Total spent')">
                                {{
                                    formatCurrency(
                                        customer.total_spent_cents,
                                        customer.currency || baseCurrency
                                    )
                                }}
                            </td>
                            <td :data-label="__('COM_NXPEASYCART_CUSTOMERS_TABLE_LAST', 'Last order')">{{ formatDate(customer.last_order) }}</td>
                        </tr>
                    </tbody>
                </table>

                <div
                    class="nxp-ec-admin-pagination"
                    v-if="state.pagination.pages > 1"
                >
                    <button
                        class="nxp-ec-btn"
                        type="button"
                        :disabled="state.pagination.current <= 1"
                        @click="emitPage(state.pagination.current - 1)"
                    >
                        ‹
                    </button>
                    <span class="nxp-ec-admin-pagination__status">
                        {{ state.pagination.current }} /
                        {{ state.pagination.pages }}
                    </span>
                    <button
                        class="nxp-ec-btn"
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

            <div
                v-if="state.lastUpdated"
                class="nxp-ec-admin-panel__metadata"
                :title="state.lastUpdated"
            >
                {{ __("COM_NXPEASYCART_LAST_UPDATED", "Last updated") }}:
                {{ formatTimestamp(state.lastUpdated) }}
            </div>

            <div
                v-if="state.activeCustomer"
                class="nxp-ec-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="customer-modal-title"
                @keydown.esc="emitClose"
            >
                <div
                    class="nxp-ec-modal__backdrop"
                    aria-hidden="true"
                    @click="emitClose"
                ></div>
                <div
                    class="nxp-ec-modal__dialog nxp-ec-modal__dialog--panel"
                    role="document"
                >
                    <aside
                        class="nxp-ec-admin-panel__sidebar"
                        aria-live="polite"
                    >
                        <header class="nxp-ec-admin-panel__sidebar-header">
                            <h3 id="customer-modal-title">
                                {{ state.activeCustomer.email }}
                            </h3>
                            <button
                                class="nxp-ec-link-button nxp-ec-btn--icon nxp-ec-modal__close-btn"
                                type="button"
                                @click="emitClose"
                                :title="__(
                                    'COM_NXPEASYCART_CUSTOMERS_DETAILS_CLOSE',
                                    'Close details',
                                    [],
                                    'customersDetailsClose'
                                )"
                                :aria-label="__(
                                    'COM_NXPEASYCART_CUSTOMERS_DETAILS_CLOSE',
                                    'Close details',
                                    [],
                                    'customersDetailsClose'
                                )"
                            >
                                <i class="fa-solid fa-circle-xmark"></i>
                                <span class="nxp-ec-sr-only">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_CUSTOMERS_DETAILS_CLOSE",
                                            "Close details",
                                            [],
                                            "customersDetailsClose"
                                        )
                                    }}
                                </span>
                            </button>
                        </header>

                        <section class="nxp-ec-admin-panel__section">
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
                            <dl class="nxp-ec-admin-summary">
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

                        <div class="nxp-ec-admin-panel__addresses">
                            <section class="nxp-ec-admin-panel__section">
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
                                <address class="nxp-ec-admin-address">
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

                            <section class="nxp-ec-admin-panel__section">
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
                                    class="nxp-ec-admin-address"
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
                                <p v-else class="nxp-ec-admin-panel__muted">
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
                        </div>

                        <section class="nxp-ec-admin-panel__section">
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
                                class="nxp-ec-admin-list"
                                v-if="
                                    state.activeCustomer.orders &&
                                    state.activeCustomer.orders.length
                                "
                            >
                                <li
                                    v-for="order in state.activeCustomer.orders"
                                    :key="order.id"
                                >
                                    <div class="nxp-ec-admin-list__title">
                                        {{ order.order_no }} ·
                                        {{
                                            formatCurrency(
                                                order.total_cents,
                                                order.currency || baseCurrency
                                            )
                                        }}
                                    </div>
                                    <div class="nxp-ec-admin-list__meta">
                                        {{ stateLabel(order.state) }} ·
                                        {{ formatDate(order.created) }}
                                    </div>
                                </li>
                            </ul>
                            <p v-else class="nxp-ec-admin-panel__muted">
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

                        <section class="nxp-ec-admin-panel__section nxp-ec-gdpr-section">
                            <h4>
                                {{
                                    __(
                                        "COM_NXPEASYCART_GDPR_TITLE",
                                        "GDPR Actions",
                                        [],
                                        "gdprTitle"
                                    )
                                }}
                            </h4>
                            <p class="nxp-ec-admin-panel__muted nxp-ec-gdpr-description">
                                {{
                                    __(
                                        "COM_NXPEASYCART_GDPR_DESCRIPTION",
                                        "Handle customer data requests under GDPR.",
                                        [],
                                        "gdprDescription"
                                    )
                                }}
                            </p>
                            <div class="nxp-ec-gdpr-actions">
                                <button
                                    class="nxp-ec-btn nxp-ec-btn--secondary"
                                    type="button"
                                    @click="emitGdprExport(state.activeCustomer.email)"
                                    :disabled="state.gdprLoading"
                                    :title="__(
                                        'COM_NXPEASYCART_GDPR_EXPORT_TOOLTIP',
                                        'Download all customer data as JSON',
                                        [],
                                        'gdprExportTooltip'
                                    )"
                                >
                                    <i class="fa-solid fa-download"></i>
                                    {{
                                        __(
                                            "COM_NXPEASYCART_GDPR_EXPORT",
                                            "Export Data",
                                            [],
                                            "gdprExport"
                                        )
                                    }}
                                </button>
                                <button
                                    class="nxp-ec-btn nxp-ec-btn--danger"
                                    type="button"
                                    @click="emitGdprAnonymise(state.activeCustomer.email)"
                                    :disabled="state.gdprLoading"
                                    :title="__(
                                        'COM_NXPEASYCART_GDPR_ANONYMISE_TOOLTIP',
                                        'Permanently anonymise customer data (cannot be undone)',
                                        [],
                                        'gdprAnonymiseTooltip'
                                    )"
                                >
                                    <i class="fa-solid fa-user-slash"></i>
                                    {{
                                        __(
                                            "COM_NXPEASYCART_GDPR_ANONYMISE",
                                            "Anonymise",
                                            [],
                                            "gdprAnonymise"
                                        )
                                    }}
                                </button>
                            </div>
                            <p v-if="state.gdprMessage" class="nxp-ec-gdpr-message" :class="{ 'nxp-ec-gdpr-message--success': state.gdprSuccess }">
                                {{ state.gdprMessage }}
                            </p>
                        </section>
                    </aside>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import SkeletonLoader from "./SkeletonLoader.vue";

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

const emit = defineEmits(["refresh", "search", "page", "view", "close", "gdprExport", "gdprAnonymise"]);

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
const emitGdprExport = (email) => emit("gdprExport", email);
const emitGdprAnonymise = (email) => emit("gdprAnonymise", email);

const formatTimestamp = (timestamp) => {
    if (!timestamp) {
        return "";
    }

    try {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = now - date;
        const seconds = Math.floor(diff / 1000);
        const minutes = Math.floor(seconds / 60);
        const hours = Math.floor(minutes / 60);

        if (seconds < 60) {
            return __("COM_NXPEASYCART_TIME_SECONDS_AGO", "just now");
        } else if (minutes < 60) {
            return __(
                "COM_NXPEASYCART_TIME_MINUTES_AGO",
                "%s minutes ago",
                [minutes]
            );
        } else if (hours < 24) {
            return __(
                "COM_NXPEASYCART_TIME_HOURS_AGO",
                "%s hours ago",
                [hours]
            );
        } else {
            return date.toLocaleString();
        }
    } catch (error) {
        return timestamp;
    }
};
</script>

<style scoped>
.nxp-ec-modal__close-btn {
    width: auto;
    height: auto;
    padding: 0;
}

.nxp-ec-modal__close-btn i {
    font-size: 1.25rem;
}

.nxp-ec-modal__close-btn:hover {
    text-decoration: none;
    opacity: 0.7;
}

/* Side by side addresses */
.nxp-ec-admin-panel__addresses {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.nxp-ec-admin-panel__addresses .nxp-ec-admin-panel__section {
    margin-bottom: 0;
}

.nxp-ec-admin-panel--customers .nxp-ec-admin-panel__table {
    flex: 1;
}

.nxp-ec-admin-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 0.75rem;
}

.nxp-ec-admin-summary dt {
    font-size: 0.75rem;
    text-transform: uppercase;
    color: var(--nxp-ec-text-muted, #667085);
    margin: 0;
}

.nxp-ec-admin-summary dd {
    margin: 0;
    font-weight: 500;
    color: var(--nxp-ec-text, #212529);
}

.nxp-ec-gdpr-section {
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid var(--nxp-ec-border, #e5e7eb);
}

.nxp-ec-gdpr-description {
    font-size: 0.875rem;
    margin-bottom: 0.75rem;
}

.nxp-ec-gdpr-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.nxp-ec-gdpr-actions .nxp-ec-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
}

.nxp-ec-btn--danger {
    background-color: #dc2626;
    color: #fff;
    border-color: #dc2626;
}

.nxp-ec-btn--danger:hover:not(:disabled) {
    background-color: #b91c1c;
    border-color: #b91c1c;
}

.nxp-ec-gdpr-message {
    margin-top: 0.75rem;
    padding: 0.5rem 0.75rem;
    border-radius: 0.25rem;
    font-size: 0.875rem;
    background-color: #fef2f2;
    color: #991b1b;
}

.nxp-ec-gdpr-message--success {
    background-color: #f0fdf4;
    color: #166534;
}

/* Tablet breakpoint */
@media (max-width: 768px) {
    .nxp-ec-admin-summary {
        grid-template-columns: repeat(2, 1fr);
    }

    .nxp-ec-gdpr-actions {
        flex-direction: column;
    }

    .nxp-ec-gdpr-actions .nxp-ec-btn {
        width: 100%;
        justify-content: center;
    }
}

/* Mobile breakpoint */
@media (max-width: 480px) {
    .nxp-ec-admin-summary {
        grid-template-columns: 1fr;
    }

    /* Stack addresses on mobile */
    .nxp-ec-admin-panel__addresses {
        grid-template-columns: 1fr;
    }
}
</style>
