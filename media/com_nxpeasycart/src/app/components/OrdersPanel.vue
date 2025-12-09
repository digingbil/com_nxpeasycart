<template>
    <section class="nxp-ec-admin-panel nxp-ec-admin-panel--orders">
        <header class="nxp-ec-admin-panel__header">
            <div>
                <h2 class="nxp-ec-admin-panel__title">
                    {{
                        __(
                            "COM_NXPEASYCART_MENU_ORDERS",
                            "Orders",
                            [],
                            "ordersPanelTitle"
                        )
                    }}
                </h2>
                <p class="nxp-ec-admin-panel__lead">
                    {{
                        __(
                            "COM_NXPEASYCART_ORDERS_LEAD",
                            "Track orders and manage fulfilment from this screen.",
                            [],
                            "ordersPanelLead"
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
                            'COM_NXPEASYCART_ORDERS_SEARCH_PLACEHOLDER',
                            'Search orders',
                            [],
                            'ordersSearchPlaceholder'
                        )
                    "
                    v-model="state.search"
                    @keyup.enter="emitSearch"
                    :aria-label="
                        __(
                            'COM_NXPEASYCART_ORDERS_SEARCH_PLACEHOLDER',
                            'Search orders',
                            [],
                            'ordersSearchPlaceholder'
                        )
                    "
                />
                <select
                    class="nxp-ec-admin-select"
                    v-model="state.filterState"
                    @change="emitFilter"
                    :aria-label="
                        __(
                            'COM_NXPEASYCART_ORDERS_FILTER_STATE',
                            'Filter by state',
                            [],
                            'ordersFilterState'
                        )
                    "
                >
                    <option value="">
                        —
                        {{
                            __(
                                "COM_NXPEASYCART_ORDERS_FILTER_STATE",
                                "State",
                                [],
                                "ordersFilterState"
                            )
                        }}
                        —
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
                    @click="emitExport"
                    :disabled="state.loading || state.exporting"
                    :title="__(
                        'COM_NXPEASYCART_ORDERS_EXPORT',
                        'Export CSV',
                        [],
                        'ordersExport'
                    )"
                    :aria-label="__(
                        'COM_NXPEASYCART_ORDERS_EXPORT',
                        'Export CSV',
                        [],
                        'ordersExport'
                    )"
                >
                    <i class="fa-solid fa-file-csv"></i>
                    <span class="nxp-ec-sr-only">
                        {{
                            __(
                                "COM_NXPEASYCART_ORDERS_EXPORT",
                                "Export CSV",
                                [],
                                "ordersExport"
                            )
                        }}
                    </span>
                </button>
                <button
                    class="nxp-ec-btn nxp-ec-btn--icon"
                    type="button"
                    @click="emitRefresh"
                    :disabled="state.loading"
                    :title="__(
                        'COM_NXPEASYCART_ORDERS_REFRESH',
                        'Refresh',
                        [],
                        'ordersRefresh'
                    )"
                    :aria-label="__(
                        'COM_NXPEASYCART_ORDERS_REFRESH',
                        'Refresh',
                        [],
                        'ordersRefresh'
                    )"
                >
                    <i class="fa-solid fa-rotate"></i>
                    <span class="nxp-ec-sr-only">
                        {{
                            __(
                                "COM_NXPEASYCART_ORDERS_REFRESH",
                                "Refresh",
                                [],
                                "ordersRefresh"
                            )
                        }}
                    </span>
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
            <div class="nxp-ec-admin-panel__table">
                <div class="nxp-ec-admin-panel__selection" v-if="hasSelection">
                    <span>{{ selectionSummary }}</span>
                    <select
                        class="nxp-ec-admin-select"
                        v-model="bulkState"
                        :aria-label="
                            __(
                                'COM_NXPEASYCART_ORDERS_BULK_STATE',
                                'Select target state',
                                [],
                                'ordersBulkState'
                            )
                        "
                    >
                        <option value="">
                            {{
                                __(
                                    "COM_NXPEASYCART_ORDERS_BULK_STATE_PLACEHOLDER",
                                    "Choose state…",
                                    [],
                                    "ordersBulkStatePlaceholder"
                                )
                            }}
                        </option>
                        <option
                            v-for="option in state.orderStates"
                            :key="`bulk-${option}`"
                            :value="option"
                        >
                            {{ stateLabel(option) }}
                        </option>
                    </select>
                    <button
                        class="nxp-ec-btn nxp-ec-btn--primary"
                        type="button"
                        :disabled="!bulkState || state.saving"
                        @click="emitBulkTransition"
                    >
                        <i class="fa-solid fa-check"></i>
                        {{
                            __(
                                "COM_NXPEASYCART_ORDERS_BULK_APPLY",
                                "Apply",
                                [],
                                "ordersBulkApply"
                            )
                        }}
                    </button>
                    <button
                        class="nxp-ec-link-button nxp-ec-btn--icon"
                        type="button"
                        @click="emitClearSelection"
                        :title="__(
                            'COM_NXPEASYCART_ORDERS_CLEAR_SELECTION',
                            'Clear',
                            [],
                            'ordersClearSelection'
                        )"
                        :aria-label="__(
                            'COM_NXPEASYCART_ORDERS_CLEAR_SELECTION',
                            'Clear',
                            [],
                            'ordersClearSelection'
                        )"
                    >
                        <i class="fa-solid fa-xmark"></i>
                        <span class="nxp-ec-sr-only">
                            {{
                                __(
                                    "COM_NXPEASYCART_ORDERS_CLEAR_SELECTION",
                                    "Clear",
                                    [],
                                    "ordersClearSelection"
                                )
                            }}
                        </span>
                    </button>
                </div>

                <table class="nxp-ec-admin-table">
                    <thead>
                        <tr>
                            <th scope="col" class="nxp-ec-admin-table__select">
                                {{
                                    __(
                                        "COM_NXPEASYCART_ORDERS_TABLE_SELECT",
                                        "Select",
                                        [],
                                        "ordersTableSelect"
                                    )
                                }}
                            </th>
                            <th scope="col">
                                {{
                                    __(
                                        "COM_NXPEASYCART_ORDERS_TABLE_ORDER",
                                        "Order",
                                        [],
                                        "ordersTableOrder"
                                    )
                                }}
                            </th>
                            <th scope="col">
                                {{
                                    __(
                                        "COM_NXPEASYCART_ORDERS_TABLE_CUSTOMER",
                                        "Customer",
                                        [],
                                        "ordersTableCustomer"
                                    )
                                }}
                            </th>
                            <th scope="col">
                                {{
                                    __(
                                        "COM_NXPEASYCART_ORDERS_TABLE_TOTAL",
                                        "Total",
                                        [],
                                        "ordersTableTotal"
                                    )
                                }}
                            </th>
                            <th scope="col">
                                {{
                                    __(
                                        "COM_NXPEASYCART_ORDERS_TABLE_STATE",
                                        "State",
                                        [],
                                        "ordersTableState"
                                    )
                                }}
                            </th>
                            <th scope="col">
                                {{
                                    __(
                                        "COM_NXPEASYCART_ORDERS_TABLE_UPDATED",
                                        "Updated",
                                        [],
                                        "ordersTableUpdated"
                                    )
                                }}
                            </th>
                            <th scope="col" class="nxp-ec-admin-table__actions">
                                {{
                                    __(
                                        "COM_NXPEASYCART_ORDERS_CHANGE_STATE",
                                        "Change state",
                                        [],
                                        "ordersChangeState"
                                    )
                                }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!state.items.length">
                            <td colspan="7">
                                {{
                                    __(
                                        "COM_NXPEASYCART_ORDERS_EMPTY",
                                        "No orders found.",
                                        [],
                                        "ordersEmpty"
                                    )
                                }}
                            </td>
                        </tr>
                        <tr
                            v-for="order in state.items"
                            :key="order.id"
                            :class="{
                                'is-active':
                                    state.activeOrder &&
                                    state.activeOrder.id === order.id,
                            }"
                        >
                            <td class="nxp-ec-admin-table__select">
                                <input
                                    type="checkbox"
                                    class="nxp-ec-admin-checkbox"
                                    :checked="isSelected(order.id)"
                                    @change="emitToggleSelection(order.id)"
                                    :aria-label="
                                        __(
                                            'COM_NXPEASYCART_ORDERS_SELECT_ORDER',
                                            'Select order',
                                            [],
                                            'ordersSelectOrder'
                                        )
                                    "
                                />
                            </td>
                            <th scope="row" class="nxp-ec-admin-table__primary">
                                <button
                                    class="nxp-ec-link-button"
                                    type="button"
                                    @click="emitView(order)"
                                >
                                    {{ order.order_no }}
                                </button>
                            </th>
                            <td :data-label="__('COM_NXPEASYCART_ORDERS_TABLE_CUSTOMER', 'Customer')">
                                <div>{{ order.email }}</div>
                                <div class="nxp-ec-admin-table__meta">
                                    {{
                                        itemsLabel(
                                            order.items_count ??
                                                (order.items?.length ?? 0)
                                        )
                                    }}
                                </div>
                            </td>
                            <td :data-label="__('COM_NXPEASYCART_ORDERS_TABLE_TOTAL', 'Total')">
                                {{
                                    formatCurrency(
                                        order.total_cents,
                                        order.currency
                                    )
                                }}
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
                                {{
                                    formatDate(order.modified || order.created)
                                }}
                            </td>
                            <td class="nxp-ec-admin-table__actions">
                                <select
                                    class="nxp-ec-admin-select"
                                    v-model="selections[order.id]"
                                    :aria-label="
                                        __(
                                            'COM_NXPEASYCART_ORDERS_CHANGE_STATE',
                                            'Change state',
                                            [],
                                            'ordersChangeState'
                                        )
                                    "
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
                                    :disabled="
                                        state.saving || !hasStateChanged(order)
                                    "
                                    @click="emitTransition(order)"
                                >
                                    {{
                                        __(
                                            "COM_NXPEASYCART_ORDERS_TRANSITIONS",
                                            "State transitions",
                                            [],
                                            "ordersStateTransitions"
                                        )
                                    }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <nav
                    class="nxp-ec-admin-pagination"
                    v-if="state.pagination.pages > 1"
                    :aria-label="__('COM_NXPEASYCART_PAGINATION', 'Pagination')"
                >
                    <button
                        class="nxp-ec-btn"
                        type="button"
                        :disabled="state.pagination.current <= 1"
                        @click="emitPage(state.pagination.current - 1)"
                        :aria-label="__('COM_NXPEASYCART_PAGINATION_PREV', 'Previous page')"
                    >
                        ‹
                    </button>
                    <span class="nxp-ec-admin-pagination__status" aria-current="page">
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
                        :aria-label="__('COM_NXPEASYCART_PAGINATION_NEXT', 'Next page')"
                    >
                        ›
                    </button>
                </nav>
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
                v-if="state.activeOrder"
                class="nxp-ec-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="order-modal-title"
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
                        class="nxp-ec-admin-panel__sidebar nxp-ec-admin-panel__sidebar--orders"
                        aria-live="polite"
                    >
                        <header class="nxp-ec-admin-panel__sidebar-header">
                            <h3 id="order-modal-title">
                                {{
                                    __(
                                        "COM_NXPEASYCART_ORDERS_DETAILS_TITLE",
                                        "Order details",
                                        [],
                                        "ordersDetailsTitle"
                                    )
                                }}
                                · {{ state.activeOrder.order_no }}
                            </h3>
                            <button
                                class="nxp-ec-link-button nxp-ec-btn--icon"
                                type="button"
                @click="emitClose"
                                :title="__(
                                    'COM_NXPEASYCART_ORDERS_DETAILS_CLOSE',
                                    'Close details',
                                    [],
                                    'ordersDetailsClose'
                                )"
                                :aria-label="__(
                                    'COM_NXPEASYCART_ORDERS_DETAILS_CLOSE',
                                    'Close details',
                                    [],
                                    'ordersDetailsClose'
                                )"
                            >
                                <i class="fa-solid fa-circle-xmark"></i>
                                <span class="nxp-ec-sr-only">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_ORDERS_DETAILS_CLOSE",
                                            "Close details",
                                            [],
                                            "ordersDetailsClose"
                                        )
                                    }}
                                </span>
                            </button>
                        </header>

                        <div class="nxp-ec-admin-panel__sidebar-content">
                        <div
                            v-if="state.transitionError"
                            class="nxp-ec-admin-alert nxp-ec-admin-alert--error"
                        >
                            {{ state.transitionError }}
                        </div>

                        <div
                            v-if="state.activeOrder?.needs_review"
                            class="nxp-ec-admin-alert nxp-ec-admin-alert--warning"
                        >
                            <strong>
                                {{
                                    __(
                                        "COM_NXPEASYCART_ORDERS_NEEDS_REVIEW",
                                        "Needs Review",
                                        [],
                                        "ordersNeedsReview"
                                    )
                                }}:
                            </strong>
                            {{ formatReviewReason(state.activeOrder?.review_reason) }}
                        </div>

                        <section
                            class="nxp-ec-admin-panel__section"
                            v-if="statusLink"
                        >
                            <h4>
                                {{
                                    __(
                                        "COM_NXPEASYCART_ORDERS_STATUS_LINK",
                                        "Status link",
                                        [],
                                        "ordersStatusLink"
                                    )
                                }}
                            </h4>
                            <div class="nxp-ec-admin-copy">
                                <input
                                    type="text"
                                    class="nxp-ec-form-input"
                                    :value="statusLink"
                                    readonly
                                />
                                <button
                                    class="nxp-ec-btn nxp-ec-btn--small"
                                    type="button"
                                    :disabled="!statusLink"
                                    @click="copyStatusLink"
                                >
                                    <i class="fa-solid fa-link"></i>
                                    {{
                                        __(
                                            "COM_NXPEASYCART_ORDERS_COPY_LINK",
                                            "Copy link",
                                            [],
                                            "ordersCopyLink"
                                        )
                                    }}
                                </button>
                            </div>
                            <p
                                v-if="copyMessage"
                                class="nxp-ec-admin-panel__muted"
                            >
                                {{ copyMessage }}
                            </p>
                            <div class="nxp-ec-admin-copy" style="margin-top: 0.75rem;">
                                <button
                                    class="nxp-ec-btn nxp-ec-btn--small"
                                    type="button"
                                    :disabled="
                                        !props.state.activeOrder ||
                                        props.state.invoiceLoading
                                    "
                                    @click="emitInvoice"
                                >
                                    <i class="fa-solid fa-file-pdf"></i>
                                    {{
                                        __(
                                            "COM_NXPEASYCART_ORDERS_INVOICE_DOWNLOAD",
                                            "Download invoice (PDF)",
                                            [],
                                            "ordersInvoiceDownload"
                                        )
                                    }}
                                </button>
                                <span
                                    v-if="props.state.invoiceLoading"
                                    class="nxp-ec-admin-panel__muted"
                                >
                                    {{
                                        __(
                                            "COM_NXPEASYCART_LOADING",
                                            "Loading…",
                                            [],
                                            "ordersInvoiceLoading"
                                        )
                                    }}
                                </span>
                            </div>
                        </section>

                        <section class="nxp-ec-admin-panel__section">
                            <h4>
                                {{
                                    __(
                                        "COM_NXPEASYCART_ORDERS_ITEMS_LABEL",
                                        "Items",
                                        [],
                                        "ordersItemsLabel"
                                    )
                                }}
                            </h4>
                            <ul class="nxp-ec-admin-list">
                                <li
                                    v-for="item in state.activeOrder.items"
                                    :key="item.id"
                                >
                                    <div class="nxp-ec-admin-list__title">
                                        {{ item.title }} <small>({{ item.sku }})</small>
                                    </div>
                                    <div class="nxp-ec-admin-list__meta">
                                        × {{ item.qty }} ·
                                        {{
                                            formatCurrency(
                                                item.unit_price_cents,
                                                state.activeOrder.currency
                                            )
                                        }}
                                    </div>
                                </li>
                            </ul>
                        </section>

                        <section class="nxp-ec-admin-panel__section">
                            <h4>
                                {{
                                    __(
                                        "COM_NXPEASYCART_ORDERS_TOTAL_LABEL",
                                        "Total",
                                        [],
                                        "ordersTotalLabel"
                                    )
                                }}
                            </h4>
                            <p class="nxp-ec-admin-panel__total">
                                {{
                                    formatCurrency(
                                        state.activeOrder.total_cents,
                                        state.activeOrder.currency
                                    )
                                }}
                            </p>
                        </section>

                        <div class="nxp-ec-admin-panel__addresses">
                            <section class="nxp-ec-admin-panel__section">
                                <h4>
                                    {{
                                        __(
                                            "COM_NXPEASYCART_ORDERS_BILLING_LABEL",
                                            "Billing",
                                            [],
                                            "ordersBillingLabel"
                                        )
                                    }}
                                </h4>
                                <address class="nxp-ec-admin-address">
                                    <span
                                        v-for="line in addressLines(
                                            state.activeOrder.billing
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
                                            "COM_NXPEASYCART_ORDERS_SHIPPING_LABEL",
                                            "Shipping",
                                            [],
                                            "ordersShippingLabel"
                                        )
                                    }}
                                </h4>
                                <address
                                    class="nxp-ec-admin-address"
                                    v-if="state.activeOrder.shipping"
                                >
                                    <span
                                        v-for="line in addressLines(
                                            state.activeOrder.shipping
                                        )"
                                        :key="line.key"
                                    >
                                        {{ line.value }}
                                    </span>
                                </address>
                                <p v-else class="nxp-ec-admin-panel__muted">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_ORDERS_NO_SHIPPING",
                                            "Shipping information not provided.",
                                            [],
                                            "ordersNoShipping"
                                        )
                                    }}
                                </p>
                            </section>
                        </div>

                        <section class="nxp-ec-admin-panel__section">
                            <h4>
                                {{
                                    __(
                                        "COM_NXPEASYCART_ORDERS_TRACKING_LABEL",
                                        "Tracking",
                                        [],
                                        "ordersTrackingLabel"
                                    )
                                }}
                            </h4>
                            <div class="nxp-ec-form-field">
                                <label
                                    class="nxp-ec-form-label"
                                    for="nxp-ec-tracking-carrier"
                                >
                                    {{
                                        __(
                                            "COM_NXPEASYCART_ORDERS_TRACKING_CARRIER",
                                            "Carrier",
                                            [],
                                            "ordersTrackingCarrier"
                                        )
                                    }}
                                </label>
                                <input
                                    id="nxp-ec-tracking-carrier"
                                    class="nxp-ec-form-input"
                                    type="text"
                                    v-model="trackingDraft.carrier"
                                />
                            </div>
                            <div class="nxp-ec-form-field">
                                <label
                                    class="nxp-ec-form-label"
                                    for="nxp-ec-tracking-number"
                                >
                                    {{
                                        __(
                                            "COM_NXPEASYCART_ORDERS_TRACKING_NUMBER",
                                            "Tracking number",
                                            [],
                                            "ordersTrackingNumber"
                                        )
                                    }}
                                </label>
                                <input
                                    id="nxp-ec-tracking-number"
                                    class="nxp-ec-form-input"
                                    type="text"
                                    v-model="trackingDraft.tracking_number"
                                />
                            </div>
                            <div class="nxp-ec-form-field">
                                <label
                                    class="nxp-ec-form-label"
                                    for="nxp-ec-tracking-url"
                                >
                                    {{
                                        __(
                                            "COM_NXPEASYCART_ORDERS_TRACKING_URL",
                                            "Tracking URL",
                                            [],
                                            "ordersTrackingUrl"
                                        )
                                    }}
                                </label>
                                <input
                                    id="nxp-ec-tracking-url"
                                    class="nxp-ec-form-input"
                                    type="url"
                                    v-model="trackingDraft.tracking_url"
                                />
                            </div>
                            <label
                                v-if="state.activeOrder.state !== 'fulfilled' && state.activeOrder.state !== 'refunded'"
                                class="nxp-ec-form-checkbox"
                            >
                                <input
                                    type="checkbox"
                                    v-model="trackingDraft.markFulfilled"
                                />
                                {{
                                    __(
                                        "COM_NXPEASYCART_ORDERS_TRACKING_MARK_FULFILLED",
                                        "Mark fulfilled",
                                        [],
                                        "ordersTrackingMarkFulfilled"
                                    )
                                }}
                            </label>
                            <div class="nxp-ec-admin-form__actions">
                                <button
                                    class="nxp-ec-btn"
                                    type="button"
                                    :disabled="!trackingChanged || state.saving"
                                    @click="emitSaveTracking"
                                >
                                    <i class="fa-solid fa-truck"></i>
                                    {{
                                        __(
                                            "COM_NXPEASYCART_ORDERS_TRACKING_SAVE",
                                            "Save tracking",
                                            [],
                                            "ordersTrackingSave"
                                        )
                                    }}
                                </button>
                            </div>
                        </section>

                        <section
                            class="nxp-ec-admin-panel__section"
                            v-if="state.activeOrder.state === 'fulfilled' || state.activeOrder.state === 'refunded'"
                        >
                            <h4>
                                {{
                                    __(
                                        "COM_NXPEASYCART_ORDERS_EMAIL_LABEL",
                                        "Email notifications",
                                        [],
                                        "ordersEmailLabel"
                                    )
                                }}
                            </h4>
                            <div class="nxp-ec-admin-form__actions nxp-ec-admin-form__actions--stacked">
                                <button
                                    v-if="state.activeOrder.state === 'fulfilled'"
                                    class="nxp-ec-btn"
                                    type="button"
                                    :disabled="state.saving"
                                    @click="emitSendEmail('shipped')"
                                >
                                    <i class="fa-solid fa-envelope"></i>
                                    {{
                                        emailSentForType('shipped')
                                            ? __(
                                                  "COM_NXPEASYCART_ORDERS_EMAIL_RESEND_SHIPPED",
                                                  "Re-send shipped email",
                                                  [],
                                                  "ordersEmailResendShipped"
                                              )
                                            : __(
                                                  "COM_NXPEASYCART_ORDERS_EMAIL_SEND_SHIPPED",
                                                  "Send shipped email",
                                                  [],
                                                  "ordersEmailSendShipped"
                                              )
                                    }}
                                </button>
                                <button
                                    v-if="state.activeOrder.state === 'refunded'"
                                    class="nxp-ec-btn"
                                    type="button"
                                    :disabled="state.saving"
                                    @click="emitSendEmail('refunded')"
                                >
                                    <i class="fa-solid fa-envelope"></i>
                                    {{
                                        emailSentForType('refunded')
                                            ? __(
                                                  "COM_NXPEASYCART_ORDERS_EMAIL_RESEND_REFUNDED",
                                                  "Re-send refunded email",
                                                  [],
                                                  "ordersEmailResendRefunded"
                                              )
                                            : __(
                                                  "COM_NXPEASYCART_ORDERS_EMAIL_SEND_REFUNDED",
                                                  "Send refunded email",
                                                  [],
                                                  "ordersEmailSendRefunded"
                                              )
                                    }}
                                </button>
                            </div>
                        </section>

                        <!-- Record Payment Section (COD/Bank Transfer) -->
                        <section
                            class="nxp-ec-admin-panel__section"
                            v-if="canRecordPayment"
                        >
                            <h4>
                                {{
                                    __(
                                        "COM_NXPEASYCART_ORDERS_RECORD_PAYMENT",
                                        "Record Payment",
                                        [],
                                        "ordersRecordPayment"
                                    )
                                }}
                            </h4>
                            <p class="nxp-ec-form-help" style="margin-bottom: 0.75rem;">
                                {{
                                    __(
                                        "COM_NXPEASYCART_ORDERS_RECORD_PAYMENT_HELP",
                                        "Manually record payment receipt for this order. This will mark the order as paid.",
                                        [],
                                        "ordersRecordPaymentHelp"
                                    )
                                }}
                            </p>
                            <div class="nxp-ec-form-field">
                                <label
                                    class="nxp-ec-form-label"
                                    for="nxp-ec-payment-amount"
                                >
                                    {{
                                        __(
                                            "COM_NXPEASYCART_ORDERS_PAYMENT_AMOUNT",
                                            "Amount",
                                            [],
                                            "ordersPaymentAmount"
                                        )
                                    }}
                                </label>
                                <input
                                    id="nxp-ec-payment-amount"
                                    class="nxp-ec-form-input"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    v-model="paymentDraft.amount"
                                    :placeholder="
                                        formatCurrency(
                                            state.activeOrder.total_cents,
                                            state.activeOrder.currency
                                        )
                                    "
                                />
                                <small class="nxp-ec-form-help">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_ORDERS_PAYMENT_AMOUNT_HELP",
                                            "Leave empty to use order total",
                                            [],
                                            "ordersPaymentAmountHelp"
                                        )
                                    }}
                                </small>
                            </div>
                            <div class="nxp-ec-form-field">
                                <label
                                    class="nxp-ec-form-label"
                                    for="nxp-ec-payment-reference"
                                >
                                    {{
                                        __(
                                            "COM_NXPEASYCART_ORDERS_PAYMENT_REFERENCE",
                                            "Reference (optional)",
                                            [],
                                            "ordersPaymentReference"
                                        )
                                    }}
                                </label>
                                <input
                                    id="nxp-ec-payment-reference"
                                    class="nxp-ec-form-input"
                                    type="text"
                                    v-model="paymentDraft.reference"
                                    :placeholder="
                                        __(
                                            'COM_NXPEASYCART_ORDERS_PAYMENT_REFERENCE_PLACEHOLDER',
                                            'Receipt number, bank reference...',
                                            [],
                                            'ordersPaymentReferencePlaceholder'
                                        )
                                    "
                                />
                            </div>
                            <div class="nxp-ec-form-field">
                                <label
                                    class="nxp-ec-form-label"
                                    for="nxp-ec-payment-note"
                                >
                                    {{
                                        __(
                                            "COM_NXPEASYCART_ORDERS_PAYMENT_NOTE",
                                            "Note (optional)",
                                            [],
                                            "ordersPaymentNote"
                                        )
                                    }}
                                </label>
                                <textarea
                                    id="nxp-ec-payment-note"
                                    class="nxp-ec-form-textarea"
                                    rows="2"
                                    v-model="paymentDraft.note"
                                ></textarea>
                            </div>
                            <div class="nxp-ec-admin-form__actions" style="margin-top: 0.75rem;">
                                <button
                                    class="nxp-ec-btn nxp-ec-btn--primary"
                                    type="button"
                                    :disabled="state.saving"
                                    @click="emitRecordPayment"
                                >
                                    <i class="icon-credit"></i>
                                    {{
                                        __(
                                            "COM_NXPEASYCART_ORDERS_RECORD_PAYMENT_SUBMIT",
                                            "Record Payment",
                                            [],
                                            "ordersRecordPaymentSubmit"
                                        )
                                    }}
                                </button>
                            </div>
                        </section>

                        <section
                            class="nxp-ec-admin-panel__section"
                            v-if="
                                state.activeOrder.transactions &&
                                state.activeOrder.transactions.length
                            "
                        >
                            <h4>
                                {{
                                    __(
                                        "COM_NXPEASYCART_ORDERS_TRANSACTIONS_LABEL",
                                        "Payments",
                                        [],
                                        "ordersTransactionsLabel"
                                    )
                                }}
                            </h4>
                            <ul class="nxp-ec-admin-list">
                                <li
                                    v-for="transaction in state.activeOrder
                                        .transactions"
                                    :key="transaction.id"
                                >
                                    <div class="nxp-ec-admin-list__title">
                                        {{ transaction.gateway }} ·
                                        {{
                                            formatCurrency(
                                                transaction.amount_cents,
                                                state.activeOrder.currency
                                            )
                                        }}
                                    </div>
                                    <div class="nxp-ec-admin-list__meta">
                                        {{ transactionStatusLabel(transaction) }} ·
                                        {{ formatDate(transaction.created) }}
                                    </div>
                                </li>
                            </ul>
                        </section>

                        <section class="nxp-ec-admin-panel__section">
                            <h4>
                                {{
                                    __(
                                        "COM_NXPEASYCART_ORDERS_NOTE_LABEL",
                                        "Add note",
                                        [],
                                        "ordersNoteLabel"
                                    )
                                }}
                            </h4>
                            <form class="nxp-ec-admin-form" @submit.prevent="emitAddNote">
                                <textarea
                                    class="nxp-ec-form-textarea"
                                    rows="3"
                                    v-model="noteDraft"
                                    :placeholder="
                                        __(
                                            'COM_NXPEASYCART_ORDERS_NOTE_PLACEHOLDER',
                                            'Leave a fulfilment note…',
                                            [],
                                            'ordersNotePlaceholder'
                                        )
                                    "
                                ></textarea>
                                <div class="nxp-ec-admin-form__actions">
                                    <button
                                        class="nxp-ec-btn"
                                        type="submit"
                                        :disabled="!noteReady || state.saving"
                                    >
                                        <i class="fa-solid fa-floppy-disk"></i>
                                        {{
                                            __(
                                                "COM_NXPEASYCART_ORDERS_NOTE_SUBMIT",
                                                "Save note",
                                                [],
                                                "ordersNoteSubmit"
                                            )
                                        }}
                                    </button>
                                </div>
                            </form>
                        </section>

                        <section class="nxp-ec-admin-panel__section">
                            <h4>
                                {{
                                    __(
                                        "COM_NXPEASYCART_ORDERS_TIMELINE_LABEL",
                                        "History",
                                        [],
                                        "ordersTimelineLabel"
                                    )
                                }}
                            </h4>
                            <ul
                                class="nxp-ec-admin-list"
                                v-if="timelineEvents.length"
                            >
                                <li
                                    v-for="entry in timelineEvents"
                                    :key="entry.key"
                                >
                                    <div class="nxp-ec-admin-list__title">
                                        {{ entry.label }}
                                    </div>
                                    <div class="nxp-ec-admin-list__meta">
                                        {{ formatDate(entry.created) }}
                                    </div>
                                </li>
                            </ul>
                            <p v-else class="nxp-ec-admin-panel__muted">
                                {{
                                    __(
                                        "COM_NXPEASYCART_ORDERS_TIMELINE_EMPTY",
                                        "No history recorded yet.",
                                        [],
                                        "ordersTimelineEmpty"
                                    )
                                }}
                            </p>
                        </section>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
