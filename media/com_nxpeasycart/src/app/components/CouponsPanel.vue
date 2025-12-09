<template>
    <section class="nxp-ec-admin-panel nxp-ec-admin-panel--coupons">
        <header class="nxp-ec-admin-panel__header">
            <div>
                <h2 class="nxp-ec-admin-panel__title">
                    {{
                        __(
                            "COM_NXPEASYCART_MENU_COUPONS",
                            "Coupons",
                            [],
                            "couponsPanelTitle"
                        )
                    }}
                </h2>
                <p class="nxp-ec-admin-panel__lead">
                    {{
                        __(
                            "COM_NXPEASYCART_COUPONS_LEAD",
                            "Create promotional codes for discounts.",
                            [],
                            "couponsPanelLead"
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
                            'COM_NXPEASYCART_COUPONS_SEARCH_PLACEHOLDER',
                            'Search coupons',
                            [],
                            'couponsSearchPlaceholder'
                        )
                    "
                    v-model="state.search"
                    @keyup.enter="emitSearch"
                    :aria-label="
                        __(
                            'COM_NXPEASYCART_COUPONS_SEARCH_PLACEHOLDER',
                            'Search coupons',
                            [],
                            'couponsSearchPlaceholder'
                        )
                    "
                />
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
                <button
                    class="nxp-ec-btn nxp-ec-btn--primary"
                    type="button"
                    @click="startCreate"
                >
                    <i class="fa-solid fa-plus"></i>
                    {{
                        __(
                            "COM_NXPEASYCART_COUPONS_ADD",
                            "Add coupon",
                            [],
                            "couponsAdd"
                        )
                    }}
                </button>
            </div>
        </header>

        <div v-if="state.error" class="nxp-ec-admin-alert nxp-ec-admin-alert--error">
            {{ state.error }}
        </div>

        <div v-if="state.loading" class="nxp-ec-admin-panel__loading">
            {{
                __(
                    "COM_NXPEASYCART_COUPONS_LOADING",
                    "Loading coupons…",
                    [],
                    "couponsLoading"
                )
            }}
        </div>

        <div v-if="!state.loading" class="nxp-ec-admin-panel__body">
            <div class="nxp-ec-admin-panel__table">
                <table class="nxp-ec-admin-table">
                    <thead>
                        <tr>
                            <th scope="col">
                                {{
                                    __(
                                        "COM_NXPEASYCART_COUPONS_TABLE_CODE",
                                        "Code",
                                        [],
                                        "couponsTableCode"
                                    )
                                }}
                            </th>
                            <th scope="col">
                                {{
                                    __(
                                        "COM_NXPEASYCART_COUPONS_TABLE_TYPE",
                                        "Type",
                                        [],
                                        "couponsTableType"
                                    )
                                }}
                            </th>
                            <th scope="col">
                                {{
                                    __(
                                        "COM_NXPEASYCART_COUPONS_TABLE_VALUE",
                                        "Value",
                                        [],
                                        "couponsTableValue"
                                    )
                                }}
                            </th>
                            <th scope="col">
                                {{
                                    __(
                                        "COM_NXPEASYCART_COUPONS_TABLE_MIN_TOTAL",
                                        "Min. order",
                                        [],
                                        "couponsTableMinTotal"
                                    )
                                }}
                            </th>
                            <th scope="col">
                                {{
                                    __(
                                        "COM_NXPEASYCART_COUPONS_TABLE_ACTIVE",
                                        "Active",
                                        [],
                                        "couponsTableActive"
                                    )
                                }}
                            </th>
                            <th scope="col">
                                {{
                                    __(
                                        "COM_NXPEASYCART_COUPONS_TABLE_USAGE",
                                        "Usage",
                                        [],
                                        "couponsTableUsage"
                                    )
                                }}
                            </th>
                            <th
                                scope="col"
                                class="nxp-ec-admin-table__actions"
                            ></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!state.items.length">
                            <td colspan="7">
                                {{
                                    __(
                                        "COM_NXPEASYCART_COUPONS_EMPTY",
                                        "No coupons configured yet.",
                                        [],
                                        "couponsEmpty"
                                    )
                                }}
                            </td>
                        </tr>
                        <tr
                            v-for="coupon in state.items"
                            :key="coupon.id"
                            :class="{ 'is-active': draft.id === coupon.id }"
                        >
                            <th scope="row" class="nxp-ec-admin-table__primary">
                                {{ coupon.code }}
                            </th>
                            <td :data-label="__('COM_NXPEASYCART_COUPONS_TABLE_TYPE', 'Type')">{{ typeLabel(coupon.type) }}</td>
                            <td :data-label="__('COM_NXPEASYCART_COUPONS_TABLE_VALUE', 'Value')">{{ formatValue(coupon) }}</td>
                            <td :data-label="__('COM_NXPEASYCART_COUPONS_TABLE_MIN_TOTAL', 'Min. order')">
                                {{
                                    formatCurrency(
                                        coupon.min_total_cents,
                                        baseCurrency
                                    )
                                }}
                            </td>
                            <td :data-label="__('COM_NXPEASYCART_COUPONS_TABLE_ACTIVE', 'Active')">
                                <span
                                    class="nxp-ec-badge"
                                    :class="{ 'is-active': coupon.active }"
                                >
                                    <i
                                        :class="
                                            coupon.active
                                                ? 'fa-solid fa-circle-check'
                                                : 'fa-regular fa-circle'
                                        "
                                        aria-hidden="true"
                                    ></i>
                                    {{
                                        coupon.active
                                            ? __("JYES", "Yes")
                                            : __("JNO", "No")
                                    }}
                                </span>
                            </td>
                            <td :data-label="__('COM_NXPEASYCART_COUPONS_TABLE_USAGE', 'Usage')">
                                {{ coupon.times_used
                                }}{{
                                    coupon.max_uses
                                        ? ` / ${coupon.max_uses}`
                                        : ""
                                }}
                            </td>
                            <td class="nxp-ec-admin-table__actions">
                                <button
                                    class="nxp-ec-btn nxp-ec-btn--link nxp-ec-btn--icon"
                                    type="button"
                                    @click="startEdit(coupon)"
                                    :title="__('JEDIT', 'Edit')"
                                    :aria-label="__('JEDIT', 'Edit')"
                                >
                                    <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                    <span class="nxp-ec-sr-only">
                                        {{ __("JEDIT", "Edit") }}
                                    </span>
                                </button>
                                <button
                                    class="nxp-ec-btn nxp-ec-btn--link nxp-ec-btn--danger nxp-ec-btn--icon"
                                    type="button"
                                    @click="confirmDelete(coupon)"
                                    :title="__('COM_NXPEASYCART_REMOVE', 'Remove')"
                                    :aria-label="__('COM_NXPEASYCART_REMOVE', 'Remove')"
                                >
                                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                    <span class="nxp-ec-sr-only">
                                        {{ __("COM_NXPEASYCART_REMOVE", "Remove") }}
                                    </span>
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
                v-if="formOpen"
                class="nxp-ec-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="coupon-modal-title"
                @keydown.esc="cancelEdit"
            >
                <div
                    class="nxp-ec-modal__backdrop"
                    aria-hidden="true"
                    @click="cancelEdit"
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
                            <h3 id="coupon-modal-title">
                                {{
                                    draft.id
                                        ? __("JEDIT", "Edit")
                                        : __(
                                              "COM_NXPEASYCART_COUPONS_ADD",
                                              "Add coupon",
                                              [],
                                              "couponsAdd"
                                          )
                                }}
                            </h3>
                            <button
                                class="nxp-ec-link-button nxp-ec-btn--icon nxp-ec-modal__close-btn"
                                type="button"
                                @click="cancelEdit"
                                :title="__(
                                    'COM_NXPEASYCART_COUPONS_DETAILS_CLOSE',
                                    'Close',
                                    [],
                                    'couponsDetailsClose'
                                )"
                                :aria-label="__(
                                    'COM_NXPEASYCART_COUPONS_DETAILS_CLOSE',
                                    'Close',
                                    [],
                                    'couponsDetailsClose'
                                )"
                            >
                                <i class="fa-solid fa-circle-xmark" aria-hidden="true"></i>
                                <span class="nxp-ec-sr-only">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_COUPONS_DETAILS_CLOSE",
                                            "Close",
                                            [],
                                            "couponsDetailsClose"
                                        )
                                    }}
                                </span>
                            </button>
                        </header>

                        <form
                            class="nxp-ec-form"
                            @submit.prevent="emitSave"
                            autocomplete="off"
                        >
                            <div class="nxp-ec-form-field">
                                <label class="nxp-ec-form-label" for="coupon-code">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_COUPONS_FORM_CODE",
                                            "Coupon code",
                                            [],
                                            "couponsFormCode"
                                        )
                                    }}
                                </label>
                                <input
                                    id="coupon-code"
                                    class="nxp-ec-form-input"
                                    type="text"
                                    v-model.trim="draft.code"
                                    required
                                    maxlength="64"
                                />
                            </div>

                            <div class="nxp-ec-form-field">
                                <label class="nxp-ec-form-label" for="coupon-type">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_COUPONS_FORM_TYPE",
                                            "Discount type",
                                            [],
                                            "couponsFormType"
                                        )
                                    }}
                                </label>
                                <select
                                    id="coupon-type"
                                    class="nxp-ec-form-select"
                                    v-model="draft.type"
                                >
                                    <option value="percent">
                                        {{
                                            __(
                                                "COM_NXPEASYCART_COUPONS_FORM_TYPE_PERCENT",
                                                "Percent",
                                                [],
                                                "couponsFormTypePercent"
                                            )
                                        }}
                                    </option>
                                    <option value="fixed">
                                        {{
                                            __(
                                                "COM_NXPEASYCART_COUPONS_FORM_TYPE_FIXED",
                                                "Fixed amount",
                                                [],
                                                "couponsFormTypeFixed"
                                            )
                                        }}
                                    </option>
                                </select>
                            </div>

                            <div class="nxp-ec-form-field">
                                <label class="nxp-ec-form-label" for="coupon-value">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_COUPONS_FORM_VALUE",
                                            "Discount value",
                                            [],
                                            "couponsFormValue"
                                        )
                                    }}
                                </label>
                                <input
                                    id="coupon-value"
                                    class="nxp-ec-form-input"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    v-model.number="draft.value"
                                    required
                                />
                            </div>

                            <div class="nxp-ec-form-field">
                                <label class="nxp-ec-form-label" for="coupon-min-total">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_COUPONS_FORM_MIN_TOTAL",
                                            "Minimum order total",
                                            [],
                                            "couponsFormMinTotal"
                                        )
                                    }}
                                </label>
                                <input
                                    id="coupon-min-total"
                                    class="nxp-ec-form-input"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    v-model.number="draft.min_total"
                                />
                            </div>

                            <div class="nxp-ec-form-field">
                                <label class="nxp-ec-form-label" for="coupon-start">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_COUPONS_FORM_START",
                                            "Start date",
                                            [],
                                            "couponsFormStart"
                                        )
                                    }}
                                </label>
                                <input
                                    id="coupon-start"
                                    class="nxp-ec-form-input"
                                    type="date"
                                    v-model="draft.start"
                                />
                            </div>

                            <div class="nxp-ec-form-field">
                                <label class="nxp-ec-form-label" for="coupon-end">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_COUPONS_FORM_END",
                                            "End date",
                                            [],
                                            "couponsFormEnd"
                                        )
                                    }}
                                </label>
                                <input
                                    id="coupon-end"
                                    class="nxp-ec-form-input"
                                    type="date"
                                    v-model="draft.end"
                                />
                            </div>

                            <div class="nxp-ec-form-field">
                                <label class="nxp-ec-form-label" for="coupon-max-uses">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_COUPONS_FORM_MAX_USES",
                                            "Maximum uses",
                                            [],
                                            "couponsFormMaxUses"
                                        )
                                    }}
                                </label>
                                <input
                                    id="coupon-max-uses"
                                    class="nxp-ec-form-input"
                                    type="number"
                                    min="0"
                                    step="1"
                                    v-model.number="draft.max_uses"
                                />
                            </div>

                            <div class="nxp-ec-form-field nxp-ec-form-field--inline">
                                <label class="nxp-ec-form-label" for="coupon-active">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_COUPONS_FORM_ACTIVE",
                                            "Active",
                                            [],
                                            "couponsFormActive"
                                        )
                                    }}
                                </label>
                                <input
                                    id="coupon-active"
                                    class="nxp-ec-form-checkbox"
                                    type="checkbox"
                                    v-model="draft.active"
                                />
                            </div>

                            <footer class="nxp-ec-modal__actions">
                                <button
                                    class="nxp-ec-btn"
                                    type="button"
                                    @click="cancelEdit"
                                    :disabled="state.saving"
                                >
                                    {{
                                        __(
                                            "COM_NXPEASYCART_COUPONS_FORM_CANCEL",
                                            "Cancel",
                                            [],
                                            "couponsFormCancel"
                                        )
                                    }}
                                </button>
                                <button
                                    class="nxp-ec-btn nxp-ec-btn--primary"
                                    type="submit"
                                    :disabled="state.saving"
                                >
                                    {{
                                        state.saving
                                            ? __("JPROCESSING_REQUEST", "Saving…")
                                            : __(
                                                  "COM_NXPEASYCART_COUPONS_FORM_SAVE",
                                                  "Save coupon",
                                                  [],
                                                  "couponsFormSave"
                                              )
                                    }}
                                </button>
                            </footer>

                            <button
                                v-if="draft.id"
                                class="nxp-ec-btn nxp-ec-btn--link nxp-ec-btn--danger nxp-ec-btn--icon"
                                type="button"
                                @click="confirmDelete(draft)"
                                :disabled="state.saving"
                            >
                                <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                {{ __("COM_NXPEASYCART_REMOVE", "Remove") }}
                            </button>
                        </form>
                    </aside>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { reactive, watch, computed } from "vue";

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

