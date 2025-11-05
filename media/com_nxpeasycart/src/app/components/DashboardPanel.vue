<template>
    <section class="nxp-ec-admin-panel nxp-ec-admin-panel--dashboard">
        <header class="nxp-ec-admin-panel__header">
            <div>
                <h2 class="nxp-ec-admin-panel__title">
                    {{
                        __(
                            "COM_NXPEASYCART_MENU_DASHBOARD",
                            "Dashboard",
                            [],
                            "dashboardTitle"
                        )
                    }}
                </h2>
                <p class="nxp-ec-admin-panel__lead">
                    {{
                        __(
                            "COM_NXPEASYCART_DASHBOARD_LEAD",
                            "Monitor store health and complete setup steps.",
                            [],
                            "dashboardLead"
                        )
                    }}
                </p>
            </div>
            <div class="nxp-ec-admin-panel__actions">
                <button
                    class="nxp-ec-btn"
                    type="button"
                    @click="$emit('refresh')"
                    :disabled="state.loading"
                >
                    {{
                        __(
                            "COM_NXPEASYCART_DASHBOARD_REFRESH",
                            "Refresh",
                            [],
                            "dashboardRefresh"
                        )
                    }}
                </button>
            </div>
        </header>

        <div v-if="state.error" class="nxp-ec-admin-alert nxp-ec-admin-alert--error">
            {{ state.error }}
        </div>

        <div v-else class="nxp-ec-dashboard">
            <div class="nxp-ec-dashboard__metrics">
                <article class="nxp-ec-dashboard__card">
                    <h3>
                        {{
                            __(
                                "COM_NXPEASYCART_DASHBOARD_METRIC_PRODUCTS",
                                "Active products",
                                [],
                                "dashboardMetricProducts"
                            )
                        }}
                    </h3>
                    <p class="nxp-ec-dashboard__value">
                        {{ state.summary.products.active }}
                    </p>
                    <p class="nxp-ec-dashboard__hint">
                        {{
                            __(
                                "COM_NXPEASYCART_DASHBOARD_METRIC_PRODUCTS_TOTAL",
                                "%s total",
                                [String(state.summary.products.total)],
                                "dashboardMetricProductsTotal"
                            )
                        }}
                    </p>
                </article>
                <article class="nxp-ec-dashboard__card">
                    <h3>
                        {{
                            __(
                                "COM_NXPEASYCART_DASHBOARD_METRIC_ORDERS",
                                "Orders today",
                                [],
                                "dashboardMetricOrders"
                            )
                        }}
                    </h3>
                    <p class="nxp-ec-dashboard__value">
                        {{ formatCurrency(state.summary.orders.revenue_today) }}
                    </p>
                    <p class="nxp-ec-dashboard__hint">
                        {{
                            __(
                                "COM_NXPEASYCART_DASHBOARD_METRIC_ORDERS_COUNT",
                                "%s orders",
                                [String(state.summary.orders.total)],
                                "dashboardMetricOrdersCount"
                            )
                        }}
                    </p>
                </article>
                <article class="nxp-ec-dashboard__card">
                    <h3>
                        {{
                            __(
                                "COM_NXPEASYCART_DASHBOARD_METRIC_CUSTOMERS",
                                "Customers",
                                [],
                                "dashboardMetricCustomers"
                            )
                        }}
                    </h3>
                    <p class="nxp-ec-dashboard__value">
                        {{ state.summary.customers.total }}
                    </p>
                    <p class="nxp-ec-dashboard__hint">
                        {{
                            __(
                                "COM_NXPEASYCART_DASHBOARD_METRIC_CUSTOMERS_HINT",
                                "Unique purchasers",
                                [],
                                "dashboardMetricCustomersHint"
                            )
                        }}
                    </p>
                </article>
                <article class="nxp-ec-dashboard__card">
                    <h3>
                        {{
                            __(
                                "COM_NXPEASYCART_DASHBOARD_METRIC_MONTH",
                                "Revenue this month",
                                [],
                                "dashboardMetricMonth"
                            )
                        }}
                    </h3>
                    <p class="nxp-ec-dashboard__value">
                        {{ formatCurrency(state.summary.orders.revenue_month) }}
                    </p>
                    <p class="nxp-ec-dashboard__hint">
                        {{
                            __(
                                "COM_NXPEASYCART_DASHBOARD_METRIC_CURRENCY",
                                "%s",
                                [currencyCode.value],
                                "dashboardMetricCurrency"
                            )
                        }}
                    </p>
                </article>
            </div>

            <section
                class="nxp-ec-dashboard__checklist"
                aria-labelledby="dashboard-checklist-heading"
            >
                <header class="nxp-ec-dashboard__checklist-header">
                    <h3 id="dashboard-checklist-heading">
                        {{
                            __(
                                "COM_NXPEASYCART_DASHBOARD_CHECKLIST",
                                "Launch checklist",
                                [],
                                "dashboardChecklistTitle"
                            )
                        }}
                    </h3>
                    <span class="nxp-ec-dashboard__checklist-progress">
                        {{ completedChecklistItems }} /
                        {{ checklistItems.length }}
                    </span>
                </header>
                <ul class="nxp-ec-dashboard__checklist-list">
                    <li
                        v-for="item in checklistItems"
                        :key="item.id"
                        :class="{ 'is-complete': item.completed }"
                    >
                        <span
                            class="nxp-ec-dashboard__checklist-icon"
                            aria-hidden="true"
                        >
                            <span
                                v-if="item.completed"
                                class="fa-solid fa-circle-check"
                            ></span>
                            <span v-else class="fa-regular fa-circle"></span>
                        </span>
                        <span class="nxp-ec-dashboard__checklist-label">
                            {{ __(item.label, fallbackLabel(item.label)) }}
                        </span>
                        <a
                            v-if="item.link"
                            :href="item.link"
                            class="nxp-ec-link-button"
                        >
                            {{
                                __(
                                    "COM_NXPEASYCART_DASHBOARD_CHECKLIST_ACTION",
                                    "Open",
                                    [],
                                    "dashboardChecklistAction"
                                )
                            }}
                        </a>
                    </li>
                </ul>
            </section>
        </div>
    </section>
