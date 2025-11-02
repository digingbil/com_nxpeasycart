<template>
    <section class="nxp-admin-panel nxp-admin-panel--coupons">
        <header class="nxp-admin-panel__header">
            <div>
                <h2 class="nxp-admin-panel__title">
                    {{
                        __(
                            "COM_NXPEASYCART_MENU_COUPONS",
                            "Coupons",
                            [],
                            "couponsPanelTitle"
                        )
                    }}
                </h2>
                <p class="nxp-admin-panel__lead">
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
            <div class="nxp-admin-panel__actions">
                <input
                    type="search"
                    class="nxp-admin-search"
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
                    class="nxp-btn"
                    type="button"
                    @click="emitRefresh"
                    :disabled="state.loading"
                >
                    {{
                        __(
                            "COM_NXPEASYCART_COUPONS_REFRESH",
                            "Refresh",
                            [],
                            "couponsRefresh"
                        )
                    }}
                </button>
                <button
                    class="nxp-btn nxp-btn--primary"
                    type="button"
                    @click="startCreate"
                >
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

        <div v-if="state.error" class="nxp-admin-alert nxp-admin-alert--error">
            {{ state.error }}
        </div>

        <div v-else-if="state.loading" class="nxp-admin-panel__loading">
            {{
                __(
                    "COM_NXPEASYCART_COUPONS_LOADING",
                    "Loading coupons…",
                    [],
                    "couponsLoading"
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
                                class="nxp-admin-table__actions"
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
                            <th scope="row">
                                {{ coupon.code }}
                            </th>
                            <td>{{ typeLabel(coupon.type) }}</td>
                            <td>{{ formatValue(coupon) }}</td>
                            <td>
                                {{
                                    formatCurrency(
                                        coupon.min_total_cents,
                                        baseCurrency
                                    )
                                }}
                            </td>
                            <td>
                                <span
                                    class="nxp-badge"
                                    :class="{ 'is-active': coupon.active }"
                                >
                                    {{
                                        coupon.active
                                            ? __("JYES", "Yes")
                                            : __("JNO", "No")
                                    }}
                                </span>
                            </td>
                            <td>
                                {{ coupon.times_used
                                }}{{
                                    coupon.max_uses
                                        ? ` / ${coupon.max_uses}`
                                        : ""
                                }}
                            </td>
                            <td class="nxp-admin-table__actions">
                                <button
                                    class="nxp-btn nxp-btn--link"
                                    type="button"
                                    @click="startEdit(coupon)"
                                >
                                    {{ __("JEDIT", "Edit") }}
                                </button>
                                <button
                                    class="nxp-btn nxp-btn--link nxp-btn--danger"
                                    type="button"
                                    @click="confirmDelete(coupon)"
                                >
                                    {{ __("COM_NXPEASYCART_REMOVE", "Remove") }}
                                </button>
                            </td>
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
                v-if="formOpen"
                class="nxp-admin-panel__sidebar"
                aria-live="polite"
            >
                <header class="nxp-admin-panel__sidebar-header">
                    <h3>
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
                        class="nxp-link-button"
                        type="button"
                        @click="cancelEdit"
                    >
                        {{
                            __(
                                "COM_NXPEASYCART_COUPONS_DETAILS_CLOSE",
                                "Close",
                                [],
                                "couponsDetailsClose"
                            )
                        }}
                    </button>
                </header>

                <form
                    class="nxp-form"
                    @submit.prevent="emitSave"
                    autocomplete="off"
                >
                    <div class="nxp-form-field">
                        <label class="nxp-form-label" for="coupon-code">
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
                            class="nxp-form-input"
                            type="text"
                            v-model.trim="draft.code"
                            required
                            maxlength="64"
                        />
                    </div>

                    <div class="nxp-form-field">
                        <label class="nxp-form-label" for="coupon-type">
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
                            class="nxp-form-select"
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

                    <div class="nxp-form-field">
                        <label class="nxp-form-label" for="coupon-value">
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
                            class="nxp-form-input"
                            type="number"
                            min="0"
                            step="0.01"
                            v-model.number="draft.value"
                            required
                        />
                    </div>

                    <div class="nxp-form-field">
                        <label class="nxp-form-label" for="coupon-min-total">
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
                            class="nxp-form-input"
                            type="number"
                            min="0"
                            step="0.01"
                            v-model.number="draft.min_total"
                        />
                    </div>

                    <div class="nxp-form-field">
                        <label class="nxp-form-label" for="coupon-start">
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
                            class="nxp-form-input"
                            type="date"
                            v-model="draft.start"
                        />
                    </div>

                    <div class="nxp-form-field">
                        <label class="nxp-form-label" for="coupon-end">
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
                            class="nxp-form-input"
                            type="date"
                            v-model="draft.end"
                        />
                    </div>

                    <div class="nxp-form-field">
                        <label class="nxp-form-label" for="coupon-max-uses">
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
                            class="nxp-form-input"
                            type="number"
                            min="0"
                            step="1"
                            v-model.number="draft.max_uses"
                        />
                    </div>

                    <div class="nxp-form-field nxp-form-field--inline">
                        <label class="nxp-form-label" for="coupon-active">
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
                            class="nxp-form-checkbox"
                            type="checkbox"
                            v-model="draft.active"
                        />
                    </div>

                    <footer class="nxp-modal__actions">
                        <button
                            class="nxp-btn"
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
                            class="nxp-btn nxp-btn--primary"
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
                        class="nxp-btn nxp-btn--link nxp-btn--danger"
                        type="button"
                        @click="confirmDelete(draft)"
                        :disabled="state.saving"
                    >
                        {{ __("COM_NXPEASYCART_REMOVE", "Remove") }}
                    </button>
                </form>
            </aside>
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
.nxp-admin-panel--coupons .nxp-admin-panel__table {
    flex: 1;
}

.nxp-admin-panel__sidebar form {
    display: grid;
    gap: 1rem;
}

.nxp-admin-table__actions {
    white-space: nowrap;
}
</style>