const emit = defineEmits(["refresh", "search", "page", "save", "delete"]);

const __ = props.translate;

const baseCurrency = computed(() =>
    (props.baseCurrency || "USD").toUpperCase()
);

const draft = reactive({
    open: false,
    id: null,
    code: "",
    type: "percent",
    value: 0,
    min_total: 0,
    start: "",
    end: "",
    max_uses: null,
    active: true,
});

const formOpen = computed(() => draft.open);

const emitRefresh = () => emit("refresh");
const emitSearch = () => emit("search");
const emitPage = (page) => emit("page", page);

const startCreate = () => {
    Object.assign(draft, {
        open: true,
        id: null,
        code: "",
        type: "percent",
        value: 0,
        min_total: 0,
        start: "",
        end: "",
        max_uses: null,
        active: true,
    });
};

const startEdit = (coupon) => {
    Object.assign(draft, {
        open: true,
        id: coupon.id,
        code: coupon.code,
        type: coupon.type,
        value: coupon.value,
        min_total: coupon.min_total ?? (coupon.min_total_cents ?? 0) / 100,
        start: coupon.start ? coupon.start.substring(0, 10) : "",
        end: coupon.end ? coupon.end.substring(0, 10) : "",
        max_uses: coupon.max_uses,
        active: coupon.active,
    });
};