</template>

<script setup>
import { computed } from "vue";

const props = defineProps({
    state: {
        type: Object,
        required: true,
    },
    translate: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits(["refresh"]);

const __ = props.translate;

const state = props.state;

const currencyCode = computed(() => {
    const value = state.summary?.currency;

    if (typeof value === "string" && value.trim() !== "") {
        return value.trim();
    }

    return "USD";
});

const formatCurrency = (cents = 0) => {
    const amount = (Number(cents) || 0) / 100;

    try {
        return new Intl.NumberFormat(undefined, {
            style: "currency",
            currency: currencyCode.value,
        }).format(amount);
    } catch (error) {
        return `${currencyCode.value} ${amount.toFixed(2)}`;
    }
};

const checklistItems = computed(() =>
    Array.isArray(state.checklist) ? state.checklist : []
);

const completedChecklistItems = computed(
    () => checklistItems.value.filter((item) => item.completed).length
);

const fallbackLabel = (key) => {
    switch (key) {
        case "COM_NXPEASYCART_CHECKLIST_SET_CURRENCY":
            return "Set base currency";
        case "COM_NXPEASYCART_CHECKLIST_ADD_PRODUCT":
            return "Add first product";
        case "COM_NXPEASYCART_CHECKLIST_CONFIGURE_PAYMENTS":
            return "Configure payment gateway";
        case "COM_NXPEASYCART_CHECKLIST_REVIEW_ORDERS":
            return "Review orders";
        case "COM_NXPEASYCART_CHECKLIST_INVITE_CUSTOMERS":
            return "Invite customers";
        default:
            return key;
    }
};
</script>

<style scoped>
.nxp-ec-dashboard {
    display: grid;
    gap: 2rem;
}

.nxp-ec-dashboard__metrics {
    display: grid;
    gap: 1rem;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
}

.nxp-ec-dashboard__card {
    background: var(--nxp-ec-surface, #fff);
    border-radius: 0.75rem;
    padding: 1.25rem;
    box-shadow: 0 1px 2px rgba(16, 24, 40, 0.08);
}

.nxp-ec-dashboard__card h3 {
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
    color: #475467;
}

.nxp-ec-dashboard__value {
    font-size: 1.75rem;
    font-weight: 600;
    margin: 0;
}

.nxp-ec-dashboard__hint {
    margin: 0;
    color: #667085;
    font-size: 0.85rem;
}

.nxp-ec-dashboard__checklist-list {
    list-style: none !important;
    padding: 0;
    margin: 0;
    display: grid;
    gap: 0.75rem;
}

.nxp-ec-dashboard__checklist-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 0.5rem;
    margin-bottom: 0.5rem;
}

.nxp-ec-dashboard__checklist-list li {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    border-radius: 0.65rem;
    background: rgba(69, 98, 255, 0.04);
}

.nxp-ec-dashboard__checklist-list li.is-complete {
    background: rgba(16, 185, 129, 0.08);
}

.nxp-ec-dashboard__checklist-icon {
    display: inline-flex;
    width: 1.5rem;
    height: 1.5rem;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    color: #6366f1;
}

.nxp-ec-dashboard__checklist-list li.is-complete .nxp-ec-dashboard__checklist-icon {
    color: #10b981;
}

.nxp-ec-dashboard__checklist-icon {
    font-size: 1.2rem;
    width: 1.5rem;
    text-align: center;
    color: #111827;
}

.nxp-ec-dashboard__checklist-label {
    flex: 1;
}
</style>
