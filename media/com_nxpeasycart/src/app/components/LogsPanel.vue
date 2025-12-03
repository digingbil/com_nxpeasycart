<template>
    <section class="nxp-ec-admin-panel nxp-ec-admin-panel--logs">
        <header class="nxp-ec-admin-panel__header">
            <div>
                <h2 class="nxp-ec-admin-panel__title">
                    {{
                        __(
                            "COM_NXPEASYCART_MENU_LOGS",
                            "Logs",
                            [],
                            "logsPanelTitle"
                        )
                    }}
                </h2>
                <p class="nxp-ec-admin-panel__lead">
                    {{
                        __(
                            "COM_NXPEASYCART_LOGS_LEAD",
                            "Review audit events across the store.",
                            [],
                            "logsPanelLead"
                        )
                    }}
                </p>
            </div>
            <div class="nxp-ec-admin-panel__actions">
                <input
                    type="search"
                    class="nxp-ec-admin-search"
                    v-model="state.search"
                    :placeholder="
                        __(
                            'COM_NXPEASYCART_LOGS_SEARCH_PLACEHOLDER',
                            'Search logs',
                            [],
                            'logsSearchPlaceholder'
                        )
                    "
                    :aria-label="
                        __(
                            'COM_NXPEASYCART_LOGS_SEARCH_PLACEHOLDER',
                            'Search logs',
                            [],
                            'logsSearchPlaceholder'
                        )
                    "
                    @keyup.enter="emitSearch"
                />
                <select
                    class="nxp-ec-form-select nxp-ec-admin-select"
                    :value="state.entity"
                    @change="emitFilter($event.target.value)"
                    aria-label="Entity filter"
                >
                    <option value="">
                        {{
                            __(
                                "COM_NXPEASYCART_LOGS_FILTER_ALL",
                                "All entities",
                                [],
                                "logsFilterAll"
                            )
                        }}
                    </option>
                    <option
                        v-for="entity in entityOptions"
                        :key="entity"
                        :value="entity"
                    >
                        {{ formatEntity(entity) }}
                    </option>
                </select>
                <button
                    class="nxp-ec-btn nxp-ec-btn--icon"
                    type="button"
                    @click="emitRefresh"
                    :disabled="state.loading"
                    :title="__(
                        'COM_NXPEASYCART_COUPONS_REFRESH',
                        'Refresh',
                        [],
                        'couponsRefresh'
                    )"
                    :aria-label="__(
                        'COM_NXPEASYCART_COUPONS_REFRESH',
                        'Refresh',
                        [],
                        'couponsRefresh'
                    )"
                >
                    <i class="fa-solid fa-rotate"></i>
                    <span class="nxp-ec-sr-only">
                        {{
                            __(
                                "COM_NXPEASYCART_COUPONS_REFRESH",
                                "Refresh",
                                [],
                                "couponsRefresh"
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
                                        "COM_NXPEASYCART_LOGS_TABLE_TIME",
                                        "Time",
                                        [],
                                        "logsTableTime"
                                    )
                                }}
                            </th>
                            <th scope="col">
                                {{
                                    __(
                                        "COM_NXPEASYCART_LOGS_TABLE_ENTITY",
                                        "Entity",
                                        [],
                                        "logsTableEntity"
                                    )
                                }}
                            </th>
                            <th scope="col">
                                {{
                                    __(
                                        "COM_NXPEASYCART_LOGS_TABLE_ACTION",
                                        "Action",
                                        [],
                                        "logsTableAction"
                                    )
                                }}
                            </th>
                            <th scope="col">
                                {{
                                    __(
                                        "COM_NXPEASYCART_LOGS_TABLE_ACTOR",
                                        "Actor",
                                        [],
                                        "logsTableActor"
                                    )
                                }}
                            </th>
                            <th scope="col">
                                {{
                                    __(
                                        "COM_NXPEASYCART_LOGS_TABLE_DETAILS",
                                        "Details",
                                        [],
                                        "logsTableDetails"
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
                                        "COM_NXPEASYCART_LOGS_EMPTY",
                                        "No audit events captured yet.",
                                        [],
                                        "logsEmpty"
                                    )
                                }}
                            </td>
                        </tr>
                        <tr v-for="log in state.items" :key="log.id">
                            <td class="nxp-ec-admin-table__primary" :data-label="__('COM_NXPEASYCART_LOGS_TABLE_TIME', 'Time')">{{ formatTimestamp(log.created) }}</td>
                            <td :data-label="__('COM_NXPEASYCART_LOGS_TABLE_ENTITY', 'Entity')">
                                <span class="nxp-ec-log-entity">{{
                                    formatEntity(log.entity_type)
                                }}</span>
                                <span class="nxp-ec-log-entity-id"
                                    >#{{ log.entity_id }}</span
                                >
                            </td>
                            <td :data-label="__('COM_NXPEASYCART_LOGS_TABLE_ACTION', 'Action')">{{ log.action }}</td>
                            <td :data-label="__('COM_NXPEASYCART_LOGS_TABLE_ACTOR', 'Actor')">{{ formatActor(log) }}</td>
                            <td :data-label="__('COM_NXPEASYCART_LOGS_TABLE_DETAILS', 'Details')">
                                <pre class="nxp-ec-log-context">{{
                                    formatContext(log.context)
                                }}</pre>
                            </td>
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
                {{ formatTimestampRelative(state.lastUpdated) }}
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed } from "vue";
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
});

