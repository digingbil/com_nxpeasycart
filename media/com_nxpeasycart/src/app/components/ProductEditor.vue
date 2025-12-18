<template>
    <div v-if="open" class="nxp-ec-modal" role="dialog" aria-modal="true" aria-labelledby="product-modal-title" @keydown.esc="cancel">
        <div class="nxp-ec-modal__backdrop"></div>
        <div class="nxp-ec-modal__dialog">
            <header class="nxp-ec-modal__header">
                <h2 id="product-modal-title" class="nxp-ec-modal__title">
                    {{
                        mode === "edit"
                            ? __("COM_NXPEASYCART_PRODUCTS_EDIT", "Edit product")
                            : __("COM_NXPEASYCART_PRODUCTS_ADD", "Add product")
                    }}
                </h2>

                <div class="nxp-ec-modal__header-actions">
                    <button
                        type="button"
                        class="nxp-ec-btn nxp-ec-btn--icon"
                        @click="cancel"
                        :disabled="saving"
                        :title="__('JCANCEL', 'Cancel')"
                        :aria-label="__('JCANCEL', 'Cancel')"
                    >
                        <i class="fa-solid fa-arrow-rotate-left" aria-hidden="true"></i>
                        <span class="nxp-ec-sr-only">{{ __("JCANCEL", "Cancel") }}</span>
                    </button>
                    <button
                        type="submit"
                        class="nxp-ec-btn nxp-ec-btn--icon nxp-ec-btn--primary"
                        :disabled="saving"
                        :form="formId"
                        :title="saving ? __('JPROCESSING_REQUEST', 'Saving…') : __('JSAVE', 'Save')"
                        :aria-label="saving ? __('JPROCESSING_REQUEST', 'Saving…') : __('JSAVE', 'Save')"
                    >
                        <i class="fa-solid fa-floppy-disk" :class="{ 'fa-spin': saving }" aria-hidden="true"></i>
                        <span class="nxp-ec-sr-only">
                            {{ saving ? __("JPROCESSING_REQUEST", "Saving…") : __("JSAVE", "Save") }}
                        </span>
                    </button>
                    <button
                        type="button"
                        class="nxp-ec-link-button nxp-ec-btn--icon nxp-ec-modal__close-btn"
                        @click="cancel"
                        :title="__('JCLOSE', 'Close')"
                        :aria-label="__('JCLOSE', 'Close')"
                    >
                        <i class="fa-solid fa-circle-xmark"></i>
                        <span class="nxp-ec-sr-only">{{ __('JCLOSE', 'Close') }}</span>
                    </button>
                </div>
            </header>

            <form ref="formRef" :id="formId" class="nxp-ec-form" novalidate @submit.prevent="submit">
                <div v-if="errors.length" class="nxp-ec-admin-alert nxp-ec-admin-alert--error">
                    <p v-for="(error, index) in errors" :key="index">{{ error }}</p>
                </div>

                <nav class="nxp-ec-editor-tabs" role="tablist">
                    <button
                        type="button"
                        class="nxp-ec-editor-tab"
                        :class="{ 'is-active': activeTab === 'general' }"
                        @click="activeTab = 'general'"
                    >
                        {{ __("COM_NXPEASYCART_SETTINGS_TAB_GENERAL", "General") }}
                    </button>
                    <button
                        type="button"
                        class="nxp-ec-editor-tab"
                        :class="{ 'is-active': activeTab === 'variants' }"
                        @click="activeTab = 'variants'"
                    >
                        {{ __("COM_NXPEASYCART_FIELD_PRODUCT_VARIANTS", "Variants") }}
                    </button>
                    <button
                        type="button"
                        class="nxp-ec-editor-tab"
                        :class="{ 'is-active': activeTab === 'images' }"
                        @click="activeTab = 'images'"
                    >
                        {{ __("COM_NXPEASYCART_FIELD_PRODUCT_IMAGES", "Images") }}
                    </button>
                    <button
                        type="button"
                        class="nxp-ec-editor-tab"
                        :class="{ 'is-active': activeTab === 'categories' }"
                        @click="activeTab = 'categories'"
                    >
                        {{ __("COM_NXPEASYCART_PRODUCT_CATEGORIES_LABEL", "Categories") }}
                    </button>
                    <button
                        v-if="isDigitalProduct"
                        type="button"
                        class="nxp-ec-editor-tab"
                        :class="{ 'is-active': activeTab === 'digital' }"
                        @click="activeTab = 'digital'"
                    >
                        {{ __("COM_NXPEASYCART_DIGITAL_FILES", "Digital") }}
                    </button>
                </nav>

                <!-- General Tab -->
                <GeneralTab
                    v-show="activeTab === 'general'"
                    data-tab="general"
                    :form="form"
                    :translate="__"
                    @slug-input="onSlugInput"
                />

                <!-- Images Tab -->
                <ImagesTab
                    v-show="activeTab === 'images'"
                    data-tab="images"
                    :images="form.images"
                    :translate="__"
                    @add-image="images.addImage"
                    @remove-image="images.removeImage"
                    @move-image="images.moveImage"
                    @update-image="images.updateImage"
                    @open-media-modal="mediaPicker.openMediaModal"
                />

                <!-- Categories Tab -->
                <CategoriesTab
                    v-show="activeTab === 'categories'"
                    data-tab="categories"
                    :categories="form.categories"
                    :category-options="categories.categoryOptionsList.value"
                    :new-category-draft="categories.newCategoryDraft.value"
                    :translate="__"
                    @add-category="categories.addCategory"
                    @remove-category="categories.removeCategory"
                    @update-selection="categories.handleCategorySelectionUpdate"
                    @update:newCategoryDraft="categories.newCategoryDraft.value = $event"
                />

                <!-- Variants Tab -->
                <VariantsTab
                    v-show="activeTab === 'variants'"
                    data-tab="variants"
                    :variants="form.variants"
                    :base-currency="baseCurrency"
                    :product-type="form.product_type"
                    :has-media-modal="mediaPicker.hasMediaModal.value"
                    :resolve-image-url="mediaPicker.resolveImageUrl"
                    :translate="__"
                    @add-variant="variants.addVariant"
                    @remove-variant="variants.removeVariant"
                    @duplicate-variant="variants.duplicateVariant"
                    @add-option="variants.addVariantOption"
                    @remove-option="variants.removeVariantOption"
                    @format-price="variants.formatVariantPrice"
                    @format-sale-price="variants.formatVariantSalePrice"
                    @open-variant-media-modal="mediaPicker.openVariantMediaModal"
                    @remove-variant-image="variants.removeVariantImage"
                />

                <!-- Digital Files Tab -->
                <DigitalFilesTab
                    v-show="activeTab === 'digital'"
                    data-tab="digital"
                    ref="digitalFilesTabRef"
                    :product-id="props.product?.id"
                    :files="digitalFiles.digitalState.files"
                    :variant-options="variants.variantOptions.value"
                    :version="digitalFiles.digitalState.version"
                    :variant-id="digitalFiles.digitalState.variantId"
                    :loading="digitalFiles.digitalState.loading"
                    :uploading="digitalFiles.digitalState.uploading"
                    :deleting-id="digitalFiles.digitalState.deletingId"
                    :error="digitalFiles.digitalState.error"
                    :translate="__"
                    @upload="() => digitalFiles.uploadDigitalFile(props.product?.id)"
                    @delete="digitalFiles.deleteDigitalFile"
                    @file-change="digitalFiles.handleFileChange"
                    @update:version="digitalFiles.digitalState.version = $event"
                    @update:variantId="digitalFiles.digitalState.variantId = $event"
                />

                <footer class="nxp-ec-modal__actions">
                    <button type="button" class="nxp-ec-btn" @click="cancel" :disabled="saving">
                        {{ __("JCANCEL", "Cancel") }}
                    </button>
                    <button type="submit" class="nxp-ec-btn nxp-ec-btn--primary" :disabled="saving">
                        {{ saving ? __("JPROCESSING_REQUEST", "Saving…") : __("JSAVE", "Save") }}
                    </button>
                </footer>
            </form>
        </div>
    </div>