const cancelEdit = () => {
    draft.open = false;
};

const emitSave = () => {
    const payload = {
        id: draft.id || undefined,
        code: draft.code,
        type: draft.type,
        value: draft.value,
        min_total: draft.min_total || 0,
        start: draft.start || null,
        end: draft.end || null,
        max_uses:
            draft.max_uses !== null && draft.max_uses !== ""
                ? draft.max_uses
                : null,
        active: draft.active,
    };

    emit("save", payload);
};

const confirmDelete = (coupon) => {
    const message = __(
        "COM_NXPEASYCART_COUPONS_DELETE_CONFIRM",
        "Delete this coupon?"
    );

    if (window.confirm(message)) {
        emit("delete", [coupon.id]);
        if (draft.id === coupon.id) {
            cancelEdit();
        }
    }
};

const formatCurrency = (cents, currency) => {
    const amount = (Number(cents) || 0) / 100;
    const code = (currency || "").toUpperCase() || baseCurrency.value;

    try {
        return new Intl.NumberFormat(undefined, {
            style: "currency",
            currency: code,
        }).format(amount);
    } catch (error) {
        return `${code} ${amount.toFixed(2)}`;
    }
};

const formatValue = (coupon) => {
    if (coupon.type === "percent") {
        return `${coupon.value}%`;
    }

    return formatCurrency(coupon.value * 100, baseCurrency.value);
};

