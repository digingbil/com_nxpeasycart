<template>
    <div v-if="open" class="nxp-ec-modal" role="dialog" aria-modal="true" aria-labelledby="product-modal-title" @keydown.esc="cancel">
        <div class="nxp-ec-modal__backdrop"></div>
        <div class="nxp-ec-modal__dialog">
            <header class="nxp-ec-modal__header">
                <h2 id="product-modal-title" class="nxp-ec-modal__title">
                    {{
                        mode === "edit"
                            ? __(
                                  "COM_NXPEASYCART_PRODUCTS_EDIT",
                                  "Edit product"
                              )
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
                        <span class="nxp-ec-sr-only">
                            {{ __("JCANCEL", "Cancel") }}
                        </span>
                    </button>
                    <button
                        type="submit"
                        class="nxp-ec-btn nxp-ec-btn--icon nxp-ec-btn--primary"
                        :disabled="saving"
                        :form="formId"
                        :title="
                            saving
                                ? __('JPROCESSING_REQUEST', 'Saving…')
                                : __('JSAVE', 'Save')
                        "
                        :aria-label="
                            saving
                                ? __('JPROCESSING_REQUEST', 'Saving…')
                                : __('JSAVE', 'Save')
                        "
                    >
                        <i
                            class="fa-solid fa-floppy-disk"
                            :class="{ 'fa-spin': saving }"
                            aria-hidden="true"
                        ></i>
                        <span class="nxp-ec-sr-only">
                            {{
                                saving
                                    ? __("JPROCESSING_REQUEST", "Saving…")
                                    : __("JSAVE", "Save")
                            }}
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

            <form
                :id="formId"
                class="nxp-ec-form"
                @submit.prevent="submit"
            >
                <div
                    v-if="errors.length"
                    class="nxp-ec-admin-alert nxp-ec-admin-alert--error"
                >
                    <p v-for="(error, index) in errors" :key="index">
                        {{ error }}
                    </p>
                </div>

                <div class="nxp-ec-form-field">
                    <label class="nxp-ec-form-label" for="product-title">
                        {{ __("COM_NXPEASYCART_FIELD_PRODUCT_TITLE", "Title") }}
                    </label>
                    <input
                        id="product-title"
                        class="nxp-ec-form-input"
                        type="text"
                        v-model.trim="form.title"
                        required
                    />
                </div>

                <div class="nxp-ec-form-field">
                    <label class="nxp-ec-form-label" for="product-slug">
                        {{ __("COM_NXPEASYCART_FIELD_PRODUCT_SLUG", "Slug") }}
                    </label>
                    <input
                        id="product-slug"
                        class="nxp-ec-form-input"
                        type="text"
                        v-model.trim="form.slug"
                        @input="onSlugInput"
                        :placeholder="
                            __(
                                'COM_NXPEASYCART_FIELD_PRODUCT_SLUG_PLACEHOLDER',
                                'Auto-generated if left empty'
                            )
                        "
                    />
                </div>

                <div class="nxp-ec-form-field">
                    <label class="nxp-ec-form-label" for="product-short-desc">
                        {{
                            __(
                                "COM_NXPEASYCART_FIELD_PRODUCT_SHORT_DESC",
                                "Short description"
                            )
                        }}
                    </label>
                    <textarea
                        id="product-short-desc"
                        class="nxp-ec-form-textarea"
                        rows="3"
                        v-model="form.short_desc"
                    ></textarea>
                </div>

                <div class="nxp-ec-form-field">
                    <label class="nxp-ec-form-label" for="product-long-desc">
                        {{
                            __(
                                "COM_NXPEASYCART_FIELD_PRODUCT_LONG_DESC",
                                "Long description"
                            )
                        }}
                    </label>
                    <textarea
                        id="product-long-desc"
                        class="nxp-ec-form-textarea"
                        rows="5"
                        v-model="form.long_desc"
                    ></textarea>
                </div>

                <div class="nxp-ec-form-field">
                    <label class="nxp-ec-form-label" for="product-status">
                        {{
                            __(
                                "COM_NXPEASYCART_FIELD_PRODUCT_STATUS",
                                "Status"
                            )
                        }}
                    </label>
                    <select
                        id="product-status"
                        class="nxp-ec-form-select"
                        v-model.number="form.status"
                    >
                        <option :value="1">
                            {{
                                __(
                                    "COM_NXPEASYCART_FIELD_PRODUCT_STATUS_ACTIVE",
                                    "Active (visible & purchasable)"
                                )
                            }}
                        </option>
                        <option :value="-1">
                            {{
                                __(
                                    "COM_NXPEASYCART_FIELD_PRODUCT_STATUS_OUT_OF_STOCK",
                                    "Out of stock (visible, purchase disabled)"
                                )
                            }}
                        </option>
                        <option :value="0">
                            {{
                                __(
                                    "COM_NXPEASYCART_FIELD_PRODUCT_STATUS_INACTIVE",
                                    "Inactive (hidden)"
                                )
                            }}
                        </option>
                    </select>
                    <p class="nxp-ec-form-help">
                        {{
                            __(
                                "COM_NXPEASYCART_FIELD_PRODUCT_STATUS_DESC",
                                "Control storefront visibility and purchase availability."
                            )
                        }}
                    </p>
                </div>

                <div class="nxp-ec-form-field nxp-ec-form-field--inline">
                    <label class="nxp-ec-form-label" for="product-featured">
                        {{
                            __(
                                "COM_NXPEASYCART_FIELD_PRODUCT_FEATURED",
                                "Featured"
                            )
                        }}
                    </label>
                    <input
                        id="product-featured"
                        type="checkbox"
                        class="nxp-ec-form-checkbox"
                        v-model="form.featured"
                    />
                    <p class="nxp-ec-form-help">
                        {{
                            __(
                                "COM_NXPEASYCART_FIELD_PRODUCT_FEATURED_DESC",
                                "Surface this product on the shop landing page."
                            )
                        }}
                    </p>
                </div>

                <div class="nxp-ec-form-field">
                    <label class="nxp-ec-form-label" for="product-images">
                        {{
                            __("COM_NXPEASYCART_FIELD_PRODUCT_IMAGES", "Images")
                        }}
                    </label>
                    <div id="product-images" class="nxp-ec-image-list">
                        <div
                            v-for="(image, index) in form.images"
                            :key="`product-image-${index}`"
                            class="nxp-ec-image-row"
                        >
                            <input
                                :id="`product-image-${index}`"
                                class="nxp-ec-form-input"
                                type="text"
                                v-model.trim="form.images[index]"
                                :placeholder="
                                    __(
                                        'COM_NXPEASYCART_FIELD_PRODUCT_IMAGES_PLACEHOLDER',
                                        'https://example.com/image.jpg',
                                        [],
                                        'productImagesPlaceholder'
                                    )
                                "
                            />
                            <div class="nxp-ec-image-row__actions">
                                <button
                                    type="button"
                                    class="nxp-ec-btn"
                                    @click="openMediaModal(index)"
                                >
                                    <i class="fa-solid fa-photo-film" aria-hidden="true"></i>
                                    {{
                                        __(
                                            "COM_NXPEASYCART_FIELD_PRODUCT_IMAGES_SELECT",
                                            "Select from media",
                                            [],
                                            "productImagesSelect"
                                        )
                                    }}
                                </button>
                                <button
                                    type="button"
                                    class="nxp-ec-btn nxp-ec-btn--link"
                                    @click="moveImage(index, -1)"
                                    :disabled="index === 0"
                                >
                                    {{
                                        __(
                                            "COM_NXPEASYCART_FIELD_PRODUCT_IMAGES_MOVE_UP",
                                            "Up",
                                            [],
                                            "productImagesMoveUp"
                                        )
                                    }}
                                </button>
                                <button
                                    type="button"
                                    class="nxp-ec-btn nxp-ec-btn--link"
                                    @click="moveImage(index, 1)"
                                    :disabled="index === form.images.length - 1"
                                >
                                    {{
                                        __(
                                            "COM_NXPEASYCART_FIELD_PRODUCT_IMAGES_MOVE_DOWN",
                                            "Down",
                                            [],
                                            "productImagesMoveDown"
                                        )
                                    }}
                                </button>
                                <button
                                    type="button"
                                    class="nxp-ec-btn nxp-ec-btn--link nxp-ec-btn--danger"
                                    @click="removeImage(index)"
                                    :aria-label="
                                        __(
                                            'COM_NXPEASYCART_FIELD_PRODUCT_IMAGES_REMOVE',
                                            'Remove image',
                                            [],
                                            'productImagesRemove'
                                        )
                                    "
                                >
                                    {{ __("COM_NXPEASYCART_REMOVE", "Remove") }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="nxp-ec-btn" @click="addImage">
                        {{
                            __(
                                "COM_NXPEASYCART_FIELD_PRODUCT_IMAGES_ADD",
                                "Add image",
                                [],
                                "productImagesAdd"
                            )
                        }}
                    </button>
                    <p class="nxp-ec-form-help">
                        {{
                            __(
                                "COM_NXPEASYCART_FIELD_PRODUCT_IMAGES_HELP",
                                "These URLs are stored as-is; ensure they are publicly accessible."
                            )
                        }}
                    </p>
                </div>

                <div class="nxp-ec-form-field">
                    <label class="nxp-ec-form-label" for="product-category-select">
                        {{
                            __(
                                "COM_NXPEASYCART_PRODUCT_CATEGORIES_LABEL",
                                "Categories"
                            )
                        }}
                    </label>
                    <select
                        id="product-category-select"
                        class="nxp-ec-form-select"
                        multiple
                        v-model="selectedCategoryIds"
                    >
                        <option
                            v-for="option in categoryOptionsList"
                            :key="option.id"
                            :value="option.id"
                        >
                            {{ getCategoryDisplayName(option) }}
                        </option>
                    </select>
                    <div class="nxp-ec-chip-input nxp-ec-chip-input--selected">
                        <span
                            v-for="(category, index) in form.categories"
                            :key="`selected-category-${index}`"
                            class="nxp-ec-chip"
                        >
                            {{ category.title }}
                            <button
                                type="button"
                                class="nxp-ec-chip__remove"
                                @click="removeCategory(index)"
                                :aria-label="
                                    __(
                                        'COM_NXPEASYCART_REMOVE_CATEGORY',
                                        'Remove category'
                                    )
                                "
                            >
                                &times;
                            </button>
                        </span>
                    </div>
                    <div class="nxp-ec-chip-input nxp-ec-chip-input--new">
                        <input
                            id="product-category-input"
                            type="text"
                            class="nxp-ec-chip-input__field"
                            v-model="newCategoryDraft"
                            @keydown.enter.prevent="addCategory"
                            :placeholder="
                                __(
                                    'COM_NXPEASYCART_PRODUCT_CATEGORIES_ADD_PLACEHOLDER',
                                    'New category name',
                                    [],
                                    'productCategoriesAddPlaceholder'
                                )
                            "
                        />
                        <button
                            type="button"
                            class="nxp-ec-btn"
                            @click="addCategory"
                        >
                            {{
                                __(
                                    "COM_NXPEASYCART_PRODUCT_CATEGORIES_ADD",
                                    "Add category",
                                    [],
                                    "productCategoriesAdd"
                                )
                            }}
                        </button>
                    </div>
                    <p class="nxp-ec-form-help">
                        {{
                            __(
                                "COM_NXPEASYCART_PRODUCT_CATEGORIES_HELP",
                                "Select categories or add a new one.",
                                [],
                                "productCategoriesHelp"
                            )
                        }}
                    </p>
                </div>

                <section class="nxp-ec-variant-section">
                    <header class="nxp-ec-variant-section__header">
                        <h3 class="nxp-ec-variant-section__title">
                            {{
                                __(
                                    "COM_NXPEASYCART_FIELD_PRODUCT_VARIANTS",
                                    "Variants"
                                )
                            }}
                        </h3>
                        <button
                            type="button"
                            class="nxp-ec-btn"
                            @click="addVariant"
                        >
                            {{
                                __(
                                    "COM_NXPEASYCART_FIELD_PRODUCT_VARIANT_ADD",
                                    "Add variant"
                                )
                            }}
                        </button>
                    </header>

                    <p class="nxp-ec-form-help">
                        {{
                            __(
                                "COM_NXPEASYCART_FIELD_PRODUCT_VARIANTS_HELP",
                                "Each product needs at least one variant with a price."
                            )
                        }}
                        <template v-if="baseCurrency">
                            ({{ __("COM_NXPEASYCART_FIELD_VARIANT_CURRENCY_NOTE", "All prices in %s", [baseCurrency]) }})
                        </template>
                    </p>

                    <article
                        v-for="(variant, index) in form.variants"
                        :key="variantKey(variant, index)"
                        class="nxp-ec-variant-card"
                    >
                        <header class="nxp-ec-variant-card__header">
                            <h4 class="nxp-ec-variant-card__title">
                                {{
                                    __(
                                        "COM_NXPEASYCART_FIELD_PRODUCT_VARIANT_HEADING",
                                        "Variant %s",
                                        [String(index + 1)]
                                    )
                                }}
                            </h4>
                            <button
                                type="button"
                                class="nxp-ec-btn nxp-ec-btn--link"
                                @click="duplicateVariant(index)"
                            >
                                {{
                                    __(
                                        "COM_NXPEASYCART_FIELD_VARIANT_DUPLICATE",
                                        "Duplicate"
                                    )
                                }}
                            </button>
                            <button
                                type="button"
                                class="nxp-ec-btn nxp-ec-btn--link nxp-ec-btn--danger"
                                @click="removeVariant(index)"
                                :disabled="form.variants.length <= 1"
                            >
                                {{
                                    __(
                                        "COM_NXPEASYCART_FIELD_PRODUCT_VARIANT_REMOVE",
                                        "Remove"
                                    )
                                }}
                            </button>
                        </header>

                        <div class="nxp-ec-variant-card__grid">
                            <div class="nxp-ec-form-field">
                                <label
                                    class="nxp-ec-form-label"
                                    :for="`variant-sku-${index}`"
                                >
                                    {{
                                        __(
                                            "COM_NXPEASYCART_FIELD_VARIANT_SKU",
                                            "SKU"
                                        )
                                    }}
                                </label>
                                <input
                                    :id="`variant-sku-${index}`"
                                    class="nxp-ec-form-input"
                                    type="text"
                                    v-model.trim="variant.sku"
                                    required
                                />
                            </div>

                            <div class="nxp-ec-form-field">
                                <label
                                    class="nxp-ec-form-label"
                                    :for="`variant-price-${index}`"
                                >
                                    {{
                                        __(
                                            "COM_NXPEASYCART_FIELD_VARIANT_PRICE",
                                            "Price"
                                        )
                                    }}
                                </label>
                                <input
                                    :id="`variant-price-${index}`"
                                    class="nxp-ec-form-input"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    v-model.trim="variant.price"
                                    @blur="formatVariantPrice(index)"
                                    required
                                />
                            </div>

                            <div class="nxp-ec-form-field">
                                <label
                                    class="nxp-ec-form-label"
                                    :for="`variant-stock-${index}`"
                                >
                                    {{
                                        __(
                                            "COM_NXPEASYCART_FIELD_VARIANT_STOCK",
                                            "Stock"
                                        )
                                    }}
                                </label>
                                <input
                                    :id="`variant-stock-${index}`"
                                    class="nxp-ec-form-input"
                                    type="number"
                                    min="0"
                                    step="1"
                                    v-model.number="variant.stock"
                                />
                            </div>

                            <div class="nxp-ec-form-field">
                                <label
                                    class="nxp-ec-form-label"
                                    :for="`variant-weight-${index}`"
                                >
                                    {{
                                        __(
                                            "COM_NXPEASYCART_FIELD_VARIANT_WEIGHT",
                                            "Weight (kg)"
                                        )
                                    }}
                                </label>
                                <input
                                    :id="`variant-weight-${index}`"
                                    class="nxp-ec-form-input"
                                    type="number"
                                    min="0"
                                    step="0.001"
                                    v-model.trim="variant.weight"
                                />
                            </div>

                            <div class="nxp-ec-form-field nxp-ec-form-field--inline">
                                <label
                                    class="nxp-ec-form-label"
                                    :for="`variant-active-${index}`"
                                >
                                    {{
                                        __(
                                            "COM_NXPEASYCART_FIELD_VARIANT_ACTIVE",
                                            "Published"
                                        )
                                    }}
                                </label>
                                <input
                                    :id="`variant-active-${index}`"
                                    class="nxp-ec-form-checkbox"
                                    type="checkbox"
                                    v-model="variant.active"
                                />
                            </div>
                        </div>

                        <section class="nxp-ec-variant-options">
                            <header class="nxp-ec-variant-options__header">
                                <h5 class="nxp-ec-variant-options__title">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_FIELD_VARIANT_OPTIONS",
                                            "Options"
                                        )
                                    }}
                                </h5>
                                <button
                                    type="button"
                                    class="nxp-ec-btn nxp-ec-btn--link"
                                    @click="addVariantOption(index)"
                                >
                                    {{
                                        __(
                                            "COM_NXPEASYCART_FIELD_VARIANT_OPTION_ADD",
                                            "Add option"
                                        )
                                    }}
                                </button>
                            </header>

                            <p class="nxp-ec-form-help">
                                {{
                                    __(
                                        "COM_NXPEASYCART_FIELD_VARIANT_OPTIONS_HELP",
                                        "Add optional key/value pairs such as Size or Colour."
                                    )
                                }}
                            </p>

                            <div
                                v-for="(option, optionIndex) in variant.options"
                                :key="`variant-${index}-option-${optionIndex}`"
                                class="nxp-ec-variant-option-row"
                            >
                                <input
                                    :id="`variant-${index}-option-name-${optionIndex}`"
                                    class="nxp-ec-form-input"
                                    type="text"
                                    v-model.trim="option.name"
                                    :placeholder="
                                        __(
                                            'COM_NXPEASYCART_FIELD_VARIANT_OPTION_NAME',
                                            'Name (e.g. Size)'
                                        )
                                    "
                                />
                                <input
                                    :id="`variant-${index}-option-value-${optionIndex}`"
                                    class="nxp-ec-form-input"
                                    type="text"
                                    v-model.trim="option.value"
                                    :placeholder="
                                        __(
                                            'COM_NXPEASYCART_FIELD_VARIANT_OPTION_VALUE',
                                            'Value (e.g. Large)'
                                        )
                                    "
                                />
                                <button
                                    type="button"
                                    class="nxp-ec-btn nxp-ec-btn--link nxp-ec-btn--danger"
                                    @click="
                                        removeVariantOption(index, optionIndex)
                                    "
                                    :aria-label="
                                        __(
                                            'COM_NXPEASYCART_FIELD_VARIANT_OPTION_REMOVE',
                                            'Remove option'
                                        )
                                    "
                                >
                                    {{ __("COM_NXPEASYCART_REMOVE", "Remove") }}
                                </button>
                            </div>
                        </section>
                    </article>
                </section>

                <footer class="nxp-ec-modal__actions">
                    <button
                        type="button"
                        class="nxp-ec-btn"
                        @click="cancel"
                        :disabled="saving"
                    >
                        {{ __("JCANCEL", "Cancel") }}
                    </button>
                    <button
                        type="submit"
                        class="nxp-ec-btn nxp-ec-btn--primary"
                        :disabled="saving"
                    >
                        {{
                            saving
                                ? __("JPROCESSING_REQUEST", "Saving…")
                                : __("JSAVE", "Save")
                        }}
                    </button>
                </footer>
            </form>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch, onMounted, onBeforeUnmount } from "vue";

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    product: {
        type: Object,
        default: () => null,
    },
    saving: {
        type: Boolean,
        default: false,
    },
    baseCurrency: {
        type: String,
        default: "USD",
    },
    translate: {
        type: Function,
        required: true,
    },
    errors: {
        type: Array,
        default: () => [],
    },
    categoryOptions: {
        type: Array,
        default: () => [],
    },
    mediaModalUrl: {
        type: String,
        default: "",
    },
});