</template>

<script setup>
/**
 * ProductEditor - Product management modal coordinator.
 *
 * Orchestrates the product editing modal with tabs for:
 * - General information (title, slug, descriptions, status)
 * - Product images
 * - Category assignment
 * - Variant management (SKU, pricing, stock, options)
 * - Digital file uploads (for digital products)
 *
 * @since 0.3.0
 * @refactored 0.3.2 - Extracted tab components and composables
 */
import { computed, reactive, ref, watch, toRef } from "vue";
import { createApiClient } from "../../api.js";

// Tab components
import GeneralTab from "./product-editor/GeneralTab.vue";
import ImagesTab from "./product-editor/ImagesTab.vue";
import CategoriesTab from "./product-editor/CategoriesTab.vue";
import VariantsTab from "./product-editor/VariantsTab.vue";
import DigitalFilesTab from "./product-editor/DigitalFilesTab.vue";

// Composables
import {
    useCategories,
    useVariants,
    useDigitalFiles,
    useMediaPicker,
    useProductImages,
} from "./product-editor/composables/index.js";

const props = defineProps({
    open: { type: Boolean, default: false },
    product: { type: Object, default: () => null },
    saving: { type: Boolean, default: false },
    baseCurrency: { type: String, default: "USD" },
    translate: { type: Function, required: true },
    errors: { type: Array, default: () => [] },
    categoryOptions: { type: Array, default: () => [] },
    mediaModalUrl: { type: String, default: "" },
    digitalEndpoints: { type: Object, default: () => ({}) },
    csrfToken: { type: String, default: "" },
});