</section>
</template>

<script setup>
import { computed, reactive, ref, watch } from "vue";
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
    siteRoot: {
        type: String,
        default: "",
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
]);

const __ = props.translate;

const selections = reactive({});
const bulkState = ref("");
const noteDraft = ref("");
const noteReady = computed(() => noteDraft.value.trim().length > 0);
const copyMessage = ref("");
const trackingDraft = reactive({
    carrier: "",
    tracking_number: "",
    tracking_url: "",
    markFulfilled: false,
});

const paymentDraft = reactive({
    amount: null,
    reference: "",
    note: "",
});

const selectedIds = computed(() => {
    const selection = props.state?.selection;

    if (!selection || typeof selection.values !== "function") {
        return [];
    }

    return Array.from(selection.values());
});

const hasSelection = computed(() => selectedIds.value.length > 0);

const canRecordPayment = computed(() => {
    const order = props.state?.activeOrder;
    if (!order) return false;
    const allowedMethods = ["cod", "bank_transfer"];
    const paymentMethod = String(order.payment_method || "").toLowerCase();
    return order.state === "pending" && allowedMethods.includes(paymentMethod);
});

const paymentReady = computed(() => {
    // Always ready - amount defaults to order total if not specified
    return canRecordPayment.value;
});

watch(selectedIds, (ids) => {
    if (!ids.length) {
        bulkState.value = "";
    }
});

