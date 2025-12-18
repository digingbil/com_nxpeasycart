<template>
    <div
        class="nxp-ec-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="order-modal-title"
        @keydown.esc="$emit('close')"
    >
        <div
            class="nxp-ec-modal__backdrop"
            aria-hidden="true"
            @click="$emit('close')"
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
                        {{ __("COM_NXPEASYCART_ORDERS_DETAILS_TITLE", "Order details") }}
                        · {{ order.order_no }}
                    </h3>
                    <button
                        class="nxp-ec-link-button nxp-ec-btn--icon"
                        type="button"
                        @click="$emit('close')"
                        :title="__('COM_NXPEASYCART_ORDERS_DETAILS_CLOSE', 'Close details')"
                        :aria-label="__('COM_NXPEASYCART_ORDERS_DETAILS_CLOSE', 'Close details')"
                    >
                        <i class="fa-solid fa-circle-xmark"></i>
                    </button>
                </header>

                <div class="nxp-ec-admin-panel__sidebar-content">
                    <!-- Error Alert -->
                    <div
                        v-if="transitionError"
                        class="nxp-ec-admin-alert nxp-ec-admin-alert--error"
                    >
                        {{ transitionError }}
                    </div>

                    <!-- Review Warning -->
                    <div
                        v-if="order?.needs_review"
                        class="nxp-ec-admin-alert nxp-ec-admin-alert--warning"
                    >
                        <strong>{{ __("COM_NXPEASYCART_ORDERS_NEEDS_REVIEW", "Needs Review") }}:</strong>
                        {{ formatReviewReason(order?.review_reason) }}
                    </div>

                    <!-- Status Link Section -->
                    <section class="nxp-ec-admin-panel__section" v-if="statusLink">
                        <h4>{{ __("COM_NXPEASYCART_ORDERS_STATUS_LINK", "Status link") }}</h4>
                        <div class="nxp-ec-admin-copy">
                            <input type="text" class="nxp-ec-form-input" :value="statusLink" readonly />
                            <button
                                class="nxp-ec-btn nxp-ec-btn--small"
                                type="button"
                                :disabled="!statusLink"
                                @click="copyStatusLink"
                            >
                                <i class="fa-solid fa-link"></i>
                                {{ __("COM_NXPEASYCART_ORDERS_COPY_LINK", "Copy link") }}
                            </button>
                        </div>
                        <p v-if="statusClipboard.message.value" class="nxp-ec-admin-panel__muted">
                            {{ statusClipboard.message.value }}
                        </p>
                        <div class="nxp-ec-admin-copy" style="margin-top: 0.75rem;">
                            <button
                                class="nxp-ec-btn nxp-ec-btn--small"
                                type="button"
                                :disabled="!order || invoiceLoading"
                                @click="$emit('invoice', order.id)"
                            >
                                <i class="fa-solid fa-file-pdf"></i>
                                {{ __("COM_NXPEASYCART_ORDERS_INVOICE_DOWNLOAD", "Download invoice (PDF)") }}
                            </button>
                            <span v-if="invoiceLoading" class="nxp-ec-admin-panel__muted">
                                {{ __("COM_NXPEASYCART_LOADING", "Loading…") }}
                            </span>
                        </div>
                    </section>

                    <!-- Items Section -->
                    <section class="nxp-ec-admin-panel__section">
                        <h4>{{ __("COM_NXPEASYCART_ORDERS_ITEMS_LABEL", "Items") }}</h4>
                        <ul class="nxp-ec-admin-list">
                            <li v-for="item in order.items" :key="item.id">
                                <div class="nxp-ec-admin-list__title">
                                    {{ item.title }} <small>({{ item.sku }})</small>
                                </div>
                                <div class="nxp-ec-admin-list__meta">
                                    × {{ item.qty }} · {{ formatCurrency(item.unit_price_cents, order.currency) }}
                                </div>
                            </li>
                        </ul>
                    </section>

                    <!-- Downloads Section -->
                    <section
                        class="nxp-ec-admin-panel__section"
                        v-if="order.downloads && order.downloads.length"
                    >
                        <div class="nxp-ec-admin-panel__section-header">
                            <h4>{{ __("COM_NXPEASYCART_DIGITAL_FILES", "Digital downloads") }}</h4>
                            <button
                                v-if="canResendDownloads"
                                type="button"
                                class="nxp-ec-btn nxp-ec-btn--small"
                                :disabled="saving || isLocked"
                                @click="$emit('resend-downloads', { id: order.id })"
                            >
                                <i class="fa-solid fa-envelope"></i>
                                {{ __("COM_NXPEASYCART_ORDERS_RESEND_DOWNLOADS", "Resend email") }}
                            </button>
                        </div>
                        <p v-if="downloadClipboard.message.value" class="nxp-ec-admin-panel__muted">
                            {{ downloadClipboard.message.value }}
                        </p>
                        <div class="nxp-ec-download-list">
                            <article
                                v-for="download in order.downloads"
                                :key="download.id"
                                class="nxp-ec-download-card"
                            >
                                <div class="nxp-ec-download-card__meta">
                                    <div class="nxp-ec-admin-list__title">{{ download.filename }}</div>
                                    <div class="nxp-ec-admin-panel__muted">
                                        {{ downloadCountLabel(download) }} · {{ downloadExpiryLabel(download) }}
                                    </div>
                                </div>
                                <div class="nxp-ec-download-card__actions">
                                    <button
                                        type="button"
                                        class="nxp-ec-btn nxp-ec-btn--link nxp-ec-btn--small"
                                        :disabled="saving || isLocked"
                                        @click="$emit('reset-download', { download_id: download.id, order_id: order.id })"
                                        :title="__('COM_NXPEASYCART_ORDERS_RESET_DOWNLOAD_TITLE', 'Reset download count to 0')"
                                    >
                                        <i class="fa-solid fa-rotate-left"></i>
                                        {{ __("COM_NXPEASYCART_ORDERS_RESET_DOWNLOAD", "Reset") }}
                                    </button>
                                    <button
                                        type="button"
                                        class="nxp-ec-btn nxp-ec-btn--link nxp-ec-btn--small"
                                        @click="copyDownloadLink(download)"
                                    >
                                        {{ __("COM_NXPEASYCART_ORDERS_COPY_LINK", "Copy link") }}
                                    </button>
                                    <a
                                        v-if="download.url"
                                        class="nxp-ec-btn nxp-ec-btn--small"
                                        :href="download.url"
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        {{ __("COM_NXPEASYCART_DOWNLOAD", "Download") }}
                                    </a>
                                </div>
                            </article>
                        </div>
                    </section>

                    <!-- Total Section -->
                    <section class="nxp-ec-admin-panel__section">
                        <h4>{{ __("COM_NXPEASYCART_ORDERS_TOTAL_LABEL", "Total") }}</h4>
                        <p class="nxp-ec-admin-panel__total">
                            {{ formatCurrency(order.total_cents, order.currency) }}
                        </p>
                    </section>

                    <!-- Addresses -->
                    <div class="nxp-ec-admin-panel__addresses">
                        <section class="nxp-ec-admin-panel__section">
                            <h4>{{ __("COM_NXPEASYCART_ORDERS_BILLING_LABEL", "Billing") }}</h4>
                            <address class="nxp-ec-admin-address">
                                <span v-for="line in addressLines(order.billing)" :key="line.key">
                                    {{ line.value }}
                                </span>
                            </address>
                        </section>
                        <section class="nxp-ec-admin-panel__section">
                            <h4>{{ __("COM_NXPEASYCART_ORDERS_SHIPPING_LABEL", "Shipping") }}</h4>
                            <address class="nxp-ec-admin-address" v-if="order.shipping">
                                <span v-for="line in addressLines(order.shipping)" :key="line.key">
                                    {{ line.value }}
                                </span>
                            </address>
                            <p v-else class="nxp-ec-admin-panel__muted">
                                {{ __("COM_NXPEASYCART_ORDERS_NO_SHIPPING", "Shipping information not provided.") }}
                            </p>
                        </section>
                    </div>

                    <!-- Tracking Section -->
                    <section class="nxp-ec-admin-panel__section">
                        <h4>{{ __("COM_NXPEASYCART_ORDERS_TRACKING_LABEL", "Tracking") }}</h4>
                        <div class="nxp-ec-form-field">
                            <label class="nxp-ec-form-label" for="nxp-ec-tracking-carrier">
                                {{ __("COM_NXPEASYCART_ORDERS_TRACKING_CARRIER", "Carrier") }}
                            </label>
                            <input
                                id="nxp-ec-tracking-carrier"
                                class="nxp-ec-form-input"
                                type="text"
                                v-model="trackingDraft.carrier"
                            />
                        </div>
                        <div class="nxp-ec-form-field">
                            <label class="nxp-ec-form-label" for="nxp-ec-tracking-number">
                                {{ __("COM_NXPEASYCART_ORDERS_TRACKING_NUMBER", "Tracking number") }}
                            </label>
                            <input
                                id="nxp-ec-tracking-number"
                                class="nxp-ec-form-input"
                                type="text"
                                v-model="trackingDraft.tracking_number"
                            />
                        </div>
                        <div class="nxp-ec-form-field">
                            <label class="nxp-ec-form-label" for="nxp-ec-tracking-url">
                                {{ __("COM_NXPEASYCART_ORDERS_TRACKING_URL", "Tracking URL") }}
                            </label>
                            <input
                                id="nxp-ec-tracking-url"
                                class="nxp-ec-form-input"
                                type="url"
                                v-model="trackingDraft.tracking_url"
                            />
                        </div>
                        <label
                            v-if="order.state !== 'fulfilled' && order.state !== 'refunded'"
                            class="nxp-ec-form-checkbox"
                        >
                            <input type="checkbox" v-model="trackingDraft.markFulfilled" />
                            {{ __("COM_NXPEASYCART_ORDERS_TRACKING_MARK_FULFILLED", "Mark fulfilled") }}
                        </label>
                        <div class="nxp-ec-admin-form__actions">
                            <button
                                class="nxp-ec-btn"
                                type="button"
                                :disabled="!trackingChanged || saving || isLocked"
                                @click="emitSaveTracking"
                            >
                                <i class="fa-solid fa-truck"></i>
                                {{ __("COM_NXPEASYCART_ORDERS_TRACKING_SAVE", "Save tracking") }}
                            </button>
                        </div>
                    </section>

                    <!-- Email Section -->
                    <section
                        class="nxp-ec-admin-panel__section"
                        v-if="order.state === 'fulfilled' || order.state === 'refunded'"
                    >
                        <h4>{{ __("COM_NXPEASYCART_ORDERS_EMAIL_LABEL", "Email notifications") }}</h4>
                        <div class="nxp-ec-admin-form__actions nxp-ec-admin-form__actions--stacked">
                            <button
                                v-if="order.state === 'fulfilled'"
                                class="nxp-ec-btn"
                                type="button"
                                :disabled="saving || isLocked"
                                @click="$emit('send-email', { id: order.id, type: 'shipped' })"
                            >
                                <i class="fa-solid fa-envelope"></i>
                                {{ emailSentForType('shipped')
                                    ? __("COM_NXPEASYCART_ORDERS_EMAIL_RESEND_SHIPPED", "Re-send shipped email")
                                    : __("COM_NXPEASYCART_ORDERS_EMAIL_SEND_SHIPPED", "Send shipped email")
                                }}
                            </button>
                            <button
                                v-if="order.state === 'refunded'"
                                class="nxp-ec-btn"
                                type="button"
                                :disabled="saving || isLocked"
                                @click="$emit('send-email', { id: order.id, type: 'refunded' })"
                            >
                                <i class="fa-solid fa-envelope"></i>
                                {{ emailSentForType('refunded')
                                    ? __("COM_NXPEASYCART_ORDERS_EMAIL_RESEND_REFUNDED", "Re-send refunded email")
                                    : __("COM_NXPEASYCART_ORDERS_EMAIL_SEND_REFUNDED", "Send refunded email")
                                }}
                            </button>
                        </div>
                    </section>

                    <!-- Record Payment Section -->
                    <section class="nxp-ec-admin-panel__section" v-if="canRecordPayment">
                        <h4>{{ __("COM_NXPEASYCART_ORDERS_RECORD_PAYMENT", "Record Payment") }}</h4>
                        <p class="nxp-ec-form-help" style="margin-bottom: 0.75rem;">
                            {{ __("COM_NXPEASYCART_ORDERS_RECORD_PAYMENT_HELP", "Manually record payment receipt for this order. This will mark the order as paid.") }}
                        </p>
                        <div class="nxp-ec-form-field">
                            <label class="nxp-ec-form-label" for="nxp-ec-payment-amount">
                                {{ __("COM_NXPEASYCART_ORDERS_PAYMENT_AMOUNT", "Amount") }}
                            </label>
                            <input
                                id="nxp-ec-payment-amount"
                                class="nxp-ec-form-input"
                                type="number"
                                step="0.01"
                                min="0"
                                v-model="paymentDraft.amount"
                                :placeholder="formatCurrency(order.total_cents, order.currency)"
                            />
                            <small class="nxp-ec-form-help">
                                {{ __("COM_NXPEASYCART_ORDERS_PAYMENT_AMOUNT_HELP", "Leave empty to use order total") }}
                            </small>
                        </div>
                        <div class="nxp-ec-form-field">
                            <label class="nxp-ec-form-label" for="nxp-ec-payment-reference">
                                {{ __("COM_NXPEASYCART_ORDERS_PAYMENT_REFERENCE", "Reference (optional)") }}
                            </label>
                            <input
                                id="nxp-ec-payment-reference"
                                class="nxp-ec-form-input"
                                type="text"
                                v-model="paymentDraft.reference"
                                :placeholder="__('COM_NXPEASYCART_ORDERS_PAYMENT_REFERENCE_PLACEHOLDER', 'Receipt number, bank reference...')"
                            />
                        </div>
                        <div class="nxp-ec-form-field">
                            <label class="nxp-ec-form-label" for="nxp-ec-payment-note">
                                {{ __("COM_NXPEASYCART_ORDERS_PAYMENT_NOTE", "Note (optional)") }}
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
                                :disabled="saving || isLocked || !canRecordPayment"
                                @click="emitRecordPayment"
                            >
                                <i class="icon-credit"></i>
                                {{ __("COM_NXPEASYCART_ORDERS_RECORD_PAYMENT_SUBMIT", "Record Payment") }}
                            </button>
                        </div>
                    </section>

                    <!-- Transactions Section -->
                    <section
                        class="nxp-ec-admin-panel__section"
                        v-if="order.transactions && order.transactions.length"
                    >
                        <h4>{{ __("COM_NXPEASYCART_ORDERS_TRANSACTIONS_LABEL", "Payments") }}</h4>
                        <ul class="nxp-ec-admin-list">
                            <li v-for="transaction in order.transactions" :key="transaction.id">
                                <div class="nxp-ec-admin-list__title">
                                    {{ transaction.gateway }} · {{ formatCurrency(transaction.amount_cents, order.currency) }}
                                </div>
                                <div class="nxp-ec-admin-list__meta">
                                    {{ transactionStatusLabel(transaction) }} · {{ formatDate(transaction.created) }}
                                </div>
                            </li>
                        </ul>
                    </section>

                    <!-- Notes Section -->
                    <section class="nxp-ec-admin-panel__section">
                        <h4>{{ __("COM_NXPEASYCART_ORDERS_NOTE_LABEL", "Add note") }}</h4>
                        <form class="nxp-ec-admin-form" @submit.prevent="emitAddNote">
                            <textarea
                                class="nxp-ec-form-textarea"
                                rows="3"
                                v-model="noteDraft"
                                :placeholder="__('COM_NXPEASYCART_ORDERS_NOTE_PLACEHOLDER', 'Leave a fulfilment note…')"
                            ></textarea>
                            <div class="nxp-ec-admin-form__actions">
                                <button
                                    class="nxp-ec-btn"
                                    type="submit"
                                    :disabled="!noteReady || saving || isLocked"
                                >
                                    <i class="fa-solid fa-floppy-disk"></i>
                                    {{ __("COM_NXPEASYCART_ORDERS_NOTE_SUBMIT", "Save note") }}
                                </button>
                            </div>
                        </form>
                    </section>

                    <!-- Timeline Section -->
                    <section class="nxp-ec-admin-panel__section">
                        <h4>{{ __("COM_NXPEASYCART_ORDERS_TIMELINE_LABEL", "History") }}</h4>
                        <ul class="nxp-ec-admin-list" v-if="timelineEvents.length">
                            <li v-for="entry in timelineEvents" :key="entry.key">
                                <div class="nxp-ec-admin-list__title">{{ entry.label }}</div>
                                <div class="nxp-ec-admin-list__meta">{{ formatDate(entry.created) }}</div>
                            </li>
                        </ul>
                        <p v-else class="nxp-ec-admin-panel__muted">
                            {{ __("COM_NXPEASYCART_ORDERS_TIMELINE_EMPTY", "No history recorded yet.") }}
                        </p>
                    </section>
                </div>
            </aside>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, watch } from "vue";