const emit = defineEmits(["submit", "cancel"]);

const __ = props.translate;
const formId = "product-editor-form";
const formRef = ref(null);
const activeTab = ref("general");

// ─────────────────────────────────────────────────────────────────────────────
// Form state
// ─────────────────────────────────────────────────────────────────────────────

const form = reactive({
    title: "",
    slug: "",
    short_desc: "",
    long_desc: "",
    status: 1,
    featured: false,
    images: [],
    categories: [],
    variants: [],
    product_type: "physical",
    digital_files: [],
});

const slugEdited = ref(false);
const baseCurrency = computed(() => (props.baseCurrency || "USD").toUpperCase());
const productType = computed(() => (form.product_type || "physical").toString().toLowerCase());
const isDigitalProduct = computed(() => productType.value === "digital");
const mode = computed(() => (props.product && props.product.id ? "edit" : "create"));

// ─────────────────────────────────────────────────────────────────────────────
// Composables
// ─────────────────────────────────────────────────────────────────────────────

const categories = useCategories(
    toRef(form, "categories"),
    toRef(props, "categoryOptions")
);

const variants = useVariants(
    toRef(form, "variants"),
    baseCurrency,
    isDigitalProduct,
    __
);

const apiClient = createApiClient({ token: props.csrfToken || "" });
const digitalEndpoints = computed(() => props.digitalEndpoints || {});

const digitalFiles = useDigitalFiles(
    toRef(form, "digital_files"),
    apiClient,
    digitalEndpoints,
    __
);

const mediaPicker = useMediaPicker(
    toRef(form, "images"),
    toRef(form, "variants"),
    toRef(props, "mediaModalUrl"),
    __
);

const images = useProductImages(toRef(form, "images"));

// Setup media picker lifecycle hooks
mediaPicker.setupLifecycle();

// ─────────────────────────────────────────────────────────────────────────────
// Slug handling
// ─────────────────────────────────────────────────────────────────────────────

const slugify = (value) => {
    if (!value) return "";
    return value
        .toString()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "")
        .substring(0, 190);
};

const onSlugInput = () => {
    slugEdited.value = true;
    form.slug = slugify(form.slug);
};

// ─────────────────────────────────────────────────────────────────────────────
// Status normalization
// ─────────────────────────────────────────────────────────────────────────────

const normaliseStatus = (value) => {
    const numeric = Number(value);
    if (Number.isFinite(numeric)) {
        if (numeric === -1) return -1;
        if (numeric === 0) return 0;
        return 1;
    }
    if (typeof value === "string") {
        const trimmed = value.trim().toLowerCase();
        if (trimmed === "out_of_stock" || trimmed === "out-of-stock" || trimmed === "-1") return -1;
        if (trimmed === "inactive" || trimmed === "0") return 0;
    }
    return 1;
};

// ─────────────────────────────────────────────────────────────────────────────
// Product application
// ─────────────────────────────────────────────────────────────────────────────

