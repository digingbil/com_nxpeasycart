<template>
    <section class="nxp-ec-editor-panel">
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
                @input="$emit('slug-input')"
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

        <div class="nxp-ec-form-field">
            <label class="nxp-ec-form-label" for="product-type">
                {{ __("COM_NXPEASYCART_FIELD_PRODUCT_TYPE", "Product type") }}
            </label>
            <select
                id="product-type"
                class="nxp-ec-form-select"
                v-model="form.product_type"
            >
                <option value="physical">
                    {{
                        __(
                            "COM_NXPEASYCART_FIELD_PRODUCT_TYPE_PHYSICAL",
                            "Physical product"
                        )
                    }}
                </option>
                <option value="digital">
                    {{
                        __(
                            "COM_NXPEASYCART_FIELD_PRODUCT_TYPE_DIGITAL",
                            "Digital product"
                        )
                    }}
                </option>
            </select>
            <p class="nxp-ec-form-help">
                {{
                    __(
                        "COM_NXPEASYCART_CHECKOUT_DIGITAL_NOTE",
                        "Digital items skip shipping and deliver download links."
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
    </section>
</template>

<script setup>
/**
 * GeneralTab - Basic product information.
 *
 * Handles title, slug, descriptions, status, type, and featured flag.
 *
 * @since 0.3.2
 */

const props = defineProps({
    /**
     * Form object containing product data (reactive, mutations allowed).
     */
    form: {
        type: Object,
        required: true,
    },
    /**
     * Translation function from parent.
     */
    translate: {
        type: Function,
        required: true,
    },
});

defineEmits(["slug-input"]);

const __ = props.translate;
</script>