import { useOrderFormatters } from "../../composables/useOrderFormatters";
import { useClipboard } from "../../composables/useClipboard";

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
    saving: {
        type: Boolean,
        default: false,
    },
    isLocked: {
        type: Boolean,
        default: false,
    },
    invoiceLoading: {
        type: Boolean,
        default: false,
    },
    transitionError: {
        type: String,
        default: "",
    },
    siteRoot: {
        type: String,
        default: "",
    },
    translate: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits([
    "close",
    "add-note",
    "save-tracking",
    "invoice",
    "send-email",
    "record-payment",
    "resend-downloads",
    "reset-download",
]);

const __ = props.translate;

const {
    formatCurrency,
    formatDate,
    stateLabel,
    formatReviewReason,
    downloadCountLabel,
    downloadExpiryLabel,
    addressLines,
} = useOrderFormatters(__);

const statusClipboard = useClipboard(__);
const downloadClipboard = useClipboard(__);

// Local state
const noteDraft = ref("");
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

// Computed
const noteReady = computed(() => noteDraft.value.trim().length > 0);

const canRecordPayment = computed(() => {
    if (!props.order) return false;
    const allowedMethods = ["cod", "bank_transfer"];
    const paymentMethod = String(props.order.payment_method || "").toLowerCase();
    return props.order.state === "pending" && allowedMethods.includes(paymentMethod);
});