const typeLabel = (type) => {
    return type === "fixed"
        ? __(
              "COM_NXPEASYCART_COUPONS_FORM_TYPE_FIXED",
              "Fixed amount",
              [],
              "couponsFormTypeFixed"
          )
        : __(
              "COM_NXPEASYCART_COUPONS_FORM_TYPE_PERCENT",
              "Percent",
              [],
              "couponsFormTypePercent"
          );
};

watch(
    () => props.state.saving,
    (saving, wasSaving) => {
        if (wasSaving && !saving && !props.state.error && draft.open) {
            cancelEdit();
        }
    }
);
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

.nxp-ec-admin-panel--coupons .nxp-ec-admin-panel__table {
    flex: 1;
}

.nxp-ec-admin-panel__sidebar form {
    display: grid;
    gap: 1rem;
}

.nxp-ec-admin-table__actions {
    white-space: nowrap;
}

/* Tablet breakpoint */
@media (max-width: 768px) {
    .nxp-ec-admin-panel__sidebar form {
        gap: 0.75rem;
    }
}

/* Mobile breakpoint */
@media (max-width: 480px) {
    .nxp-ec-admin-table__actions {
        white-space: normal;
        flex-direction: column;
        align-items: stretch;
    }

    .nxp-ec-admin-table__actions .nxp-ec-btn {
        width: 100%;
    }
}
</style>
