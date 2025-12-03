<template>
    <section class="nxp-ec-admin-panel nxp-ec-admin-panel--tax">
        <header class="nxp-ec-admin-panel__header">
            <div>
                <h2 class="nxp-ec-admin-panel__title">
                    {{
                        __(
                            "COM_NXPEASYCART_MENU_TAX",
                            "Tax Rates",
                            [],
                            "taxPanelTitle"
                        )
                    }}
                </h2>
                <p class="nxp-ec-admin-panel__lead">
                    {{
                        __(
                            "COM_NXPEASYCART_TAX_LEAD",
                            "Configure tax rates by country and region.",
                            [],
                            "taxPanelLead"
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
                        'COM_NXPEASYCART_TAX_REFRESH',
                        'Refresh',
                        [],
                        'taxRefresh'
                    )"
                    :aria-label="__(
                        'COM_NXPEASYCART_TAX_REFRESH',
                        'Refresh',
                        [],
                        'taxRefresh'
                    )"
                >
                    <i class="fa-solid fa-rotate"></i>
                    <span class="nxp-ec-sr-only">
                        {{
                            __(
                                "COM_NXPEASYCART_TAX_REFRESH",
                                "Refresh",
                                [],
                                "taxRefresh"
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
                            "COM_NXPEASYCART_TAX_ADD",
                            "Add tax rate",
                            [],
                            "taxAdd"
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
                    "COM_NXPEASYCART_TAX_LOADING",
                    "Loading tax rates…",
                    [],
                    "taxLoading"
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
                                        "COM_NXPEASYCART_TAX_COUNTRY",
                                        "Country",
                                        [],
                                        "taxCountry"
                                    )
                                }}
                            </th>
                            <th scope="col">
                                {{
                                    __(
                                        "COM_NXPEASYCART_TAX_REGION",
                                        "Region",
                                        [],
                                        "taxRegion"
                                    )
                                }}
                            </th>
                            <th scope="col">
                                {{
                                    __(
                                        "COM_NXPEASYCART_TAX_RATE",
                                        "Rate",
                                        [],
                                        "taxRate"
                                    )
                                }}
                            </th>
                            <th scope="col">
                                {{
                                    __(
                                        "COM_NXPEASYCART_TAX_INCLUSIVE",
                                        "Inclusive",
                                        [],
                                        "taxInclusive"
                                    )
                                }}
                            </th>
                            <th scope="col">
                                {{
                                    __(
                                        "COM_NXPEASYCART_TAX_PRIORITY",
                                        "Priority",
                                        [],
                                        "taxPriority"
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
                            <td colspan="6">
                                {{
                                    __(
                                        "COM_NXPEASYCART_TAX_EMPTY",
                                        "No tax rates defined.",
                                        [],
                                        "taxEmpty"
                                    )
                                }}
                            </td>
                        </tr>
                        <tr
                            v-for="rate in state.items"
                            :key="rate.id"
                            :class="{
                                'is-active': draft.id === rate.id,
                            }"
                        >
                            <th scope="row" class="nxp-ec-admin-table__primary">{{ rate.country }}</th>
                            <td :data-label="__('COM_NXPEASYCART_TAX_REGION', 'Region')">{{ rate.region || "—" }}</td>
                            <td :data-label="__('COM_NXPEASYCART_TAX_RATE', 'Rate')">{{ (rate.rate ?? 0).toFixed(2) }}%</td>
                            <td :data-label="__('COM_NXPEASYCART_TAX_INCLUSIVE', 'Inclusive')">
                                <span
                                    class="nxp-ec-badge"
                                    :class="{ 'is-active': rate.inclusive }"
                                >
                                    <i
                                        :class="
                                            rate.inclusive
                                                ? 'fa-solid fa-circle-check'
                                                : 'fa-regular fa-circle'
                                        "
                                        aria-hidden="true"
                                    ></i>
                                    {{
                                        rate.inclusive
                                            ? __("JYES", "Yes")
                                            : __("JNO", "No")
                                    }}
                                </span>
                            </td>
                            <td :data-label="__('COM_NXPEASYCART_TAX_PRIORITY', 'Priority')">{{ rate.priority }}</td>
                            <td class="nxp-ec-admin-table__actions">
                                <button
                                    class="nxp-ec-btn nxp-ec-btn--link nxp-ec-btn--icon"
                                    type="button"
                                    @click="startEdit(rate)"
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
                                    @click="confirmDelete(rate)"
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
                            <h3>
                                {{
                                    draft.id
                                        ? __("JEDIT", "Edit")
                                        : __(
                                              "COM_NXPEASYCART_TAX_ADD",
                                              "Add tax rate",
                                              [],
                                              "taxAdd"
                                          )
                                }}
                            </h3>
                            <button
                                class="nxp-ec-link-button nxp-ec-btn--icon"
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
                                <label class="nxp-ec-form-label" for="tax-country">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_TAX_COUNTRY",
                                            "Country",
                                            [],
                                            "taxCountry"
                                        )
                                    }}
                                </label>
                                <input
                                    id="tax-country"
                                    class="nxp-ec-form-input"
                                    type="text"
                                    v-model.trim="draft.country"
                                    maxlength="2"
                                    required
                                    :placeholder="__('COM_NXPEASYCART_TAX_COUNTRY_PLACEHOLDER', 'e.g. US', [], 'taxCountryPlaceholder')"
                                />
                                <p class="nxp-ec-form-help">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_TAX_COUNTRY_HELP",
                                            "Use ISO 3166-1 alpha-2 country codes (e.g., US, GB, DE).",
                                            [],
                                            "taxCountryHelp"
                                        )
                                    }}
                                </p>
                            </div>

                            <div class="nxp-ec-form-field">
                                <label class="nxp-ec-form-label" for="tax-region">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_TAX_REGION",
                                            "Region",
                                            [],
                                            "taxRegion"
                                        )
                                    }}
                                </label>
                                <input
                                    id="tax-region"
                                    class="nxp-ec-form-input"
                                    type="text"
                                    v-model.trim="draft.region"
                                    :placeholder="__('COM_NXPEASYCART_TAX_REGION_PLACEHOLDER', 'State/province code (optional)', [], 'taxRegionPlaceholder')"
                                />
                                <p class="nxp-ec-form-help">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_TAX_REGION_HELP",
                                            "Leave empty for country-wide tax rates.",
                                            [],
                                            "taxRegionHelp"
                                        )
                                    }}
                                </p>
                            </div>

                            <div class="nxp-ec-form-field">
                                <label class="nxp-ec-form-label" for="tax-rate">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_TAX_RATE",
                                            "Rate",
                                            [],
                                            "taxRate"
                                        )
                                    }}
                                </label>
                                <div class="nxp-ec-input-group">
                                    <input
                                        id="tax-rate"
                                        class="nxp-ec-form-input"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        v-model.number="draft.rate"
                                        required
                                    />
                                    <span class="nxp-ec-input-group__suffix">%</span>
                                </div>
                            </div>

                            <div class="nxp-ec-form-field nxp-ec-form-field--inline">
                                <label class="nxp-ec-form-label" for="tax-inclusive">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_TAX_INCLUSIVE",
                                            "Inclusive",
                                            [],
                                            "taxInclusive"
                                        )
                                    }}
                                </label>
                                <input
                                    id="tax-inclusive"
                                    class="nxp-ec-form-checkbox"
                                    type="checkbox"
                                    v-model="draft.inclusive"
                                />
                                <p class="nxp-ec-form-help">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_TAX_INCLUSIVE_HELP",
                                            "When enabled, prices are displayed with tax already included.",
                                            [],
                                            "taxInclusiveHelp"
                                        )
                                    }}
                                </p>
                            </div>

                            <div class="nxp-ec-form-field">
                                <label class="nxp-ec-form-label" for="tax-priority">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_TAX_PRIORITY",
                                            "Priority",
                                            [],
                                            "taxPriority"
                                        )
                                    }}
                                </label>
                                <input
                                    id="tax-priority"
                                    class="nxp-ec-form-input"
                                    type="number"
                                    step="1"
                                    v-model.number="draft.priority"
                                />
                                <p class="nxp-ec-form-help">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_TAX_PRIORITY_HELP",
                                            "Higher priority rates are applied first when multiple rates match.",
                                            [],
                                            "taxPriorityHelp"
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
                                            "COM_NXPEASYCART_TAX_FORM_CANCEL",
                                            "Cancel",
                                            [],
                                            "taxFormCancel"
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
                                                  "COM_NXPEASYCART_TAX_FORM_SAVE",
                                                  "Save tax rate",
                                                  [],
                                                  "taxFormSave"
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
import { reactive, ref, watch } from "vue";

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

const emit = defineEmits(["refresh", "save", "delete"]);

const __ = props.translate;

const formOpen = ref(false);

const draft = reactive({
    id: null,
    country: "",
    region: "",
    rate: 0,
    inclusive: false,
    priority: 0,
});

const refresh = () => emit("refresh");

const startCreate = () => {
    reset();
    formOpen.value = true;
};

const startEdit = (rate) => {
    Object.assign(draft, {
        id: rate.id,
        country: rate.country,
        region: rate.region,
        rate: rate.rate,
        inclusive: rate.inclusive,
        priority: rate.priority,
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
        country: "",
        region: "",
        rate: 0,
        inclusive: false,
        priority: 0,
    });
};

const emitSave = () => {
    emit("save", {
        id: draft.id || undefined,
        country: draft.country,
        region: draft.region,
        rate: draft.rate,
        inclusive: draft.inclusive,
        priority: draft.priority,
    });
};

const confirmDelete = (rate) => {
    const message = __(
        "COM_NXPEASYCART_TAX_DELETE_CONFIRM",
        "Delete this tax rate?",
        [],
        "taxDeleteConfirm"
    );

    if (window.confirm(message)) {
        emit("delete", [rate.id]);
        if (draft.id === rate.id) {
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
</script>