watch(
    () => props.state.activeOrder,
    (order) => {
        noteDraft.value = "";
        copyMessage.value = "";
        trackingDraft.carrier = order?.carrier ?? "";
        trackingDraft.tracking_number = order?.tracking_number ?? "";
        trackingDraft.tracking_url = order?.tracking_url ?? "";
        trackingDraft.markFulfilled = false;
        paymentDraft.amount = null;
        paymentDraft.reference = "";
        paymentDraft.note = "";
    },
    { immediate: true }
);

watch(
    () => props.state.items,
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

// Reset selections to actual order states when a transition error occurs
watch(
    () => props.state.transitionError,
    (error) => {
        if (!error) {
            return;
        }

        const items = props.state?.items;

        if (!Array.isArray(items)) {
            return;
        }

        // Reset all selections back to actual order states
        items.forEach((order) => {
            if (order?.id && order.state) {
                selections[order.id] = order.state;
            }
        });
    }
);

const formatCurrency = (cents, currency) => {
    const amount = (Number(cents) || 0) / 100;
    const code = (currency || "").toUpperCase() || "USD";

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

const formatReviewReason = (reason) => {
    if (!reason) {
        return __("COM_NXPEASYCART_ORDERS_REVIEW_UNKNOWN", "Unknown reason");
    }

    const reasonMap = {
        payment_amount_mismatch: __(
            "COM_NXPEASYCART_ORDERS_REVIEW_AMOUNT_MISMATCH",
            "Payment amount does not match order total. Please verify the transaction in your payment gateway dashboard."
        ),
    };

    return reasonMap[reason] || reason;
};

const itemsLabel = (count) => {
    if (count === 1) {
        return __("COM_NXPEASYCART_ORDERS_BADGE_ITEM", "1 item");
    }

    return __(
        "COM_NXPEASYCART_ORDERS_BADGE_ITEMS",
        "%s items",
        [String(count)]
    );
};

const stateLabel = (state) => {
    if (!state) {
        return "";
    }

    const key = `COM_NXPEASYCART_ORDERS_STATE_${String(state).toUpperCase()}`;

    return __(key, state);
};

const nextStates = (order) =>
    props.state.orderStates.filter((candidate) => candidate !== order.state);

const hasStateChanged = (order) =>
    (selections[order.id] || order.state) !== order.state;

const normaliseSiteRoot = () => {
    if (props.siteRoot && props.siteRoot.trim() !== "") {
        return props.siteRoot.trim();
    }

    if (typeof window !== "undefined" && window.location?.origin) {
        return window.location.origin;
    }

    return "";
};

const statusLink = computed(() => {
    const order = props.state?.activeOrder ?? {};
    const token = String(order.public_token || "").trim();
    const orderNo = String(order.order_no || "").trim();

    if (!token) {
        return "";
    }

    const base = normaliseSiteRoot();

    if (!base) {
        return "";
    }

    const separator = base.endsWith("/") ? "" : "/";
    const params = new URLSearchParams({
        option: "com_nxpeasycart",
        view: "order",
        ref: token,
    });

    if (orderNo) {
        params.set("no", orderNo);
    }

    return `${base}${separator}index.php?${params.toString()}`;
});

const copyStatusLink = async () => {
    if (!statusLink.value) {
        return;
    }

    try {
        if (!navigator?.clipboard?.writeText) {
            throw new Error("Clipboard unavailable");
        }

        await navigator.clipboard.writeText(statusLink.value);
        copyMessage.value = __(
            "COM_NXPEASYCART_ORDERS_LINK_COPIED",
            "Link copied",
            [],
            "ordersLinkCopied"
        );
    } catch (error) {
        // Fallback to a temporary textarea selection for HTTP or blocked clipboard contexts.
        try {
            const el = document.createElement("textarea");
            el.value = statusLink.value;
            el.setAttribute("readonly", "");
            el.style.position = "absolute";
            el.style.left = "-9999px";
            document.body.appendChild(el);
            el.select();
            document.execCommand("copy");
            document.body.removeChild(el);
            copyMessage.value = __(
                "COM_NXPEASYCART_ORDERS_LINK_COPIED",
                "Link copied",
                [],
                "ordersLinkCopiedFallback"
            );
        } catch (fallbackError) {
            copyMessage.value = __(
                "COM_NXPEASYCART_ORDERS_LINK_COPY_FALLBACK",
                "Copy the link below.",
                [],
                "ordersLinkCopyFallback"
            );
        }
    }

    if (copyMessage.value) {
        setTimeout(() => {
            copyMessage.value = "";
        }, 2000);
    }
};

const trackingChanged = computed(() => {
    const order = props.state?.activeOrder ?? {};

    return (
        trackingDraft.carrier !== (order.carrier ?? "") ||
        trackingDraft.tracking_number !== (order.tracking_number ?? "") ||
        trackingDraft.tracking_url !== (order.tracking_url ?? "") ||
        trackingDraft.markFulfilled
    );
});

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

const emitRefresh = () => {
    emit("refresh");
};

const emitExport = () => {
    emit("export");
};

const emitSearch = () => {
    emit("search");
};

const emitFilter = () => {
    emit("filter", props.state.filterState);
};

const emitView = (order) => {
    emit("view", order);
};

const emitClose = () => {
    emit("close");
};

const emitTransition = (order) => {
    const targetState = selections[order.id] || order.state;

    if (targetState === order.state) {
        return;
    }

    emit("transition", { id: order.id, state: targetState });
};

const emitPage = (page) => {
    emit("page", page);
};

const isSelected = (orderId) => props.state?.selection?.has?.(orderId) ?? false;

const emitToggleSelection = (orderId) => {
    emit("toggle-selection", orderId);
};

const emitClearSelection = () => {
    bulkState.value = "";
    emit("clear-selection");
};

const emitBulkTransition = () => {
    if (!hasSelection.value || !bulkState.value) {
        return;
    }

    emit("bulk-transition", {
        ids: [...selectedIds.value],
        state: bulkState.value,
    });
};

const emitAddNote = () => {
    if (!props.state.activeOrder) {
        return;
    }

    const message = noteDraft.value.trim();

    if (!message) {
        return;
    }

    emit("add-note", {
        id: props.state.activeOrder.id,
        message,
    });

    noteDraft.value = "";
};

const emitInvoice = () => {
    if (!props.state.activeOrder) {
        return;
    }

    emit("invoice", props.state.activeOrder.id);
};

const emitSaveTracking = () => {
    if (!props.state.activeOrder || !trackingChanged.value) {
        return;
    }

    emit("save-tracking", {
        id: props.state.activeOrder.id,
        carrier: trackingDraft.carrier,
        tracking_number: trackingDraft.tracking_number,
        tracking_url: trackingDraft.tracking_url,
        mark_fulfilled: trackingDraft.markFulfilled,
    });

    trackingDraft.markFulfilled = false;
};

const emitSendEmail = (type) => {
    if (!props.state.activeOrder) {
        return;
    }

    emit("send-email", {
        id: props.state.activeOrder.id,
        type: type,
    });
};

const emitRecordPayment = () => {
    if (!props.state.activeOrder || !canRecordPayment.value) {
        return;
    }

    const order = props.state.activeOrder;
    const amountCents =
        paymentDraft.amount !== null && paymentDraft.amount !== ""
            ? Math.round(Number(paymentDraft.amount) * 100)
            : order.total_cents;

    emit("record-payment", {
        id: order.id,
        amountCents,
        reference: paymentDraft.reference,
        note: paymentDraft.note,
    });

    // Reset the form
    paymentDraft.amount = null;
    paymentDraft.reference = "";
    paymentDraft.note = "";
};

/**
 * Check if an email of a specific type was already sent for this order.
 * Looks through the order's audit trail for email.sent events.
 */
const emailSentForType = (type) => {
    const order = props.state?.activeOrder;
    if (!order) {
        return false;
    }

    // Check audit trail for email.sent events
    const timeline = order.timeline || order.audit || [];
    return timeline.some((entry) => {
        if (entry.action === 'order.email.sent') {
            return entry.context?.type === type;
        }
        return false;
    });
};

const selectionSummary = computed(() =>
    __(
        "COM_NXPEASYCART_ORDERS_SELECTED_COUNT",
        "%s selected",
        [String(selectedIds.value.length)],
        "ordersSelectedCount"
    )
);

const historyLabel = (entry) => {
    if (!entry || !entry.action) {
        return "";
    }

    switch (entry.action) {
        case "order.created":
            return __(
                "COM_NXPEASYCART_ORDERS_TIMELINE_CREATED",
                "Order created",
                [],
                "ordersTimelineCreated"
            );
        case "order.state.transitioned": {
            const from = stateLabel(entry.context?.from ?? "");
            const to = stateLabel(entry.context?.to ?? "");
            return __(
                "COM_NXPEASYCART_ORDERS_TIMELINE_STATE",
                "State changed from %s to %s",
                [
                    from || entry.context?.from || "",
                    to || entry.context?.to || "",
                ],
                "ordersTimelineState"
            );
        }
        case "order.note":
            return (
                entry.context?.message ||
                __(
                    "COM_NXPEASYCART_ORDERS_TIMELINE_NOTE",
                    "Note added",
                    [],
                    "ordersTimelineNote"
                )
            );
        case "order.payment.recorded": {
            const gateway =
                entry.context?.gateway ||
                __(
                    "COM_NXPEASYCART_ORDERS_TRANSACTION",
                    "Payment",
                    [],
                    "ordersTransactionLabel"
                );
            const amount = formatCurrency(
                entry.context?.amount_cents ?? 0,
                props.state?.activeOrder?.currency
            );

            return __(
                "COM_NXPEASYCART_ORDERS_TIMELINE_PAYMENT_RECORDED",
                "%s recorded (%s)",
                [gateway, amount],
                "ordersTimelinePaymentRecorded"
            );
        }
        case "order.tracking.updated":
            return __(
                "COM_NXPEASYCART_ORDER_TRACKING_EVENT",
                "Tracking updated",
                [],
                "ordersTimelineTracking"
            );
        default:
            return entry.action;
    }
};

const fulfilmentLabel = (event) => {
    if (!event) {
        return "";
    }

    const type = String(event.type || "").toLowerCase();
    const message = String(event.message || "").trim();
    const state = String(event.state || "").toLowerCase();

    if (type === "tracking") {
        return __(
            "COM_NXPEASYCART_ORDER_TRACKING_EVENT",
            "Tracking updated",
            [],
            "ordersTimelineTrackingEvent"
        );
    }

    if (state) {
        return stateLabel(state);
    }

    if (message) {
        return message;
    }

    return type || "";
};

const timelineEvents = computed(() => {
    const events = [];
    const fulfilment = Array.isArray(
        props.state?.activeOrder?.fulfillment_events
    )
        ? props.state.activeOrder.fulfillment_events
        : [];

    fulfilment.forEach((event, index) => {
        events.push({
            label: fulfilmentLabel(event),
            created: event?.at ?? "",
            key: `f-${index}-${event?.at ?? ""}`,
        });
    });

    const audit = Array.isArray(props.state?.activeOrder?.timeline)
        ? props.state.activeOrder.timeline
        : [];

    audit.forEach((entry, index) => {
        events.push({
            label: historyLabel(entry),
            created: entry?.created ?? "",
            key: `a-${entry?.id ?? index}-${entry?.created ?? ""}`,
        });
    });

    return events
        .filter((entry) => entry.label)
        .sort((a, b) => {
            const aDate = new Date(a.created || 0).getTime();
            const bDate = new Date(b.created || 0).getTime();

            return bDate - aDate;
        });
});

const transactionStatusLabel = (transaction) =>
    __(
        `COM_NXPEASYCART_TRANSACTION_STATUS_${String(transaction.status || "").toUpperCase()}`,
        transaction.status || ""
    );

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
/* Order details modal with fixed header */
.nxp-ec-admin-panel__sidebar--orders {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    padding: 0;
}

.nxp-ec-admin-panel__sidebar--orders .nxp-ec-admin-panel__sidebar-header {
    flex-shrink: 0;
    background: var(--nxp-ec-sidebar-bg, #f8f9fa);
    padding: 1.25rem;
    border-bottom: 1px solid var(--nxp-ec-border, #dee2e6);
    margin: 0;
}

.nxp-ec-admin-panel__sidebar--orders .nxp-ec-admin-panel__sidebar-header .nxp-ec-btn--icon i {
    font-size: 1.25rem;
}

.nxp-ec-admin-panel__sidebar--orders .nxp-ec-admin-panel__sidebar-header .nxp-ec-btn--icon:hover {
    text-decoration: none;
    opacity: 0.7;
}

.nxp-ec-admin-panel__sidebar-content {
    flex: 1;
    overflow-y: auto;
    padding: 1.25rem;
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

.nxp-ec-admin-panel__selection {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.nxp-ec-admin-table__select {
    width: 3rem;
    text-align: center;
}

.nxp-ec-admin-table__select .nxp-ec-admin-checkbox {
    margin: 0 auto;
}

.nxp-ec-admin-checkbox {
    width: 1rem;
    height: 1rem;
}

.nxp-ec-admin-panel__section .nxp-ec-admin-list {
    gap: 0.5rem;
}

.nxp-ec-admin-form {
    display: grid;
    gap: 0.75rem;
}

.nxp-ec-admin-form__actions {
    display: flex;
    justify-content: flex-end;
}

.nxp-ec-admin-form__actions--stacked {
    flex-direction: column;
    align-items: stretch;
    gap: 0.5rem;
}

.nxp-ec-admin-copy {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.nxp-ec-admin-copy .nxp-ec-form-input {
    flex: 1;
}

.nxp-ec-admin-panel__metadata {
    pointer-events: none;
}

/* Tablet breakpoint */
@media (max-width: 768px) {
    .nxp-ec-admin-panel__selection {
        flex-wrap: wrap;
    }

    .nxp-ec-admin-panel__selection > span {
        width: 100%;
    }

    .nxp-ec-admin-copy {
        flex-direction: column;
        align-items: stretch;
    }

    .nxp-ec-admin-checkbox {
        width: 1.35rem;
        height: 1.35rem;
    }

    /* Fix orders table overflow */
    .nxp-ec-admin-table__actions {
        flex-direction: column;
        align-items: stretch;
    }

    .nxp-ec-admin-table__actions .nxp-ec-admin-select,
    .nxp-ec-admin-table__actions .nxp-ec-btn {
        width: 100%;
        min-width: 0;
    }
}

/* Mobile breakpoint */
@media (max-width: 480px) {
    .nxp-ec-admin-panel__selection {
        flex-direction: column;
        align-items: stretch;
    }

    .nxp-ec-admin-form__actions {
        flex-direction: column;
    }

    .nxp-ec-admin-form__actions .nxp-ec-btn {
        width: 100%;
    }

    .nxp-ec-admin-checkbox {
        width: 1.5rem;
        height: 1.5rem;
    }

    /* Stack addresses on mobile */
    .nxp-ec-admin-panel__addresses {
        grid-template-columns: 1fr;
    }

    /* Adjust header and content padding on mobile */
    .nxp-ec-admin-panel__sidebar--orders .nxp-ec-admin-panel__sidebar-header {
        padding: 1rem;
    }

    .nxp-ec-admin-panel__sidebar-content {
        padding: 1rem;
    }
}
</style>
