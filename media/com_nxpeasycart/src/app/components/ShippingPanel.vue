<template>
    <section class="nxp-ec-admin-panel nxp-ec-admin-panel--shipping">
        <header class="nxp-ec-admin-panel__header">
            <div>
                <h2 class="nxp-ec-admin-panel__title">
                    {{
                        __(
                            "COM_NXPEASYCART_MENU_SHIPPING",
                            "Shipping Methods",
                            [],
                            "shippingPanelTitle"
                        )
                    }}
                </h2>
                <p class="nxp-ec-admin-panel__lead">
                    {{
                        __(
                            "COM_NXPEASYCART_SHIPPING_LEAD",
                            "Configure shipping rules and rates for your store.",
                            [],
                            "shippingPanelLead"
                        )
                    }}
                </p>
            </div>
            <div class="nxp-ec-admin-panel__actions">
                <button
                    class="nxp-ec-btn nxp-ec-btn--icon"
                    type="button"
                    @click="refresh"
                    :disabled="state.loading"
                    :title="__(
                        'COM_NXPEASYCART_SHIPPING_REFRESH',
                        'Refresh',
                        [],
                        'shippingRefresh'
                    )"
                    :aria-label="__(
                        'COM_NXPEASYCART_SHIPPING_REFRESH',
                        'Refresh',
                        [],
                        'shippingRefresh'
                    )"
                >
                    <i class="fa-solid fa-rotate"></i>
                    <span class="nxp-ec-sr-only">
                        {{
                            __(
                                "COM_NXPEASYCART_SHIPPING_REFRESH",
                                "Refresh",
                                [],
                                "shippingRefresh"
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
                            "COM_NXPEASYCART_SHIPPING_ADD",
                            "Add shipping rule",
                            [],
                            "shippingAdd"
                        )
                    }}
                </button>
            </div>
        </header>

        <div
            v-if="state.error"
            class="nxp-ec-admin-alert nxp-ec-admin-alert--error"
        >
            {{ state.error }}
        </div>

        <div v-if="state.loading" class="nxp-ec-admin-panel__loading">
            {{
                __(
                    "COM_NXPEASYCART_SHIPPING_LOADING",
                    "Loading shipping rules…",
                    [],
                    "shippingLoading"
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
                                        "COM_NXPEASYCART_SHIPPING_NAME",
                                        "Name",
                                        [],
                                        "shippingName"
                                    )
                                }}
                            </th>
                            <th scope="col">
                                {{
                                    __(
                                        "COM_NXPEASYCART_SHIPPING_TYPE",
                                        "Type",
                                        [],
                                        "shippingType"
                                    )
                                }}
                            </th>
                            <th scope="col">
                                {{
                                    __(
                                        "COM_NXPEASYCART_SHIPPING_PRICE",
                                        "Price",
                                        [],
                                        "shippingPrice"
                                    )
                                }}
                            </th>
                            <th scope="col">
                                {{
                                    __(
                                        "COM_NXPEASYCART_SHIPPING_THRESHOLD",
                                        "Threshold",
                                        [],
                                        "shippingThreshold"
                                    )
                                }}
                            </th>
                            <th scope="col">
                                {{
                                    __(
                                        "COM_NXPEASYCART_SHIPPING_REGIONS",
                                        "Regions",
                                        [],
                                        "shippingRegions"
                                    )
                                }}
                            </th>
                            <th scope="col">
                                {{
                                    __(
                                        "COM_NXPEASYCART_SHIPPING_ACTIVE",
                                        "Active",
                                        [],
                                        "shippingActive"
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
                                        "COM_NXPEASYCART_SHIPPING_EMPTY",
                                        "No shipping rules defined.",
                                        [],
                                        "shippingEmpty"
                                    )
                                }}
                            </td>
                        </tr>
                        <tr
                            v-for="rule in state.items"
                            :key="rule.id"
                            :class="{
                                'is-active': draft.id === rule.id,
                            }"
                        >
                            <th scope="row" class="nxp-ec-admin-table__primary">{{ rule.name }}</th>
                            <td :data-label="__('COM_NXPEASYCART_SHIPPING_TYPE', 'Type')">{{ typeLabel(rule.type) }}</td>
                            <td :data-label="__('COM_NXPEASYCART_SHIPPING_PRICE', 'Price')">
                                {{
                                    formatCurrency(
                                        rule.price_cents,
                                        baseCurrency
                                    )
                                }}
                            </td>
                            <td :data-label="__('COM_NXPEASYCART_SHIPPING_THRESHOLD', 'Threshold')">
                                {{
                                    rule.type === "free_over"
                                        ? formatCurrency(
                                              rule.threshold_cents,
                                              baseCurrency
                                          )
                                        : "—"
                                }}
                            </td>
                            <td :data-label="__('COM_NXPEASYCART_SHIPPING_REGIONS', 'Regions')">
                                {{
                                    rule.regions && rule.regions.length
                                        ? rule.regions.join(", ")
                                        : __(
                                              "COM_NXPEASYCART_SHIPPING_ALL",
                                              "All regions",
                                              [],
                                              "shippingAll"
                                          )
                                }}
                            </td>
                            <td :data-label="__('COM_NXPEASYCART_SHIPPING_ACTIVE', 'Active')">
                                <span
                                    class="nxp-ec-badge"
                                    :class="{ 'is-active': rule.active }"
                                >
                                    <i
                                        :class="
                                            rule.active
                                                ? 'fa-solid fa-circle-check'
                                                : 'fa-regular fa-circle'
                                        "
                                        aria-hidden="true"
                                    ></i>
                                    {{
                                        rule.active
                                            ? __("JYES", "Yes")
                                            : __("JNO", "No")
                                    }}
                                </span>
                            </td>
                            <td class="nxp-ec-admin-table__actions">
                                <button
                                    class="nxp-ec-btn nxp-ec-btn--link nxp-ec-btn--icon"
                                    type="button"
                                    @click="startEdit(rule)"
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
                                    @click="confirmDelete(rule)"
                                    :title="__('COM_NXPEASYCART_REMOVE', 'Remove')"
                                    :aria-label="__('COM_NXPEASYCART_REMOVE', 'Remove')"
                                >
                                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                    <span class="nxp-ec-sr-only">
                                        {{
                                            __(
                                                "COM_NXPEASYCART_REMOVE",
                                                "Remove"
                                            )
                                        }}
                                    </span>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="formOpen"
                class="nxp-ec-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="shipping-modal-title"
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
                            <h3 id="shipping-modal-title">
                                {{
                                    draft.id
                                        ? __("JEDIT", "Edit")
                                        : __(
                                              "COM_NXPEASYCART_SHIPPING_ADD",
                                              "Add shipping rule",
                                              [],
                                              "shippingAdd"
                                          )
                                }}
                            </h3>
                            <button
                                class="nxp-ec-link-button nxp-ec-btn--icon nxp-ec-modal__close-btn"
                                type="button"
                                @click="cancelEdit"
                                :title="__(
                                    'COM_NXPEASYCART_CLOSE',
                                    'Close',
                                    [],
                                    'close'
                                )"
                                :aria-label="__(
                                    'COM_NXPEASYCART_CLOSE',
                                    'Close',
                                    [],
                                    'close'
                                )"
                            >
                                <i class="fa-solid fa-circle-xmark" aria-hidden="true"></i>
                                <span class="nxp-ec-sr-only">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_CLOSE",
                                            "Close",
                                            [],
                                            "close"
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
                                <label class="nxp-ec-form-label" for="shipping-name">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_SHIPPING_NAME",
                                            "Name",
                                            [],
                                            "shippingName"
                                        )
                                    }}
                                </label>
                                <input
                                    id="shipping-name"
                                    class="nxp-ec-form-input"
                                    type="text"
                                    v-model.trim="draft.name"
                                    required
                                    :placeholder="__('COM_NXPEASYCART_SHIPPING_NAME_PLACEHOLDER', 'e.g. Standard Shipping', [], 'shippingNamePlaceholder')"
                                />
                            </div>

                            <div class="nxp-ec-form-field">
                                <label class="nxp-ec-form-label" for="shipping-type">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_SHIPPING_TYPE",
                                            "Type",
                                            [],
                                            "shippingType"
                                        )
                                    }}
                                </label>
                                <select
                                    id="shipping-type"
                                    class="nxp-ec-form-select"
                                    v-model="draft.type"
                                >
                                    <option value="flat">
                                        {{
                                            __(
                                                "COM_NXPEASYCART_SHIPPING_TYPE_FLAT",
                                                "Flat rate",
                                                [],
                                                "shippingTypeFlat"
                                            )
                                        }}
                                    </option>
                                    <option value="free_over">
                                        {{
                                            __(
                                                "COM_NXPEASYCART_SHIPPING_TYPE_FREE",
                                                "Free over threshold",
                                                [],
                                                "shippingTypeFree"
                                            )
                                        }}
                                    </option>
                                </select>
                                <p class="nxp-ec-form-help">
                                    {{
                                        draft.type === "free_over"
                                            ? __(
                                                  "COM_NXPEASYCART_SHIPPING_TYPE_FREE_HELP",
                                                  "Shipping is free when the order subtotal exceeds the threshold.",
                                                  [],
                                                  "shippingTypeFreeHelp"
                                              )
                                            : __(
                                                  "COM_NXPEASYCART_SHIPPING_TYPE_FLAT_HELP",
                                                  "A fixed shipping cost regardless of order total.",
                                                  [],
                                                  "shippingTypeFlatHelp"
                                              )
                                    }}
                                </p>
                            </div>

                            <div class="nxp-ec-form-field">
                                <label class="nxp-ec-form-label" for="shipping-price">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_SHIPPING_PRICE",
                                            "Price",
                                            [],
                                            "shippingPrice"
                                        )
                                    }}
                                </label>
                                <div class="nxp-ec-input-group">
                                    <span class="nxp-ec-input-group__prefix">{{ baseCurrency }}</span>
                                    <input
                                        id="shipping-price"
                                        class="nxp-ec-form-input"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        v-model.number="draft.price"
                                        required
                                    />
                                </div>
                            </div>

                            <div
                                class="nxp-ec-form-field"
                                v-if="draft.type === 'free_over'"
                            >
                                <label class="nxp-ec-form-label" for="shipping-threshold">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_SHIPPING_THRESHOLD",
                                            "Threshold",
                                            [],
                                            "shippingThreshold"
                                        )
                                    }}
                                </label>
                                <div class="nxp-ec-input-group">
                                    <span class="nxp-ec-input-group__prefix">{{ baseCurrency }}</span>
                                    <input
                                        id="shipping-threshold"
                                        class="nxp-ec-form-input"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        v-model.number="draft.threshold"
                                        required
                                    />
                                </div>
                                <p class="nxp-ec-form-help">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_SHIPPING_THRESHOLD_HELP",
                                            "Orders above this amount qualify for free shipping.",
                                            [],
                                            "shippingThresholdHelp"
                                        )
                                    }}
                                </p>
                            </div>

                            <div class="nxp-ec-form-field">
                                <label class="nxp-ec-form-label" for="shipping-regions">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_SHIPPING_REGIONS",
                                            "Regions",
                                            [],
                                            "shippingRegions"
                                        )
                                    }}
                                </label>
                                <input
                                    id="shipping-regions"
                                    class="nxp-ec-form-input"
                                    type="text"
                                    v-model.trim="draft.regions"
                                    :placeholder="
                                        __(
                                            'COM_NXPEASYCART_SHIPPING_REGIONS_PLACEHOLDER',
                                            'e.g. US,GB,FR',
                                            [],
                                            'shippingRegionsPlaceholder'
                                        )
                                    "
                                />
                                <p class="nxp-ec-form-help">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_SHIPPING_REGIONS_HELP",
                                            "Leave empty to apply to all regions. Use two-letter ISO 3166-1 alpha-2 country codes.",
                                            [],
                                            "shippingRegionsHelp"
                                        )
                                    }}
                                </p>
                            </div>

                            <div class="nxp-ec-form-field nxp-ec-form-field--inline">
                                <label class="nxp-ec-form-label" for="shipping-active">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_SHIPPING_ACTIVE",
                                            "Active",
                                            [],
                                            "shippingActive"
                                        )
                                    }}
                                </label>
                                <input
                                    id="shipping-active"
                                    class="nxp-ec-form-checkbox"
                                    type="checkbox"
                                    v-model="draft.active"
                                />
                                <p class="nxp-ec-form-help">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_SHIPPING_ACTIVE_HELP",
                                            "Only active shipping rules are available at checkout.",
                                            [],
                                            "shippingActiveHelp"
                                        )
                                    }}
                                </p>
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
                                            "COM_NXPEASYCART_SHIPPING_FORM_CANCEL",
                                            "Cancel",
                                            [],
                                            "shippingFormCancel"
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
                                                  "COM_NXPEASYCART_SHIPPING_FORM_SAVE",
                                                  "Save shipping rule",
                                                  [],
                                                  "shippingFormSave"
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
import { reactive, ref, watch, computed } from "vue";

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