const applyProduct = (product) => {
    const source = product ? JSON.parse(JSON.stringify(product)) : {};

    form.title = source.title ?? "";
    form.slug = source.slug ?? "";
    form.short_desc = source.short_desc ?? "";
    form.long_desc = source.long_desc ?? "";
    form.status = normaliseStatus(source.status !== undefined ? source.status : source.active);
    form.featured = source.featured !== undefined ? Boolean(source.featured) : false;
    form.product_type =
        typeof source.product_type === "string" && source.product_type !== ""
            ? source.product_type.toLowerCase()
            : "physical";

    // Reset images
    const productImages = Array.isArray(source.images)
        ? source.images.map((img) => String(img ?? "").trim()).filter(Boolean)
        : [];
    images.resetImages(productImages);

    // Reset categories
    const productCategories = Array.isArray(source.categories) ? source.categories : [];
    categories.resetCategories(productCategories);

    // Reset variants
    variants.resetVariants(variants.normaliseVariants(source.variants));

    // Reset digital files
    digitalFiles.resetDigitalFiles(source.digital_files);

    categories.newCategoryDraft.value = "";
    slugEdited.value = Boolean(form.slug);
};

// ─────────────────────────────────────────────────────────────────────────────
// Watchers
// ─────────────────────────────────────────────────────────────────────────────

watch(
    () => props.product,
    (product) => {
        applyProduct(product);
        activeTab.value = "general";
    },
    { immediate: true }
);

watch(
    () => form.title,
    (title) => {
        if (!slugEdited.value) {
            form.slug = slugify(title);
        }
    }
);

watch(baseCurrency, (currency) => {
    form.variants.forEach((variant) => {
        variant.currency = currency;
    });
});

watch(productType, (newType) => {
    variants.applyVariantDigitalDefaults();
    if (newType !== "digital" && activeTab.value === "digital") {
        activeTab.value = "general";
    }
});

watch(activeTab, (tab) => {
    if (tab === "digital") {
        digitalFiles.loadDigitalFiles(props.product?.id);
    }
});

watch(
    () => props.saving,
    (saving, wasSaving) => {
        if (wasSaving && !saving && props.errors.length === 0) {
            if (mode.value === "create") {
                applyProduct(null);
                categories.newCategoryDraft.value = "";
                slugEdited.value = false;
            }
        }
    }
);

// ─────────────────────────────────────────────────────────────────────────────
// Form validation
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Validate form and switch to tab containing first invalid field.
 * Returns true if form is valid, false otherwise.
 */
const validateForm = () => {
    if (!formRef.value) {
        return true;
    }

    const form = formRef.value;

    // Check if the form is valid
    if (form.checkValidity()) {
        return true;
    }

    // Find the first invalid element
    const invalidElement = form.querySelector(":invalid");

    if (!invalidElement) {
        return true;
    }

    // Find which tab contains the invalid element
    const tabPanel = invalidElement.closest("[data-tab]");

    if (tabPanel) {
        const targetTab = tabPanel.dataset.tab;

        if (targetTab && targetTab !== activeTab.value) {
            activeTab.value = targetTab;

            // Wait for Vue to update the DOM, then focus and report
            setTimeout(() => {
                invalidElement.focus();
                invalidElement.reportValidity();
            }, 50);

            return false;
        }
    }

    // Already on the correct tab, just focus and report
    invalidElement.focus();
    invalidElement.reportValidity();

    return false;
};

// ─────────────────────────────────────────────────────────────────────────────
// Submit / Cancel
// ─────────────────────────────────────────────────────────────────────────────

const submit = () => {
    if (!validateForm()) {
        return;
    }

    const status = normaliseStatus(form.status);

    const payload = {
        title: form.title.trim(),
        slug: form.slug.trim(),
        short_desc: form.short_desc ?? "",
        long_desc: form.long_desc ?? "",
        status,
        active: status,
        featured: Boolean(form.featured),
        images: images.buildPayloadImages(),
        categories: categories.buildPayloadCategories(),
        variants: variants.buildPayloadVariants(),
        product_type: productType.value === "digital" ? "digital" : "physical",
    };

    if (import.meta?.env?.DEV) {
        console.debug("[ProductEditor] submit payload", payload);
    }

    emit("submit", payload);
};

const cancel = () => {
    emit("cancel");
};
</script>