const emit = defineEmits(["submit", "cancel"]);

const __ = props.translate;
const formId = "product-editor-form";

const baseCurrency = computed(() =>
    (props.baseCurrency || "USD").toUpperCase()
);

const normaliseCategoryInput = (input) => {
    if (input == null) {
        return null;
    }

    if (typeof input === "string") {
        const title = input.trim();

        if (title === "") {
            return null;
        }

        return {
            id: 0,
            title,
            slug: "",
        };
    }

    if (typeof input === "object") {
        const id = Number.parseInt(input.id ?? input.value ?? 0, 10) || 0;
        const titleSource =
            input.title ??
            input.name ??
            input.text ??
            input.label ??
            input.slug ??
            "";
        const title = String(titleSource ?? "").trim();
        const slug = String(input.slug ?? "").trim();
        const parentId = input.parent_id !== null && input.parent_id !== undefined
            ? (Number.parseInt(input.parent_id, 10) || null)
            : null;

        if (id <= 0 && title === "") {
            return null;
        }

        return {
            id: id > 0 ? id : 0,
            title: title !== "" ? title : slug,
            slug,
            parent_id: parentId,
        };
    }

    return null;
};

const normaliseStatus = (value) => {
    const numeric = Number(value);

    if (Number.isFinite(numeric)) {
        if (numeric === -1) {
            return -1;
        }

        if (numeric === 0) {
            return 0;
        }

        return 1;
    }

    if (typeof value === "string") {
        const trimmed = value.trim().toLowerCase();

        if (trimmed === "out_of_stock" || trimmed === "out-of-stock" || trimmed === "-1") {
            return -1;
        }

        if (trimmed === "inactive" || trimmed === "0") {
            return 0;
        }
    }

    return 1;
};

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
});
const newCategoryDraft = ref("");
const slugEdited = ref(false);