const emit = defineEmits(["refresh", "save", "delete"]);

const __ = props.translate;

const baseCurrency = computed(() => {
    const value = props.baseCurrency;
    return typeof value === "string" && value.trim() !== ""
        ? value.trim().toUpperCase()
        : "USD";
});

const formOpen = ref(false);

const draft = reactive({
    id: null,
    name: "",
    type: "flat",
    price: 0,
    threshold: 0,
    regions: "",
    active: true,
});

const refresh = () => emit("refresh");

const startCreate = () => {
    reset();
    formOpen.value = true;
};

const startEdit = (rule) => {
    Object.assign(draft, {
        id: rule.id,
        name: rule.name,
        type: rule.type,
        price: rule.price ?? (rule.price_cents ?? 0) / 100,
        threshold:
            rule.threshold !== null && rule.threshold !== undefined
                ? rule.threshold
                : (rule.threshold_cents ?? 0) / 100,
        regions:
            rule.regions && rule.regions.length ? rule.regions.join(", ") : "",
        active: rule.active,
    });
    formOpen.value = true;
};

const cancelEdit = () => {
    formOpen.value = false;
    reset();
};

const reset = () => {
    Object.assign(draft, {
        id: null,
        name: "",
        type: "flat",
        price: 0,
        threshold: 0,
        regions: "",
        active: true,
    });
};

const emitSave = () => {
    const payload = {
        id: draft.id || undefined,
        name: draft.name,
        type: draft.type,
        price: draft.price,
        threshold:
            draft.type === "free_over" ? draft.threshold : 0,
        regions: draft.regions,
        active: draft.active,
    };

    emit("save", payload);
};

const confirmDelete = (rule) => {
    const message = __(
        "COM_NXPEASYCART_SHIPPING_DELETE_CONFIRM",
        "Delete this shipping rule?",
        [],
        "shippingDeleteConfirm"
    );

    if (window.confirm(message)) {
        emit("delete", [rule.id]);
        if (draft.id === rule.id) {
            cancelEdit();
        }
    }
};

watch(
    () => props.state.saving,
    (saving, wasSaving) => {
        if (wasSaving && !saving && !props.state.error) {
            cancelEdit();
        }
    }
);

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

const typeLabel = (type) => {
    return type === "free_over"
        ? __(
              "COM_NXPEASYCART_SHIPPING_TYPE_FREE",
              "Free over threshold",
              [],
              "shippingTypeFree"
          )
        : __(
              "COM_NXPEASYCART_SHIPPING_TYPE_FLAT",
              "Flat rate",
              [],
              "shippingTypeFlat"
          );
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
</style>
