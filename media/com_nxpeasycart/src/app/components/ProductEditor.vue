<template>
    <div v-if="open" class="nxp-modal" role="dialog" aria-modal="true">
        <div class="nxp-modal__backdrop"></div>
        <div class="nxp-modal__dialog">
            <header class="nxp-modal__header">
                <h2 class="nxp-modal__title">
                    {{
                        mode === "edit"
                            ? __(
                                  "COM_NXPEASYCART_PRODUCTS_EDIT",
                                  "Edit product"
                              )
                            : __("COM_NXPEASYCART_PRODUCTS_ADD", "Add product")
                    }}
                </h2>
                <button
                    type="button"
                    class="nxp-modal__close"
                    @click="cancel"
                    :aria-label="__('JCLOSE', 'Close')"
                >
                    &times;
                </button>
            </header>

            <form class="nxp-form" @submit.prevent="submit">
                <div
                    v-if="errors.length"
                    class="nxp-admin-alert nxp-admin-alert--error"
                >
                    <p v-for="(error, index) in errors" :key="index">
                        {{ error }}
                    </p>
                </div>

                <div class="nxp-form-field">
                    <label class="nxp-form-label" for="product-title">
                        {{ __("COM_NXPEASYCART_FIELD_PRODUCT_TITLE", "Title") }}
                    </label>
                    <input
                        id="product-title"
                        class="nxp-form-input"
                        type="text"
                        v-model.trim="form.title"
                        required
                    />
                </div>

                <div class="nxp-form-field">
                    <label class="nxp-form-label" for="product-slug">
                        {{ __("COM_NXPEASYCART_FIELD_PRODUCT_SLUG", "Slug") }}
                    </label>
                    <input
                        id="product-slug"
                        class="nxp-form-input"
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

                <div class="nxp-form-field">
                    <label class="nxp-form-label" for="product-short-desc">
                        {{
                            __(
                                "COM_NXPEASYCART_FIELD_PRODUCT_SHORT_DESC",
                                "Short description"
                            )
                        }}
                    </label>
                    <textarea
                        id="product-short-desc"
                        class="nxp-form-textarea"
                        rows="3"
                        v-model="form.short_desc"
                    ></textarea>
                </div>

                <div class="nxp-form-field">
                    <label class="nxp-form-label" for="product-long-desc">
                        {{
                            __(
                                "COM_NXPEASYCART_FIELD_PRODUCT_LONG_DESC",
                                "Long description"
                            )
                        }}
                    </label>
                    <textarea
                        id="product-long-desc"
                        class="nxp-form-textarea"
                        rows="5"
                        v-model="form.long_desc"
                    ></textarea>
                </div>

                <div class="nxp-form-field nxp-form-field--inline">
                    <label class="nxp-form-label" for="product-active">
                        {{
                            __(
                                "COM_NXPEASYCART_FIELD_PRODUCT_ACTIVE",
                                "Published"
                            )
                        }}
                    </label>
                    <input
                        id="product-active"
                        type="checkbox"
                        class="nxp-form-checkbox"
                        v-model="form.active"
                    />
                </div>

                <div class="nxp-form-field">
                    <label class="nxp-form-label" for="product-images">
                        {{
                            __("COM_NXPEASYCART_FIELD_PRODUCT_IMAGES", "Images")
                        }}
                    </label>
                    <div id="product-images" class="nxp-image-list">
                        <div
                            v-for="(image, index) in form.images"
                            :key="`product-image-${index}`"
                            class="nxp-image-row"
                        >
                            <input
                                :id="`product-image-${index}`"
                                class="nxp-form-input"
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
                            <div class="nxp-image-row__actions">
                                <button
                                    type="button"
                                    class="nxp-btn nxp-btn--link"
                                    @click="selectImage(index)"
                                >
                                    {{
                                        __(
                                            "COM_NXPEASYCART_FIELD_PRODUCT_IMAGES_SELECT",
                                            "Select",
                                            [],
                                            "productImagesSelect"
                                        )
                                    }}
                                </button>
                                <button
                                    type="button"
                                    class="nxp-btn nxp-btn--link"
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
                                    class="nxp-btn nxp-btn--link"
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
                                    class="nxp-btn nxp-btn--link nxp-btn--danger"
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
                    <button type="button" class="nxp-btn" @click="addImage">
                        {{
                            __(
                                "COM_NXPEASYCART_FIELD_PRODUCT_IMAGES_ADD",
                                "Add image",
                                [],
                                "productImagesAdd"
                            )
                        }}
                    </button>
                    <p class="nxp-form-help">
                        {{
                            __(
                                "COM_NXPEASYCART_FIELD_PRODUCT_IMAGES_HELP",
                                "These URLs are stored as-is; ensure they are publicly accessible."
                            )
                        }}
                    </p>
                </div>

                <div class="nxp-form-field">
                    <label class="nxp-form-label" for="product-category-select">
                        {{
                            __(
                                "COM_NXPEASYCART_PRODUCT_CATEGORIES_LABEL",
                                "Categories"
                            )
                        }}
                    </label>
                    <select
                        id="product-category-select"
                        class="nxp-form-select"
                        multiple
                        v-model="selectedCategoryIds"
                    >
                        <option
                            v-for="option in categoryOptionsList"
                            :key="option.id"
                            :value="option.id"
                        >
                            {{ option.title }}
                        </option>
                    </select>
                    <div class="nxp-chip-input nxp-chip-input--selected">
                        <span
                            v-for="(category, index) in form.categories"
                            :key="`selected-category-${index}`"
                            class="nxp-chip"
                        >
                            {{ category.title }}
                            <button
                                type="button"
                                class="nxp-chip__remove"
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
                    <div class="nxp-chip-input nxp-chip-input--new">
                        <input
                            id="product-category-input"
                            type="text"
                            class="nxp-chip-input__field"
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
                            class="nxp-btn"
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
                    <p class="nxp-form-help">
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

                <section class="nxp-variant-section">
                    <header class="nxp-variant-section__header">
                        <h3 class="nxp-variant-section__title">
                            {{
                                __(
                                    "COM_NXPEASYCART_FIELD_PRODUCT_VARIANTS",
                                    "Variants"
                                )
                            }}
                        </h3>
                        <button
                            type="button"
                            class="nxp-btn"
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

                    <p class="nxp-form-help">
                        {{
                            __(
                                "COM_NXPEASYCART_FIELD_PRODUCT_VARIANTS_HELP",
                                "Each product needs at least one variant with a price and currency."
                            )
                        }}
                    </p>

                    <article
                        v-for="(variant, index) in form.variants"
                        :key="variantKey(variant, index)"
                        class="nxp-variant-card"
                    >
                        <header class="nxp-variant-card__header">
                            <h4 class="nxp-variant-card__title">
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
                                class="nxp-btn nxp-btn--link"
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
                                class="nxp-btn nxp-btn--link nxp-btn--danger"
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

                        <div class="nxp-variant-card__grid">
                            <div class="nxp-form-field">
                                <label
                                    class="nxp-form-label"
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
                                    class="nxp-form-input"
                                    type="text"
                                    v-model.trim="variant.sku"
                                    required
                                />
                            </div>

                            <div class="nxp-form-field">
                                <label
                                    class="nxp-form-label"
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
                                    class="nxp-form-input"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    v-model.trim="variant.price"
                                    @blur="formatVariantPrice(index)"
                                    required
                                />
                            </div>

                            <div class="nxp-form-field">
                                <label
                                    class="nxp-form-label"
                                    :for="`variant-currency-${index}`"
                                >
                                    {{
                                        __(
                                            "COM_NXPEASYCART_FIELD_VARIANT_CURRENCY",
                                            "Currency"
                                        )
                                    }}
                                </label>
                                <input
                                    :id="`variant-currency-${index}`"
                                    class="nxp-form-input"
                                    type="text"
                                    maxlength="3"
                                    v-model.trim="variant.currency"
                                    readonly
                                    aria-readonly="true"
                                />
                                <p class="nxp-form-help">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_FIELD_VARIANT_CURRENCY_HELP",
                                            "Variants inherit the store base currency (%s).",
                                            [baseCurrency]
                                        )
                                    }}
                                </p>
                            </div>

                            <div class="nxp-form-field">
                                <label
                                    class="nxp-form-label"
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
                                    class="nxp-form-input"
                                    type="number"
                                    min="0"
                                    step="1"
                                    v-model.number="variant.stock"
                                />
                            </div>

                            <div class="nxp-form-field">
                                <label
                                    class="nxp-form-label"
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
                                    class="nxp-form-input"
                                    type="number"
                                    min="0"
                                    step="0.001"
                                    v-model.trim="variant.weight"
                                />
                            </div>

                            <div class="nxp-form-field nxp-form-field--inline">
                                <label
                                    class="nxp-form-label"
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
                                    class="nxp-form-checkbox"
                                    type="checkbox"
                                    v-model="variant.active"
                                />
                            </div>
                        </div>

                        <section class="nxp-variant-options">
                            <header class="nxp-variant-options__header">
                                <h5 class="nxp-variant-options__title">
                                    {{
                                        __(
                                            "COM_NXPEASYCART_FIELD_VARIANT_OPTIONS",
                                            "Options"
                                        )
                                    }}
                                </h5>
                                <button
                                    type="button"
                                    class="nxp-btn nxp-btn--link"
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

                            <p class="nxp-form-help">
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
                                class="nxp-variant-option-row"
                            >
                                <input
                                    :id="`variant-${index}-option-name-${optionIndex}`"
                                    class="nxp-form-input"
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
                                    class="nxp-form-input"
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
                                    class="nxp-btn nxp-btn--link nxp-btn--danger"
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

                <footer class="nxp-modal__actions">
                    <button
                        type="button"
                        class="nxp-btn"
                        @click="cancel"
                        :disabled="saving"
                    >
                        {{ __("JCANCEL", "Cancel") }}
                    </button>
                    <button
                        type="submit"
                        class="nxp-btn nxp-btn--primary"
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
import { computed, reactive, ref, watch } from "vue";

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
});