/**
 * Calculate the depth of a category in the hierarchy.
 * Used for visual indentation in the select dropdown.
 * v1.1 - Added parent_id preservation
 */
const getCategoryDepth = (categoryId, categories, visited = new Set()) => {
    if (!categoryId || visited.has(categoryId)) {
        return 0;
    }

    visited.add(categoryId);

    const category = categories.find(cat => cat.id === categoryId);
    if (!category || !category.parent_id) {
        return 0;
    }

    return 1 + getCategoryDepth(category.parent_id, categories, visited);
};

/**
 * Get the display name for a category with visual indentation.
 * Child categories are prefixed with "─ " characters based on depth.
 */
const getCategoryDisplayName = (category) => {
    if (!category) {
        return '';
    }

    const provided = Array.isArray(props.categoryOptions)
        ? props.categoryOptions
        : [];

    const depth = getCategoryDepth(category.id, provided);

    if (depth === 0) {
        return category.title;
    }

    // Use non-breaking spaces and unicode box characters for visual hierarchy
    // Using \u00A0 (non-breaking space) and \u2514 (box drawing character)
    const indent = '\u00A0\u00A0'.repeat(depth) + '\u2514\u2500 ';
    return indent + category.title;
};

const categoryOptionsList = computed(() => {
    const provided = Array.isArray(props.categoryOptions)
        ? props.categoryOptions
        : [];
    const normalised = provided
        .map(normaliseCategoryInput)
        .filter(
            (category) => category && category.id > 0 && category.title !== ""
        );

    const map = new Map();

    normalised.forEach((category) => {
        map.set(category.id, category);
    });

    form.categories.forEach((category) => {
        if (category.id > 0 && category.title && !map.has(category.id)) {
            map.set(category.id, {
                id: category.id,
                title: category.title,
                slug: category.slug ?? "",
                parent_id: category.parent_id !== null && category.parent_id !== undefined
                    ? category.parent_id
                    : null,
            });
        }
    });

    // Sort categories hierarchically: parents with their children grouped together
    const categories = Array.from(map.values());

    // Build a tree structure to sort properly
    const buildTree = (parentId = null, depth = 0) => {
        return categories
            .filter(cat => cat.parent_id === parentId)
            .sort((a, b) => a.title.localeCompare(b.title))
            .flatMap(cat => [cat, ...buildTree(cat.id, depth + 1)]);
    };

    return buildTree();
});