const emit = defineEmits(["refresh", "search", "filter", "page"]);

const __ = props.translate;

const state = props.state;

const entityOptions = computed(() => {
    const unique = new Set();

    if (Array.isArray(state.items)) {
        state.items.forEach((item) => {
            if (item?.entity_type) {
                unique.add(item.entity_type);
            }
        });
    }

    if (state.entity) {
        unique.add(state.entity);
    }

    return Array.from(unique).sort();
});

const emitRefresh = () => emit("refresh");
const emitSearch = () => emit("search");
const emitFilter = (value) => emit("filter", value);
const emitPage = (page) => emit("page", page);

const formatTimestamp = (value) => {
    if (!value) {
        return "";
    }

    try {
        const date = new Date(value.replace(" ", "T"));

        if (!Number.isNaN(date.getTime())) {
            return date.toLocaleString();
        }
    } catch (error) {
        // Ignore parsing errors and fall back to raw value.
    }

    return value;
};

const formatEntity = (entity) => {
    if (!entity) {
        return __(
            "COM_NXPEASYCART_LOGS_FILTER_UNKNOWN",
            "Unknown",
            [],
            "logsFilterUnknown"
        );
    }

    switch (entity) {
        case "order":
            return __("COM_NXPEASYCART_MENU_ORDERS", "Orders");
        case "product":
            return __("COM_NXPEASYCART_MENU_PRODUCTS", "Products");
        case "coupon":
            return __("COM_NXPEASYCART_MENU_COUPONS", "Coupons");
        case "customer":
            return __("COM_NXPEASYCART_MENU_CUSTOMERS", "Customers");
        default:
            return entity.charAt(0).toUpperCase() + entity.slice(1);
    }
};

const formatActor = (log) => {
    if (!log?.user) {
        return __(
            "COM_NXPEASYCART_LOGS_ACTOR_UNKNOWN",
            "System",
            [],
            "logsActorUnknown"
        );
    }

    const name = log.user.name || "";
    const username = log.user.username || "";

    if (name && username) {
        return `${name} (${username})`;
    }

    if (name) {
        return name;
    }

    if (username) {
        return username;
    }

    return __(
        "COM_NXPEASYCART_LOGS_ACTOR_UNKNOWN",
        "System",
        [],
        "logsActorUnknown"
    );
};

const formatContext = (context) => {
    if (
        !context ||
        (Array.isArray(context) && !context.length) ||
        (typeof context === "object" && !Object.keys(context).length)
    ) {
        return "—";
    }

    try {
        return JSON.stringify(context, null, 2);
    } catch (error) {
        return String(context);
    }
};

const formatTimestampRelative = (timestamp) => {
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
.nxp-ec-admin-panel--logs .nxp-ec-admin-panel__table {
    overflow-x: auto;
}

.nxp-ec-admin-panel--logs table {
    min-width: 720px;
}

.nxp-ec-log-context {
    margin: 0;
    font-family: var(
        --nxp-ec-font-mono,
        ui-monospace,
        SFMono-Regular,
        Menlo,
        Monaco,
        Consolas,
        "Liberation Mono",
        "Courier New",
        monospace
    );
    font-size: 0.8rem;
    white-space: pre-wrap;
    word-break: break-word;
}

.nxp-ec-log-entity {
    display: inline-block;
    font-weight: 600;
}

.nxp-ec-log-entity-id {
    display: inline-block;
    margin-left: 0.25rem;
    color: var(--nxp-ec-text-muted, #475467);
}

.nxp-ec-admin-select {
    min-width: 160px;
}

/* Tablet breakpoint */
@media (max-width: 768px) {
    .nxp-ec-admin-panel--logs table {
        min-width: 0;
    }

    .nxp-ec-log-context {
        font-size: 0.75rem;
        max-width: 100%;
        overflow-x: auto;
    }

    .nxp-ec-log-entity {
        display: block;
    }

    .nxp-ec-log-entity-id {
        margin-left: 0;
    }
}

/* Mobile breakpoint */
@media (max-width: 480px) {
    .nxp-ec-admin-select {
        min-width: 100%;
        width: 100%;
    }

    .nxp-ec-log-context {
        font-size: 0.7rem;
        max-height: 100px;
        overflow-y: auto;
    }
}
</style>
