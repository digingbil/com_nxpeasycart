<template>
    <section class="nxp-ec-editor-panel nxp-ec-editor-panel--digital">
        <div class="nxp-ec-admin-alert nxp-ec-admin-alert--info">
            <p style="margin-bottom: 0;">
                {{
                    __(
                        "COM_NXPEASYCART_CHECKOUT_DIGITAL_NOTE",
                        "Digital items skip shipping and deliver download links."
                    )
                }}
            </p>
        </div>

        <div
            v-if="error"
            class="nxp-ec-admin-alert nxp-ec-admin-alert--error"
        >
            {{ error }}
        </div>

        <div
            v-if="!productId"
            class="nxp-ec-admin-callout nxp-ec-admin-callout--info"
        >
            <span class="nxp-ec-admin-callout__icon">
                <i class="fa-solid fa-circle-info"></i>
            </span>
            <div class="nxp-ec-admin-callout__content">
                <div class="nxp-ec-admin-callout__title">
                    {{
                        __(
                            "COM_NXPEASYCART_DIGITAL_FILES_SAVE_FIRST_TITLE",
                            "Save Required"
                        )
                    }}
                </div>
                {{
                    __(
                        "COM_NXPEASYCART_DIGITAL_FILES_SAVE_FIRST",
                        "Digital files are linked to the product record. Save this product first, then reopen the editor to upload your files."
                    )
                }}
            </div>
        </div>

        <div class="nxp-ec-digital-grid">
            <!-- Upload Section -->
            <div class="nxp-ec-digital-card">
                <h4>
                    {{
                        __(
                            "COM_NXPEASYCART_DIGITAL_FILES_UPLOAD",
                            "Upload file"
                        )
                    }}
                </h4>
                <div class="nxp-ec-form-field">
                    <label class="nxp-ec-form-label" for="digital-file-input">
                        {{
                            __(
                                "COM_NXPEASYCART_DIGITAL_FILES_SELECT",
                                "Choose file"
                            )
                        }}
                    </label>
                    <input
                        id="digital-file-input"
                        ref="fileInputRef"
                        type="file"
                        class="nxp-ec-form-input"
                        @change="handleFileChange"
                        :disabled="uploading || !productId"
                    />
                </div>
                <div class="nxp-ec-form-field">
                    <label class="nxp-ec-form-label" for="digital-version">
                        {{
                            __(
                                "COM_NXPEASYCART_DIGITAL_VERSION",
                                "Version"
                            )
                        }}
                    </label>
                    <input
                        id="digital-version"
                        class="nxp-ec-form-input"
                        type="text"
                        :value="version"
                        @input="$emit('update:version', $event.target.value)"
                        :disabled="uploading"
                    />
                </div>
                <div class="nxp-ec-form-field">
                    <label class="nxp-ec-form-label" for="digital-variant">
                        {{
                            __(
                                "COM_NXPEASYCART_DIGITAL_VARIANT_SCOPE",
                                "Applies to"
                            )
                        }}
                    </label>
                    <select
                        id="digital-variant"
                        class="nxp-ec-form-select"
                        :value="variantId"
                        @change="$emit('update:variantId', $event.target.value)"
                        :disabled="uploading"
                    >
                        <option value="">
                            {{
                                __(
                                    "COM_NXPEASYCART_DIGITAL_VARIANT_ALL",
                                    "All variants"
                                )
                            }}
                        </option>
                        <option
                            v-for="variant in variantOptions"
                            :key="variant.id"
                            :value="variant.id"
                        >
                            {{ variant.label }}
                        </option>
                    </select>
                    <p class="nxp-ec-form-help">
                        {{
                            __(
                                "COM_NXPEASYCART_DIGITAL_VARIANT_HELP",
                                "Scope downloads to a specific variant when needed."
                            )
                        }}
                    </p>
                </div>
                <div class="nxp-ec-admin-form__actions">
                    <button
                        type="button"
                        class="nxp-ec-btn nxp-ec-btn--primary"
                        @click="$emit('upload')"
                        :disabled="uploading || !productId"
                    >
                        <i
                            class="fa-solid fa-cloud-arrow-up"
                            :class="{ 'fa-spin': uploading }"
                            aria-hidden="true"
                        ></i>
                        {{
                            uploading
                                ? __("JPROCESSING_REQUEST", "Uploading...")
                                : __("COM_NXPEASYCART_DIGITAL_FILES_UPLOAD", "Upload file")
                        }}
                    </button>
                </div>
            </div>

            <!-- Files List -->
            <div class="nxp-ec-digital-card">
                <div class="nxp-ec-digital-card__header">
                    <h4>
                        {{
                            __(
                                "COM_NXPEASYCART_DIGITAL_FILES",
                                "Digital files"
                            )
                        }}
                    </h4>
                    <span v-if="loading">
                        {{
                            __(
                                "JGLOBAL_LOADING",
                                "Loading..."
                            )
                        }}
                    </span>
                </div>
                <div v-if="files.length === 0" class="nxp-ec-form-help">
                    {{
                        __(
                            "COM_NXPEASYCART_DIGITAL_FILES_EMPTY",
                            "No files attached to this product."
                        )
                    }}
                </div>
                <div v-else class="nxp-ec-digital-table">
                    <div
                        v-for="file in files"
                        :key="file.id"
                        class="nxp-ec-digital-row"
                    >
                        <div class="nxp-ec-digital-meta">
                            <strong>{{ file.filename }}</strong>
                            <div class="nxp-ec-admin-panel__muted">
                                {{ getVariantLabel(file.variant_id) }}
                                ·
                                {{ formatFileSize(file.file_size) }}
                                <template v-if="file.version">
                                    · v{{ file.version }}
                                </template>
                            </div>
                            <div
                                v-if="file.created"
                                class="nxp-ec-admin-panel__muted"
                            >
                                {{ file.created }}
                            </div>
                        </div>
                        <div class="nxp-ec-digital-actions">
                            <button
                                type="button"
                                class="nxp-ec-btn nxp-ec-btn--link nxp-ec-btn--danger"
                                :disabled="deletingId === file.id"
                                @click="$emit('delete', file)"
                            >
                                {{
                                    deletingId === file.id
                                        ? __("JPROCESSING_REQUEST", "Removing...")
                                        : __("COM_NXPEASYCART_REMOVE", "Remove")
                                }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { ref } from "vue";

/**
 * DigitalFilesTab - Digital file management.
 *
 * Handles uploading, listing, and deleting digital product files.
 *
 * @since 0.3.2
 */

const props = defineProps({
    /**
     * Product ID (null if not yet saved).
     */
    productId: {
        type: [Number, String],
        default: null,
    },
    /**
     * Array of digital files.
     */
    files: {
        type: Array,
        default: () => [],
    },
    /**
     * Available variant options.
     */
    variantOptions: {
        type: Array,
        default: () => [],
    },
    /**
     * Version string for upload.
     */
    version: {
        type: String,
        default: "1.0",
    },
    /**
     * Selected variant ID for upload.
     */
    variantId: {
        type: [String, Number],
        default: "",
    },
    /**
     * Loading state.
     */
    loading: {
        type: Boolean,
        default: false,
    },
    /**
     * Uploading state.
     */
    uploading: {
        type: Boolean,
        default: false,
    },
    /**
     * ID of file being deleted.
     */
    deletingId: {
        type: Number,
        default: 0,
    },
    /**
     * Error message.
     */
    error: {
        type: String,
        default: "",
    },
    /**
     * Translation function from parent.
     */
    translate: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits([
    "upload",
    "delete",
    "file-change",
    "update:version",
    "update:variantId",
]);

const __ = props.translate;
const fileInputRef = ref(null);

/**
 * Handle file input change.
 */
const handleFileChange = (event) => {
    const files = event?.target?.files;
    emit("file-change", files && files.length ? files[0] : null);
};

/**
 * Get display label for a variant ID.
 */
const getVariantLabel = (variantId) => {
    if (!variantId) {
        return __("COM_NXPEASYCART_DIGITAL_VARIANT_ALL", "All variants");
    }

    const match = props.variantOptions.find((v) => v.id === variantId);
    return match?.label || __("COM_NXPEASYCART_DIGITAL_VARIANT_SPECIFIC", "Variant");
};

/**
 * Format file size for display.
 */
const formatFileSize = (bytes) => {
    const size = Number(bytes) || 0;

    if (size <= 0) {
        return __("COM_NXPEASYCART_DIGITAL_FILE_SIZE_UNKNOWN", "Unknown size");
    }

    const units = ["B", "KB", "MB", "GB"];
    let index = 0;
    let value = size;

    while (value >= 1024 && index < units.length - 1) {
        value /= 1024;
        index += 1;
    }

    return `${value.toFixed(value >= 10 ? 0 : 1)} ${units[index]}`;
};

/**
 * Expose the file input ref for parent to reset.
 */
defineExpose({
    resetFileInput: () => {
        if (fileInputRef.value) {
            fileInputRef.value.value = "";
        }
    },
});
</script>