const emit = defineEmits(["submit", "cancel"]);

const __ = props.translate;

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

        if (id <= 0 && title === "") {
            return null;
        }

        return {
            id: id > 0 ? id : 0,
            title: title !== "" ? title : slug,
            slug,
        };
    }

    return null;
};

const form = reactive({
    title: "",
    slug: "",
    short_desc: "",
    long_desc: "",
    active: true,
    images: [],
    categories: [],
    variants: [],
});
const newCategoryDraft = ref("");
const slugEdited = ref(false);

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
            });
        }
    });

    return Array.from(map.values()).sort((a, b) =>
        a.title.localeCompare(b.title)
    );
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
    get: () =>
        form.categories
            .filter((category) => category.id > 0)
            .map((category) => category.id),
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
    form.active = source.active !== undefined ? Boolean(source.active) : true;

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

const ensureImagesArray = () => {
    if (!Array.isArray(form.images)) {
        form.images = [];
    }
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

const selectImage = (index) => {
    ensureImagesArray();

    const current = form.images[index] ?? "";

    if (typeof window === "undefined") {
        return;
    }

    const value = window.prompt(
        __("COM_NXPEASYCART_FIELD_PRODUCT_IMAGES_PROMPT", "Image URL"),
        current
    );

    if (value !== null && value.trim() !== "") {
        form.images[index] = value.trim();
    }
};

const submit = () => {
    ensureImagesArray();

    const payloadImages = Array.from(
        new Set(
            form.images
                .map((image) => String(image ?? "").trim())
                .filter((image) => image !== "")
        )
    );

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

    const payload = {
        title: form.title.trim(),
        slug: form.slug.trim(),
        short_desc: form.short_desc ?? "",
        long_desc: form.long_desc ?? "",
        active: Boolean(form.active),
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
.nxp-image-list {
    display: grid;
    gap: 0.75rem;
}

.nxp-image-row {
    display: grid;
    gap: 0.5rem;
}

@media (min-width: 640px) {
    .nxp-image-row {
        grid-template-columns: 1fr auto;
        align-items: center;
    }
}

.nxp-image-row__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    justify-content: flex-end;
}
</style>