const categoryOptionsMap = computed(() => {
    const map = new Map();

    categoryOptionsList.value.forEach((category) => {
        map.set(category.id, {
            title: category.title,
            slug: category.slug ?? "",
        });
    });

    return map;
});

const selectedCategoryIds = computed({
    get: () => {
        const ids = form.categories
            .filter((category) => category && category.id > 0)
            .map((category) => category.id);

        if (import.meta?.env?.DEV) {
            console.debug('[ProductEditor] selectedCategoryIds getter:', {
                formCategories: form.categories,
                extractedIds: ids
            });
        }

        return ids;
    },
    set: (value) => {
        const list = Array.isArray(value) ? value : [];
        const uniqueIds = Array.from(
            new Set(
                list
                    .map((entry) => Number.parseInt(entry ?? 0, 10) || 0)
                    .filter((id) => id > 0)
            )
        );

        const newCategories = form.categories.filter(
            (category) => !(category.id > 0)
        );

        const existing = uniqueIds
            .map((id) => {
                const option = categoryOptionsMap.value.get(id);
                const current = form.categories.find(
                    (category) => category.id === id
                );

                const title = option?.title || current?.title || "";
                const slug = option?.slug || current?.slug || "";

                if (title === "") {
                    return null;
                }

                return {
                    id,
                    title,
                    slug,
                };
            })
            .filter((category) => category !== null);

        form.categories.splice(
            0,
            form.categories.length,
            ...existing,
            ...newCategories
        );
    },
});