const canResendDownloads = computed(() => {
    if (!props.order) return false;
    const allowedStates = ["paid", "fulfilled"];
    return allowedStates.includes(props.order.state) && props.order.downloads?.length > 0;
});

const trackingChanged = computed(() => {
    return (
        trackingDraft.carrier !== (props.order?.carrier ?? "") ||
        trackingDraft.tracking_number !== (props.order?.tracking_number ?? "") ||
        trackingDraft.tracking_url !== (props.order?.tracking_url ?? "") ||
        trackingDraft.markFulfilled
    );
});

const statusLink = computed(() => {
    const token = String(props.order?.public_token || "").trim();
    const orderNo = String(props.order?.order_no || "").trim();

    if (!token) return "";

    const base = props.siteRoot?.trim() || (typeof window !== "undefined" ? window.location?.origin : "") || "";
    if (!base) return "";

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

const timelineEvents = computed(() => {
    const events = [];
    const fulfilment = Array.isArray(props.order?.fulfillment_events)
        ? props.order.fulfillment_events
        : [];

    fulfilment.forEach((event, index) => {
        events.push({
            label: fulfilmentLabel(event),
            created: event?.at ?? "",
            key: `f-${index}-${event?.at ?? ""}`,
        });
    });

    const audit = Array.isArray(props.order?.timeline) ? props.order.timeline : [];

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

// Helpers
const historyLabel = (entry) => {
    if (!entry || !entry.action) return "";

    switch (entry.action) {
        case "order.created":
            return __("COM_NXPEASYCART_ORDERS_TIMELINE_CREATED", "Order created");
        case "order.state.transitioned": {
            const from = stateLabel(entry.context?.from ?? "");
            const to = stateLabel(entry.context?.to ?? "");
            return __("COM_NXPEASYCART_ORDERS_TIMELINE_STATE", "State changed from %s to %s", [
                from || entry.context?.from || "",
                to || entry.context?.to || "",
            ]);
        }
        case "order.note":
            return entry.context?.message || __("COM_NXPEASYCART_ORDERS_TIMELINE_NOTE", "Note added");
        case "order.payment.recorded": {
            const gateway = entry.context?.gateway || __("COM_NXPEASYCART_ORDERS_TRANSACTION", "Payment");
            const amount = formatCurrency(entry.context?.amount_cents ?? 0, props.order?.currency);
            return __("COM_NXPEASYCART_ORDERS_TIMELINE_PAYMENT_RECORDED", "%s recorded (%s)", [gateway, amount]);
        }
        case "order.tracking.updated":
            return __("COM_NXPEASYCART_ORDER_TRACKING_EVENT", "Tracking updated");
        default:
            return entry.action;
    }
};

const fulfilmentLabel = (event) => {
    if (!event) return "";

    const type = String(event.type || "").toLowerCase();
    const message = String(event.message || "").trim();
    const state = String(event.state || "").toLowerCase();

    if (type === "tracking") {
        return __("COM_NXPEASYCART_ORDER_TRACKING_EVENT", "Tracking updated");
    }

    if (state) {
        return stateLabel(state);
    }

    return message || type || "";
};

const transactionStatusLabel = (transaction) =>
    __(`COM_NXPEASYCART_TRANSACTION_STATUS_${String(transaction.status || "").toUpperCase()}`, transaction.status || "");

const emailSentForType = (type) => {
    const timeline = props.order?.timeline || props.order?.audit || [];
    return timeline.some((entry) => entry.action === "order.email.sent" && entry.context?.type === type);
};

// Actions
const copyStatusLink = () => statusClipboard.copy(statusLink.value);
const copyDownloadLink = (download) => downloadClipboard.copy(download?.url);

const emitSaveTracking = () => {
    if (!props.order || !trackingChanged.value || props.isLocked) return;

    emit("save-tracking", {
        id: props.order.id,
        carrier: trackingDraft.carrier,
        tracking_number: trackingDraft.tracking_number,
        tracking_url: trackingDraft.tracking_url,
        mark_fulfilled: trackingDraft.markFulfilled,
    });

    trackingDraft.markFulfilled = false;
};

const emitAddNote = () => {
    if (!props.order || props.isLocked) return;

    const message = noteDraft.value.trim();
    if (!message) return;

    emit("add-note", { id: props.order.id, message });
    noteDraft.value = "";
};

const emitRecordPayment = () => {
    if (!props.order || !canRecordPayment.value || props.isLocked) return;

    const amountCents =
        paymentDraft.amount !== null && paymentDraft.amount !== ""
            ? Math.round(Number(paymentDraft.amount) * 100)
            : props.order.total_cents;

    emit("record-payment", {
        id: props.order.id,
        amountCents,
        reference: paymentDraft.reference,
        note: paymentDraft.note,
    });

    paymentDraft.amount = null;
    paymentDraft.reference = "";
    paymentDraft.note = "";
};

// Watch for order changes to reset drafts
watch(
    () => props.order,
    (order) => {
        noteDraft.value = "";
        statusClipboard.clearMessage();
        downloadClipboard.clearMessage();
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
</script>

<style scoped>
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
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.nxp-ec-admin-panel__sidebar-content {
    flex: 1;
    overflow-y: auto;
    padding: 1.25rem;
}

.nxp-ec-admin-panel__addresses {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.nxp-ec-admin-panel__addresses .nxp-ec-admin-panel__section {
    margin-bottom: 0;
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
    gap: 0.5rem;
}
</style>
