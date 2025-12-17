<template>
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
                @click="$emit('add-variant')"
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
            v-for="(variant, index) in variants"
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
                    @click="$emit('duplicate-variant', index)"
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
                    @click="$emit('remove-variant', index)"
                    :disabled="variants.length <= 1"
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
                <!-- SKU -->
                <div class="nxp-ec-form-field">
                    <label
                        class="nxp-ec-form-label"
                        :for="`variant-sku-${index}`"
                    >
                        {{ __("COM_NXPEASYCART_FIELD_VARIANT_SKU", "SKU") }}
                    </label>
                    <input
                        :id="`variant-sku-${index}`"
                        class="nxp-ec-form-input"
                        type="text"
                        v-model.trim="variant.sku"
                        required
                    />
                </div>

                <!-- EAN -->
                <div class="nxp-ec-form-field">
                    <label
                        class="nxp-ec-form-label"
                        :for="`variant-ean-${index}`"
                    >
                        {{ __("COM_NXPEASYCART_FIELD_VARIANT_EAN", "EAN") }}
                    </label>
                    <input
                        :id="`variant-ean-${index}`"
                        class="nxp-ec-form-input"
                        type="text"
                        v-model.trim="variant.ean"
                        :placeholder="
                            __(
                                'COM_NXPEASYCART_FIELD_VARIANT_EAN_PLACEHOLDER',
                                '8 or 13 digits'
                            )
                        "
                        maxlength="13"
                        pattern="\d{8}|\d{13}"
                    />
                </div>

                <!-- Price -->
                <div class="nxp-ec-form-field">
                    <label
                        class="nxp-ec-form-label"
                        :for="`variant-price-${index}`"
                    >
                        {{ __("COM_NXPEASYCART_FIELD_VARIANT_PRICE", "Price") }}
                    </label>
                    <input
                        :id="`variant-price-${index}`"
                        class="nxp-ec-form-input"
                        type="number"
                        min="0"
                        step="0.01"
                        v-model.trim="variant.price"
                        @blur="$emit('format-price', index)"
                        required
                    />
                </div>

                <!-- Sale Price -->
                <div class="nxp-ec-form-field">
                    <label
                        class="nxp-ec-form-label"
                        :for="`variant-sale-price-${index}`"
                    >
                        {{ __("COM_NXPEASYCART_FIELD_VARIANT_SALE_PRICE", "Sale Price") }}
                    </label>
                    <input
                        :id="`variant-sale-price-${index}`"
                        class="nxp-ec-form-input"
                        type="number"
                        min="0"
                        step="0.01"
                        v-model.trim="variant.sale_price"
                        @blur="$emit('format-sale-price', index)"
                        :placeholder="__('COM_NXPEASYCART_FIELD_VARIANT_SALE_PRICE_PLACEHOLDER', 'Leave empty if not on sale')"
                    />
                </div>

                <!-- Sale Start -->
                <div class="nxp-ec-form-field">
                    <label
                        class="nxp-ec-form-label"
                        :for="`variant-sale-start-${index}`"
                    >
                        {{ __("COM_NXPEASYCART_FIELD_VARIANT_SALE_START", "Sale Start") }}
                    </label>
                    <input
                        :id="`variant-sale-start-${index}`"
                        class="nxp-ec-form-input"
                        type="datetime-local"
                        v-model="variant.sale_start_local"
                        :disabled="!variant.sale_price || variant.sale_price === ''"
                    />
                    <p class="nxp-ec-form-help">
                        {{ __("COM_NXPEASYCART_FIELD_VARIANT_SALE_START_HELP", "Leave empty to start immediately") }}
                    </p>
                </div>

                <!-- Sale End -->
                <div class="nxp-ec-form-field">
                    <label
                        class="nxp-ec-form-label"
                        :for="`variant-sale-end-${index}`"
                    >
                        {{ __("COM_NXPEASYCART_FIELD_VARIANT_SALE_END", "Sale End") }}
                    </label>
                    <input
                        :id="`variant-sale-end-${index}`"
                        class="nxp-ec-form-input"
                        type="datetime-local"
                        v-model="variant.sale_end_local"
                        :disabled="!variant.sale_price || variant.sale_price === ''"
                    />
                    <p class="nxp-ec-form-help">
                        {{ __("COM_NXPEASYCART_FIELD_VARIANT_SALE_END_HELP", "Leave empty for no expiration") }}
                    </p>
                </div>

                <!-- Stock -->
                <div class="nxp-ec-form-field">
                    <label
                        class="nxp-ec-form-label"
                        :for="`variant-stock-${index}`"
                    >
                        {{ __("COM_NXPEASYCART_FIELD_VARIANT_STOCK", "Stock") }}
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

                <!-- Weight -->
                <div class="nxp-ec-form-field">
                    <label
                        class="nxp-ec-form-label"
                        :for="`variant-weight-${index}`"
                    >
                        {{ __("COM_NXPEASYCART_FIELD_VARIANT_WEIGHT", "Weight (kg)") }}
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

                <!-- Digital Item -->
                <div class="nxp-ec-form-field nxp-ec-form-field--inline">
                    <label
                        class="nxp-ec-form-label"
                        :for="`variant-digital-${index}`"
                    >
                        {{ __("COM_NXPEASYCART_FIELD_VARIANT_DIGITAL", "Digital item") }}
                    </label>
                    <input
                        :id="`variant-digital-${index}`"
                        class="nxp-ec-form-checkbox"
                        type="checkbox"
                        v-model="variant.is_digital"
                    />
                    <p class="nxp-ec-form-help">
                        {{ __("COM_NXPEASYCART_FIELD_VARIANT_DIGITAL_HELP", "Digital variants skip shipping.") }}
                    </p>
                </div>

                <!-- Published -->
                <div class="nxp-ec-form-field nxp-ec-form-field--inline">
                    <label
                        class="nxp-ec-form-label"
                        :for="`variant-active-${index}`"
                    >
                        {{ __("COM_NXPEASYCART_FIELD_VARIANT_ACTIVE", "Published") }}
                    </label>
                    <input
                        :id="`variant-active-${index}`"
                        class="nxp-ec-form-checkbox"
                        type="checkbox"
                        v-model="variant.active"
                    />
                </div>
            </div>

            <!-- Options Section -->
            <section class="nxp-ec-variant-options">
                <header class="nxp-ec-variant-options__header">
                    <h5 class="nxp-ec-variant-options__title">
                        {{ __("COM_NXPEASYCART_FIELD_VARIANT_OPTIONS", "Options") }}
                    </h5>
                    <button
                        type="button"
                        class="nxp-ec-btn nxp-ec-btn--link"
                        @click="$emit('add-option', index)"
                    >
                        {{ __("COM_NXPEASYCART_FIELD_VARIANT_OPTION_ADD", "Add option") }}
                    </button>
                </header>

                <p class="nxp-ec-form-help">
                    {{ __("COM_NXPEASYCART_FIELD_VARIANT_OPTIONS_HELP", "Add optional key/value pairs such as Size or Colour.") }}
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
                        :placeholder="__('COM_NXPEASYCART_FIELD_VARIANT_OPTION_NAME', 'Name (e.g. Size)')"
                    />
                    <input
                        :id="`variant-${index}-option-value-${optionIndex}`"
                        class="nxp-ec-form-input"
                        type="text"
                        v-model.trim="option.value"
                        :placeholder="__('COM_NXPEASYCART_FIELD_VARIANT_OPTION_VALUE', 'Value (e.g. Large)')"
                    />
                    <button
                        type="button"
                        class="nxp-ec-btn nxp-ec-btn--link nxp-ec-btn--danger"
                        @click="$emit('remove-option', index, optionIndex)"
                        :aria-label="__('COM_NXPEASYCART_FIELD_VARIANT_OPTION_REMOVE', 'Remove option')"
                    >
                        {{ __("COM_NXPEASYCART_REMOVE", "Remove") }}
                    </button>
                </div>
            </section>

            <!-- Variant Images Section -->
            <section class="nxp-ec-variant-images">
                <header class="nxp-ec-variant-images__header">
                    <h5 class="nxp-ec-variant-images__title">
                        {{ __("COM_NXPEASYCART_FIELD_VARIANT_IMAGES", "Variant Images") }}
                    </h5>
                    <button
                        v-if="hasMediaModal"
                        type="button"
                        class="nxp-ec-btn nxp-ec-btn--link"
                        @click="$emit('open-variant-media-modal', index)"
                    >
                        {{ __("COM_NXPEASYCART_FIELD_VARIANT_IMAGE_ADD", "Add image") }}
                    </button>
                </header>

                <p class="nxp-ec-form-help">
                    {{ __("COM_NXPEASYCART_FIELD_VARIANT_IMAGES_HELP", "Add variant-specific images (e.g., different colours). Leave empty to use product images.") }}
                </p>

                <div
                    v-if="variant.images && variant.images.length > 0"
                    class="nxp-ec-variant-images__list"
                >
                    <div
                        v-for="(img, imgIndex) in variant.images"
                        :key="`variant-${index}-img-${imgIndex}`"
                        class="nxp-ec-variant-image"
                    >
                        <img
                            :src="resolveImageUrl(img)"
                            :alt="__('COM_NXPEASYCART_VARIANT_IMAGE', 'Variant image') + ' ' + (imgIndex + 1)"
                            class="nxp-ec-variant-image__thumb"
                        />
                        <button
                            type="button"
                            class="nxp-ec-variant-image__remove"
                            @click="$emit('remove-variant-image', index, imgIndex)"
                            :aria-label="__('COM_NXPEASYCART_REMOVE', 'Remove')"
                        >
                            <i class="fa-solid fa-times"></i>
                        </button>
                    </div>
                </div>

                <p
                    v-else
                    class="nxp-ec-variant-images__empty"
                >
                    {{ __("COM_NXPEASYCART_FIELD_VARIANT_IMAGES_EMPTY", "No variant images (using product images)") }}
                </p>
            </section>
        </article>
    </section>
</template>

<script setup>
/**
 * VariantsTab - Product variant management.
 *
 * Handles the variant matrix including SKU, pricing, stock,
 * options, and variant-specific images.
 *
 * @since 0.3.2
 */

const props = defineProps({
    /**
     * Array of variant objects.
     */
    variants: {
        type: Array,
        default: () => [],
    },
    /**
     * Base currency code.
     */
    baseCurrency: {
        type: String,
        default: "USD",
    },
    /**
     * Whether the media modal is available.
     */
    hasMediaModal: {
        type: Boolean,
        default: false,
    },
    /**
     * Function to resolve image URLs.
     */
    resolveImageUrl: {
        type: Function,
        default: (url) => url,
    },
    /**
     * Translation function from parent.
     */
    translate: {
        type: Function,
        required: true,
    },
});

defineEmits([
    "add-variant",
    "remove-variant",
    "duplicate-variant",
    "add-option",
    "remove-option",
    "format-price",
    "format-sale-price",
    "open-variant-media-modal",
    "remove-variant-image",
]);

const __ = props.translate;

/**
 * Generate a unique key for each variant.
 */
const variantKey = (variant, index) => `${variant.id || "new"}-${index}`;
</script>