const mode = computed(() =>
    props.product && props.product.id ? "edit" : "create"
);

const blankVariant = () => ({
    id: 0,
    sku: "",
    price: "",
    currency: baseCurrency.value,
    stock: 0,
    weight: "",
    active: true,
    options: [],
});

const blankOption = () => ({
    name: "",
    value: "",
});

const slugify = (value) => {
    if (!value) {
        return "";
    }

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

const resetCategories = (categories) => {
    const incoming = Array.isArray(categories) ? categories : [];

    const normalised = incoming
        .map(normaliseCategoryInput)
        .filter(
            (category) => category && (category.id > 0 || category.title !== "")
        );

    const seen = new Set();
    const unique = [];

    normalised.forEach((category) => {
        const key =
            category.id > 0
                ? `id:${category.id}`
                : `title:${category.title.toLowerCase()}`;

        if (!seen.has(key)) {
            seen.add(key);
            unique.push(category);
        }
    });

    form.categories.splice(0, form.categories.length, ...unique);
};

const resetVariants = (variants) => {
    form.variants.splice(0, form.variants.length, ...variants);
};

const resetImages = (images) => {
    form.images.splice(0, form.images.length, ...images);
};

const normaliseOptions = (options) => {
    if (Array.isArray(options)) {
        return options.map((option) => ({
            name: String(option?.name ?? option?.key ?? "").trim(),
            value: String(option?.value ?? "").trim(),
        }));
    }

    if (options && typeof options === "object") {
        return Object.entries(options).map(([name, value]) => ({
            name: String(name ?? "").trim(),
            value: String(value ?? "").trim(),
        }));
    }

    return [];
};

const normaliseVariants = (variants) => {
    if (!Array.isArray(variants) || variants.length === 0) {
        return [blankVariant()];
    }

    return variants.map((variant) => ({
        id: Number.parseInt(variant?.id ?? 0, 10) || 0,
        sku: String(variant?.sku ?? "").trim(),
        price:
            variant?.price != null
                ? String(variant.price)
                : Number.isFinite(variant?.price_cents)
                  ? (variant.price_cents / 100).toFixed(2)
                  : "",
        currency: String(variant?.currency ?? baseCurrency.value).toUpperCase(),
        stock: Number.parseInt(variant?.stock ?? 0, 10) || 0,
        weight: variant?.weight != null ? String(variant.weight) : "",
        active: variant?.active !== undefined ? Boolean(variant.active) : true,
        options: normaliseOptions(variant?.options),
    }));
};

const applyProduct = (product) => {
    const source = product ? JSON.parse(JSON.stringify(product)) : {};

    form.title = source.title ?? "";
    form.slug = source.slug ?? "";
    form.short_desc = source.short_desc ?? "";
    form.long_desc = source.long_desc ?? "";
    form.status = normaliseStatus(
        source.status !== undefined ? source.status : source.active
    );
    form.featured = source.featured !== undefined ? Boolean(source.featured) : false;

    const images = Array.isArray(source.images)
        ? source.images
              .map((image) => String(image ?? "").trim())
              .filter(Boolean)
        : [];
    resetImages(images);

    const categories = Array.isArray(source.categories)
        ? source.categories
        : [];
    resetCategories(categories);

    newCategoryDraft.value = "";

    resetVariants(normaliseVariants(source.variants));
    slugEdited.value = Boolean(form.slug);
};

watch(
    () => props.product,
    (product) => {
        applyProduct(product);
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

const addCategory = () => {
    const value = newCategoryDraft.value.trim();

    if (!value) {
        return;
    }

    const lowerValue = value.toLowerCase();
    const categories = form.categories.slice();

    const exists = categories.some(
        (category) => category.title.toLowerCase() === lowerValue
    );

    if (!exists) {
        categories.push({
            id: 0,
            title: value,
            slug: "",
        });

        form.categories.splice(0, form.categories.length, ...categories);
    }

    newCategoryDraft.value = "";
};

const removeCategory = (index) => {
    if (index < 0 || index >= form.categories.length) {
        return;
    }

    const categories = form.categories.slice();
    categories.splice(index, 1);
    form.categories.splice(0, form.categories.length, ...categories);
};

const addVariant = () => {
    form.variants.push(blankVariant());
};

const removeVariant = (index) => {
    if (form.variants.length <= 1) {
        return;
    }

    form.variants.splice(index, 1);
};

const addVariantOption = (variantIndex) => {
    const target = form.variants[variantIndex];

    if (!target) {
        return;
    }

    if (!Array.isArray(target.options)) {
        target.options = [];
    }

    target.options.push(blankOption());
};

const removeVariantOption = (variantIndex, optionIndex) => {
    const target = form.variants[variantIndex];

    if (!target || !Array.isArray(target.options)) {
        return;
    }

    target.options.splice(optionIndex, 1);
};

const variantKey = (variant, index) => `${variant.id || "new"}-${index}`;

const duplicateVariant = (index) => {
    const original = form.variants[index];

    if (!original) {
        return;
    }

    const clone = JSON.parse(JSON.stringify(original));
    clone.id = 0;
    clone.sku = [clone.sku || "SKU", "COPY"].join("-").replace(/-+/g, "-");
    clone.active = true;

    form.variants.splice(index + 1, 0, clone);
};

const formatVariantPrice = (index) => {
    const variant = form.variants[index];

    if (!variant) {
        return;
    }

    const numeric = Number.parseFloat(variant.price);

    if (Number.isNaN(numeric)) {
        return;
    }

    variant.price = numeric.toFixed(2);
};

let mediaPickerField = null;
let mediaPickerWrapper = null;
let mediaPickerInput = null;
let mediaPickerIndex = null;
let pendingMediaValue = "";

const ensureImagesArray = () => {
    if (!Array.isArray(form.images)) {
        form.images = [];
    }
};

const normaliseMediaValue = (value) => {
    if (!value) {
        return "";
    }

    let result = String(value).trim();

    if (result === "") {
        return "";
    }

    // Convert absolute site URLs back to relative paths
    try {
        const systemPaths = window.Joomla?.getOptions?.("system.paths", {}) ?? {};
        const rootFull = systemPaths.rootFull || window.location.origin;
        const rootRel = systemPaths.root || "/";

        if (result.startsWith(rootFull)) {
            result = result.substring(rootFull.length);
        }

        if (result.startsWith(rootRel)) {
            result = result.substring(rootRel.length);
        }
    } catch (error) {
        // ignore
    }

    // Handle media adapter prefixes like local-images:/path/to/file.jpg
    const adapterMatch = result.match(/^(?:local-[a-z0-9_-]+|images|videos|audios|documents):\/\/?(.*)$/i);
    if (adapterMatch && adapterMatch[1]) {
        result = adapterMatch[1];
    }

    // Remove Joomla image metadata suffix
    const metadataIndex = result.indexOf("#joomlaImage://");
    if (metadataIndex !== -1) {
        result = result.substring(0, metadataIndex);
    }

    return result.trim();
};

const applyImageSelection = (
    rawValue,
    { keepIndex = false, appendWhenNoIndex = false, targetIndex = null } = {}
) => {
    const value = normaliseMediaValue(rawValue);

    if (value === "") {
        return false;
    }

    ensureImagesArray();

    const indexToUse =
        targetIndex !== null && targetIndex >= 0 ? targetIndex : mediaPickerIndex;

    if (
        indexToUse === null ||
        indexToUse === undefined ||
        Number.isNaN(indexToUse)
    ) {
        if (!appendWhenNoIndex) {
            return false;
        }

        form.images.push(value);

        if (!keepIndex) {
            mediaPickerIndex = null;
            pendingMediaValue = "";
        }

        return true;
    }

    if (indexToUse >= form.images.length) {
        form.images.push(value);
    } else {
        form.images[indexToUse] = value;
    }

    pendingMediaValue = keepIndex ? value : "";

    if (!keepIndex) {
        mediaPickerIndex = null;
        pendingMediaValue = "";
    }

    return true;
};

const addImage = () => {
    ensureImagesArray();
    form.images.push("");
};

const removeImage = (index) => {
    ensureImagesArray();
    form.images.splice(index, 1);
};

const moveImage = (index, offset) => {
    ensureImagesArray();

    const current = form.images[index];

    if (current === undefined) {
        return;
    }

    const nextIndex = index + offset;

    if (nextIndex < 0 || nextIndex >= form.images.length) {
        return;
    }

    form.images.splice(index, 1);
    form.images.splice(nextIndex, 0, current);
};

const promptForImage = (index) => {
    ensureImagesArray();

    const current = form.images[index] ?? "";

    if (typeof window === "undefined") {
        return;
    }

    const value = window.prompt(
        __("COM_NXPEASYCART_FIELD_PRODUCT_IMAGES_PROMPT", "Image URL"),
        current
    );

    applyImageSelection(value ?? "", { targetIndex: index });
};

const buildMediaModalUrl = () => {
    const configured = (props.mediaModalUrl || "").trim();

    if (configured !== "") {
        return configured;
    }

    const mediaPickerOptions =
        window.Joomla?.getOptions?.("media-picker", {}) ?? {};
    if (mediaPickerOptions.modalUrl) {
        return mediaPickerOptions.modalUrl;
    }

    const systemPaths = window.Joomla?.getOptions?.("system.paths", {}) ?? {};
    const root = systemPaths.rootFull || systemPaths.root || "";
    const base = root ? root.replace(/\/?$/, "/") : "";

    return (
        base +
        "index.php?option=com_media&view=media&tmpl=component&layout=modal&mediatypes=0,1,2,3&asset=com_nxpeasycart"
    );
};

const ensureMediaPickerField = async () => {
    if (mediaPickerField) {
        return mediaPickerField;
    }

    if (
        typeof window === "undefined" ||
        !window.Joomla ||
        !window.customElements
    ) {
        return null;
    }

    if (!customElements.get("joomla-field-media")) {
        try {
            await customElements.whenDefined("joomla-field-media");
        } catch (error) {
            return null;
        }
    }

    const systemPaths = window.Joomla?.getOptions?.("system.paths", {}) ?? {};
    const rootFull = systemPaths.rootFull || systemPaths.root || "";
    const mediaParams = window.Joomla?.getOptions?.("com_media", {}) ?? {};
    const rootFolder = mediaParams?.file_path || "images";
    const supported =
        window.Joomla?.getOptions?.("media-picker", {}) ?? {
            images: ["bmp", "gif", "jpg", "jpeg", "png", "webp", "svg", "avif"],
            audios: [],
            videos: [],
            documents: [],
        };
    const modalTitleText = __(
        "COM_NXPEASYCART_FIELD_PRODUCT_IMAGES_MODAL_TITLE",
        "Media Manager",
        [],
        "productImagesModalTitle"
    );
    const safeModalTitle = modalTitleText.replace(/"/g, "&quot;");

    mediaPickerWrapper = document.createElement("div");
    mediaPickerWrapper.className = "nxp-ec-media-picker-host";
    mediaPickerWrapper.style.display = "none";

    mediaPickerWrapper.innerHTML = `
        <joomla-field-media
            class="nxp-ec-media-field"
            types="images"
            base-path="${rootFull.replace(/"/g, "&quot;")}"
            root-folder="${rootFolder.replace(/"/g, "&quot;")}"
            url=""
            preview="none"
            input=".nxp-ec-media-field__input"
            button-select=".nxp-ec-media-field__select"
            button-clear=".nxp-ec-media-field__clear"
            modal-title="${safeModalTitle}"
            modal-width="960"
            modal-height="600"
            supported-extensions='${JSON.stringify(supported).replace(/'/g, "&apos;")}'
        >
            <div class="input-group">
                <input type="text" class="nxp-ec-media-field__input field-media-input" />
                <button type="button" class="nxp-ec-media-field__select button-select"></button>
                <button type="button" class="nxp-ec-media-field__clear button-clear"></button>
            </div>
        </joomla-field-media>
    `;

    document.body.appendChild(mediaPickerWrapper);

    mediaPickerField = mediaPickerWrapper.querySelector("joomla-field-media");
    mediaPickerInput = mediaPickerWrapper.querySelector(
        ".nxp-ec-media-field__input"
    );

    mediaPickerField.addEventListener("change", (event) => {
        const rawValue =
            event.detail?.value ?? mediaPickerInput?.value ?? "";
        let value = normaliseMediaValue(rawValue);

        if (value === "" && pendingMediaValue !== "") {
            value = pendingMediaValue;
        }

        if (value === "" && window.Joomla?.selectedMediaFile) {
            const fallbackUrl = normaliseMediaValue(
                window.Joomla.selectedMediaFile.url ?? ""
            );
            const fallbackPath = normaliseMediaValue(
                window.Joomla.selectedMediaFile.path ?? ""
            );
            value = fallbackUrl || fallbackPath;
        }

        if (import.meta?.env?.DEV) {
            console.debug("[ProductEditor] media change", {
                value,
                rawValue,
                pending: pendingMediaValue,
                index: mediaPickerIndex,
                detail: event.detail,
            });
        }

        applyImageSelection(value);
    });

    mediaPickerField.addEventListener("joomla-dialog:close", () => {
        if (import.meta?.env?.DEV) {
            console.debug("[ProductEditor] dialog closed, index:", mediaPickerIndex);
        }
        mediaPickerIndex = null;
        pendingMediaValue = "";
    });

    if (mediaPickerInput) {
        mediaPickerInput.addEventListener("change", () => {
            if (mediaPickerIndex === null) {
                return;
            }

            let value = normaliseMediaValue(mediaPickerInput.value ?? "");

            if (value === "" && pendingMediaValue !== "") {
                value = pendingMediaValue;
        }

        if (value === "" && window.Joomla?.selectedMediaFile) {
            const fallbackUrl = normaliseMediaValue(
                window.Joomla.selectedMediaFile.url ?? ""
            );
            const fallbackPath = normaliseMediaValue(
                window.Joomla.selectedMediaFile.path ?? ""
            );
            value = fallbackUrl || fallbackPath;
        }

            if (import.meta?.env?.DEV) {
                console.debug("[ProductEditor] input change", {
                    value,
                    pending: pendingMediaValue,
                    index: mediaPickerIndex,
                });
            }

            if (value === "") {
                return;
            }

            applyImageSelection(value);
        });
    }

    return mediaPickerField;
};

const handleMediaFileSelected = (event) => {
    const detail = event?.detail;

    if (!detail || typeof detail !== "object") {
        return;
    }

    const path = normaliseMediaValue(detail.path ?? "");
    const url = normaliseMediaValue(detail.url ?? "");

    const resolved = url || path;

    if (import.meta?.env?.DEV) {
        console.debug("[ProductEditor] media file selected", {
            resolved,
            path,
            url,
            detail,
            currentIndex: mediaPickerIndex,
        });
    }

    if (resolved === "" || mediaPickerIndex === null) {
        return;
    }

    pendingMediaValue = resolved;

    if (
        applyImageSelection(resolved, {
            keepIndex: true,
        }) &&
        import.meta?.env?.DEV
    ) {
        console.debug("[ProductEditor] Applied image immediately", {
            index: mediaPickerIndex,
            value: resolved,
        });
    }
};

onMounted(() => {
    if (typeof document !== "undefined") {
        document.addEventListener("onMediaFileSelected", handleMediaFileSelected);
    }
});

const openMediaModal = async (index) => {
    ensureImagesArray();

    const picker = await ensureMediaPickerField();

    if (!picker || typeof picker.show !== "function") {
        promptForImage(index);
        return;
    }

    mediaPickerIndex = index;
    picker.setAttribute("url", buildMediaModalUrl());

    const currentValue = form.images[index] ?? "";

    if (typeof picker.setValue === "function") {
        picker.setValue(currentValue);
    } else if (mediaPickerInput) {
        mediaPickerInput.value = currentValue;
    }

    try {
        picker.show();
    } catch (error) {
        mediaPickerIndex = null;
        pendingMediaValue = "";
        promptForImage(index);
    }
};

onBeforeUnmount(() => {
    if (mediaPickerWrapper && mediaPickerWrapper.parentNode) {
        mediaPickerWrapper.parentNode.removeChild(mediaPickerWrapper);
    }

    mediaPickerField = null;
    mediaPickerWrapper = null;
    mediaPickerInput = null;
    mediaPickerIndex = null;
    pendingMediaValue = "";

    if (typeof document !== "undefined") {
        document.removeEventListener("onMediaFileSelected", handleMediaFileSelected);
    }
});

const submit = () => {
    ensureImagesArray();

    const payloadImages = Array.from(
        new Set(
            form.images
                .map((image) => String(image ?? "").trim())
                .filter((image) => image !== "")
        )
    );

    if (import.meta?.env?.DEV) {
        console.debug('[ProductEditor] submit payload images', payloadImages);
    }

    const payloadCategories = form.categories
        .map((category) => {
            const id = Number.parseInt(category?.id ?? 0, 10) || 0;
            const title = String(category?.title ?? "").trim();
            const slug = String(category?.slug ?? "").trim();

            if (id > 0) {
                return {
                    id,
                    title,
                    slug,
                };
            }

            if (title === "") {
                return null;
            }

            return {
                id: 0,
                title,
                slug: "",
            };
        })
        .filter((category) => category !== null);

    const payloadVariants = form.variants.map((variant) => {
        const options = Array.isArray(variant.options)
            ? variant.options
                  .map((option) => ({
                      name: String(option?.name ?? "").trim(),
                      value: String(option?.value ?? "").trim(),
                  }))
                  .filter((option) => option.name !== "" && option.value !== "")
            : [];

        const stock = Number.isFinite(Number(variant.stock))
            ? Math.max(0, parseInt(variant.stock, 10))
            : 0;

        const weight =
            variant.weight !== null && variant.weight !== ""
                ? String(variant.weight).trim()
                : null;

        return {
            id: variant.id || 0,
            sku: variant.sku.trim(),
            price:
                variant.price !== null && variant.price !== undefined
                    ? String(variant.price).trim()
                    : "",
            currency: variant.currency
                ? String(variant.currency).trim().toUpperCase()
                : baseCurrency.value,
            stock,
            weight,
            active: Boolean(variant.active),
            options,
        };
    });

    const status = normaliseStatus(form.status);

    const payload = {
        title: form.title.trim(),
        slug: form.slug.trim(),
        short_desc: form.short_desc ?? "",
        long_desc: form.long_desc ?? "",
        status,
        active: status,
        featured: Boolean(form.featured),
        images: payloadImages,
        categories: payloadCategories,
        variants: payloadVariants,
    };

    emit("submit", payload);
};

const cancel = () => {
    emit("cancel");
};

watch(
    () => props.saving,
    (saving, wasSaving) => {
        if (wasSaving && !saving && props.errors.length === 0) {
            // When save completes successfully, reset to fresh state
            if (mode.value === "create") {
                applyProduct(null);
                newCategoryDraft.value = "";
                slugEdited.value = false;
            }
        }
    }
);
</script>

<style scoped>
#product-category-select {
    font-family: ui-monospace, 'SF Mono', 'Cascadia Code', 'Roboto Mono', 'Ubuntu Mono', 'Menlo', 'Consolas', 'Monaco', 'Liberation Mono', monospace;
    white-space: pre;
    min-height: 200px;
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

.nxp-ec-image-list {
    display: grid;
    gap: 0.75rem;
}

.nxp-ec-image-row {
    display: grid;
    gap: 0.5rem;
}

@media (min-width: 640px) {
    .nxp-ec-image-row {
        grid-template-columns: 1fr auto;
        align-items: center;
    }
}

.nxp-ec-image-row__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    justify-content: flex-end;
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

    #product-category-select {
        min-height: 150px;
    }

    .nxp-ec-image-row__actions {
        justify-content: stretch;
    }

    .nxp-ec-image-row__actions .nxp-ec-btn {
        flex: 1;
    }
}
</style>