<style scoped>
.nxp-ec-editor-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.nxp-ec-editor-tab {
    border: 1px solid var(--nxp-ec-border, #d0d5dd);
    background: var(--nxp-ec-surface, #fff);
    color: var(--nxp-ec-text, #111827);
    border-radius: 999px;
    padding: 0.4rem 0.9rem;
    cursor: pointer;
    transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
}

.nxp-ec-editor-tab.is-active {
    background: var(--nxp-ec-primary-bg-solid, #4f46e5);
    color: #fff;
    border-color: var(--nxp-ec-primary-bg-solid, #4f46e5);
}

.nxp-ec-modal__header {
    position: sticky;
    top: 0;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    background: var(--nxp-ec-surface);
    color: var(--nxp-ec-text);
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--nxp-ec-border);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.nxp-ec-modal__header-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.nxp-ec-btn--icon {
    width: 2.5rem;
    height: 2.5rem;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    line-height: 1;
}

.nxp-ec-btn--icon i {
    font-size: 1rem;
}

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

/* Deep styles for child tab components */
:deep(.nxp-ec-editor-panel) {
    display: grid;
    gap: 1rem;
}

:deep(.nxp-ec-editor-panel > .nxp-ec-admin-alert) {
    margin: 0;
    padding: 0.625rem 0.875rem;
    font-size: 0.875rem;
}

:deep(.nxp-ec-editor-panel--digital) {
    display: grid;
    gap: 1rem;
}

:deep(.nxp-ec-digital-grid) {
    display: grid;
    gap: 1rem;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
}

:deep(.nxp-ec-digital-card) {
    border: 1px solid var(--nxp-ec-border, #e4e7ec);
    border-radius: 0.75rem;
    padding: 1rem;
    background: var(--nxp-ec-surface, #fff);
    display: grid;
    gap: 0.75rem;
    min-width: 0;
}

:deep(.nxp-ec-digital-card .nxp-ec-form-field) {
    min-width: 0;
}

:deep(.nxp-ec-digital-card .nxp-ec-form-input),
:deep(.nxp-ec-digital-card .nxp-ec-form-select) {
    width: 100%;
    box-sizing: border-box;
}

:deep(.nxp-ec-digital-card__header) {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
}

:deep(.nxp-ec-digital-card__header h4) {
    margin: 0;
    flex: 1;
}

:deep(.nxp-ec-digital-table) {
    display: grid;
    gap: 0.75rem;
    max-width: 100%;
    overflow: hidden;
}

:deep(.nxp-ec-digital-row) {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    border: 1px solid var(--nxp-ec-border, #e4e7ec);
    border-radius: 0.5rem;
    background: var(--nxp-ec-surface-alt, #f9fafb);
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

:deep(.nxp-ec-digital-meta) {
    flex: 1;
    min-width: 0;
    overflow: hidden;
}

:deep(.nxp-ec-digital-meta strong) {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

:deep(.nxp-ec-digital-actions) {
    display: flex;
    align-items: center;
}

:deep(.nxp-ec-image-list) {
    display: grid;
    gap: 0.75rem;
}

:deep(.nxp-ec-image-row) {
    display: grid;
    gap: 0.5rem;
}

@media (min-width: 640px) {
    :deep(.nxp-ec-image-row) {
        grid-template-columns: 1fr auto;
        align-items: center;
    }
}

:deep(.nxp-ec-image-row__actions) {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    justify-content: flex-end;
}

:deep(#product-category-select) {
    font-family: ui-monospace, 'SF Mono', 'Cascadia Code', 'Roboto Mono', 'Ubuntu Mono', 'Menlo', 'Consolas', 'Monaco', 'Liberation Mono', monospace;
    white-space: pre;
    min-height: 200px;
}

/* Tablet breakpoint */
@media (max-width: 768px) {
    .nxp-ec-modal__header {
        padding: 0.75rem 1rem;
    }

    .nxp-ec-btn--icon {
        width: 2.75rem;
        height: 2.75rem;
    }
}

/* Mobile breakpoint */
@media (max-width: 480px) {
    .nxp-ec-modal__header {
        padding: 0.5rem 0.75rem;
    }

    .nxp-ec-modal__header-actions {
        flex-wrap: wrap;
    }

    .nxp-ec-btn--icon {
        width: 3rem;
        height: 3rem;
    }

    .nxp-ec-btn--icon i {
        font-size: 1.1rem;
    }

    :deep(#product-category-select) {
        min-height: 150px;
    }

    :deep(.nxp-ec-image-row__actions) {
        justify-content: stretch;
    }

    :deep(.nxp-ec-image-row__actions .nxp-ec-btn) {
        flex: 1;
    }
}
</style>
