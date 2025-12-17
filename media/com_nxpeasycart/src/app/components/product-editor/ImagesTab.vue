<template>
    <section class="nxp-ec-editor-panel">
        <div class="nxp-ec-form-field">
            <label class="nxp-ec-form-label" for="product-images">
                {{
                    __("COM_NXPEASYCART_FIELD_PRODUCT_IMAGES", "Images")
                }}
            </label>
            <div id="product-images" class="nxp-ec-image-list">
                <div
                    v-for="(image, index) in images"
                    :key="`product-image-${index}`"
                    class="nxp-ec-image-row"
                >
                    <input
                        :id="`product-image-${index}`"
                        class="nxp-ec-form-input"
                        type="text"
                        :value="image"
                        @input="$emit('update-image', index, $event.target.value)"
                        :placeholder="
                            __(
                                'COM_NXPEASYCART_FIELD_PRODUCT_IMAGES_PLACEHOLDER',
                                'https://example.com/image.jpg'
                            )
                        "
                    />
                    <div class="nxp-ec-image-row__actions">
                        <button
                            type="button"
                            class="nxp-ec-btn"
                            @click="$emit('open-media-modal', index)"
                        >
                            <i class="fa-solid fa-photo-film" aria-hidden="true"></i>
                            {{
                                __(
                                    "COM_NXPEASYCART_FIELD_PRODUCT_IMAGES_SELECT",
                                    "Select from media"
                                )
                            }}
                        </button>
                        <button
                            type="button"
                            class="nxp-ec-btn nxp-ec-btn--link"
                            @click="$emit('move-image', index, -1)"
                            :disabled="index === 0"
                        >
                            {{
                                __(
                                    "COM_NXPEASYCART_FIELD_PRODUCT_IMAGES_MOVE_UP",
                                    "Up"
                                )
                            }}
                        </button>
                        <button
                            type="button"
                            class="nxp-ec-btn nxp-ec-btn--link"
                            @click="$emit('move-image', index, 1)"
                            :disabled="index === images.length - 1"
                        >
                            {{
                                __(
                                    "COM_NXPEASYCART_FIELD_PRODUCT_IMAGES_MOVE_DOWN",
                                    "Down"
                                )
                            }}
                        </button>
                        <button
                            type="button"
                            class="nxp-ec-btn nxp-ec-btn--link nxp-ec-btn--danger"
                            @click="$emit('remove-image', index)"
                            :aria-label="
                                __(
                                    'COM_NXPEASYCART_FIELD_PRODUCT_IMAGES_REMOVE',
                                    'Remove image'
                                )
                            "
                        >
                            {{ __("COM_NXPEASYCART_REMOVE", "Remove") }}
                        </button>
                    </div>
                </div>
            </div>
            <button type="button" class="nxp-ec-btn" @click="$emit('add-image')">
                {{
                    __(
                        "COM_NXPEASYCART_FIELD_PRODUCT_IMAGES_ADD",
                        "Add image"
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
    </section>
</template>

<script setup>
/**
 * ImagesTab - Product image management.
 *
 * Displays a list of image URLs with controls for reordering,
 * adding, removing, and selecting from Joomla media manager.
 *
 * @since 0.3.2
 */

const props = defineProps({
    /**
     * Array of image URLs.
     */
    images: {
        type: Array,
        default: () => [],
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
    "add-image",
    "remove-image",
    "move-image",
    "update-image",
    "open-media-modal",
]);

const __ = props.translate;
</script>
