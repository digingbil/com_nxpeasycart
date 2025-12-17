<template>
    <div class="nxp-ec-settings-panel">
        <header class="nxp-ec-settings-panel__header">
            <h3>
                {{
                    __(
                        "COM_NXPEASYCART_SETTINGS_DIGITAL_TITLE",
                        "Digital products"
                    )
                }}
            </h3>
            <button
                class="nxp-ec-btn"
                type="button"
                @click="$emit('refresh')"
                :disabled="loading"
            >
                {{
                    __(
                        "COM_NXPEASYCART_SETTINGS_GENERAL_REFRESH",
                        "Refresh"
                    )
                }}
            </button>
        </header>

        <div
            v-if="error"
            class="nxp-ec-admin-alert nxp-ec-admin-alert--error"
        >
            {{ error }}
        </div>

        <div
            v-else-if="loading"
            class="nxp-ec-admin-panel__loading"
        >
            {{
                __(
                    "COM_NXPEASYCART_SETTINGS_GENERAL_LOADING",
                    "Loading settings..."
                )
            }}
        </div>

        <form
            v-else
            class="nxp-ec-settings-form"
            @submit.prevent="$emit('save')"
        >
            <div class="nxp-ec-form-grid">
                <div class="nxp-ec-form-field">
                    <label class="nxp-ec-form-label" for="settings-digital-max">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_DIGITAL_MAX_DOWNLOADS",
                                "Default max downloads"
                            )
                        }}
                    </label>
                    <input
                        id="settings-digital-max"
                        class="nxp-ec-form-input"
                        type="number"
                        min="0"
                        step="1"
                        v-model.number="draft.digitalMaxDownloads"
                    />
                    <p class="nxp-ec-form-help">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_DIGITAL_MAX_DOWNLOADS_DESC",
                                "Maximum downloads allowed per purchase (0 for unlimited)."
                            )
                        }}
                    </p>
                </div>

                <div class="nxp-ec-form-field">
                    <label class="nxp-ec-form-label" for="settings-digital-expiry">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_DIGITAL_EXPIRY",
                                "Download link expiry (days)"
                            )
                        }}
                    </label>
                    <input
                        id="settings-digital-expiry"
                        class="nxp-ec-form-input"
                        type="number"
                        min="0"
                        step="1"
                        v-model.number="draft.digitalExpiryDays"
                    />
                    <p class="nxp-ec-form-help">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_DIGITAL_EXPIRY_DESC",
                                "Number of days before links expire (0 for no expiry)."
                            )
                        }}
                    </p>
                </div>

                <div class="nxp-ec-form-field nxp-ec-form-field--inline">
                    <label class="nxp-ec-form-label" for="settings-digital-fulfill">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_DIGITAL_AUTO_FULFILL",
                                "Auto-fulfill digital orders"
                            )
                        }}
                    </label>
                    <input
                        id="settings-digital-fulfill"
                        class="nxp-ec-form-checkbox"
                        type="checkbox"
                        v-model="draft.digitalAutoFulfill"
                    />
                    <p class="nxp-ec-form-help">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_DIGITAL_AUTO_FULFILL_DESC",
                                "Mark digital-only orders fulfilled after payment."
                            )
                        }}
                    </p>
                </div>

                <div class="nxp-ec-form-field">
                    <label class="nxp-ec-form-label" for="settings-digital-max-size">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_DIGITAL_MAX_FILE_SIZE",
                                "Max file size (MB)"
                            )
                        }}
                    </label>
                    <input
                        id="settings-digital-max-size"
                        class="nxp-ec-form-input"
                        type="number"
                        min="1"
                        max="2048"
                        step="1"
                        v-model.number="draft.digitalMaxFileSize"
                    />
                    <p class="nxp-ec-form-help">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_DIGITAL_MAX_FILE_SIZE_DESC",
                                "Maximum upload size per file in megabytes (1-2048 MB)."
                            )
                        }}
                    </p>
                </div>

                <div class="nxp-ec-form-field">
                    <label class="nxp-ec-form-label" for="settings-digital-storage">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_DIGITAL_STORAGE_PATH",
                                "Storage path"
                            )
                        }}
                    </label>
                    <input
                        id="settings-digital-storage"
                        class="nxp-ec-form-input"
                        type="text"
                        v-model.trim="draft.digitalStoragePath"
                        maxlength="250"
                    />
                    <p class="nxp-ec-form-help">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_DIGITAL_STORAGE_PATH_DESC",
                                "Protected directory for digital files. Adjust only if you know the server path."
                            )
                        }}
                    </p>
                </div>
            </div>

            <!-- Allowed File Types Section -->
            <div class="nxp-ec-form-section">
                <h4 class="nxp-ec-form-section__title">
                    {{
                        __(
                            "COM_NXPEASYCART_SETTINGS_DIGITAL_ALLOWED_TYPES",
                            "Allowed File Types"
                        )
                    }}
                </h4>
                <p class="nxp-ec-form-help" style="margin-bottom: 1rem;">
                    {{
                        __(
                            "COM_NXPEASYCART_SETTINGS_DIGITAL_ALLOWED_TYPES_DESC",
                            "Select which file types can be uploaded as digital products."
                        )
                    }}
                </p>

                <div class="nxp-ec-filetypes-actions">
                    <button
                        type="button"
                        class="nxp-ec-btn nxp-ec-btn--sm"
                        @click="selectAllFileTypes"
                    >
                        {{
                            __(
                                "COM_NXPEASYCART_SELECT_ALL",
                                "Select All"
                            )
                        }}
                    </button>
                    <button
                        type="button"
                        class="nxp-ec-btn nxp-ec-btn--sm nxp-ec-btn--link"
                        @click="selectNoFileTypes"
                    >
                        {{
                            __(
                                "COM_NXPEASYCART_SELECT_NONE",
                                "Select None"
                            )
                        }}
                    </button>
                </div>

                <div class="nxp-ec-filetypes-grid">
                    <div
                        v-for="(extensions, category) in fileTypeCategories"
                        :key="category"
                        class="nxp-ec-filetypes-category"
                    >
                        <h5 class="nxp-ec-filetypes-category__title">
                            {{ formatCategoryName(category) }}
                        </h5>
                        <div class="nxp-ec-filetypes-category__list">
                            <label
                                v-for="ext in extensions"
                                :key="ext"
                                class="nxp-ec-filetypes-item"
                            >
                                <input
                                    type="checkbox"
                                    :value="ext"
                                    v-model="draft.digitalAllowedExtensions"
                                    class="nxp-ec-filetypes-item__checkbox"
                                />
                                <span class="nxp-ec-filetypes-item__label">.{{ ext }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="nxp-ec-form-field" style="margin-top: 1.5rem;">
                    <label class="nxp-ec-form-label" for="settings-digital-custom-ext">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_DIGITAL_CUSTOM_EXTENSIONS",
                                "Custom Extensions"
                            )
                        }}
                    </label>
                    <input
                        id="settings-digital-custom-ext"
                        class="nxp-ec-form-input"
                        type="text"
                        v-model.trim="draft.digitalCustomExtensions"
                        placeholder="blend, psd, ai, sketch"
                    />
                    <p class="nxp-ec-form-help nxp-ec-form-help--warning">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_DIGITAL_CUSTOM_EXTENSIONS_DESC",
                                "Comma-separated list of additional extensions. These bypass MIME validation - use only for trusted uploads."
                            )
                        }}
                    </p>
                </div>
            </div>

            <div class="nxp-ec-settings-actions">
                <button
                    class="nxp-ec-btn nxp-ec-btn--link"
                    type="button"
                    @click="$emit('reset')"
                >
                    {{
                        __(
                            "COM_NXPEASYCART_SETTINGS_GENERAL_RESET",
                            "Reset"
                        )
                    }}
                </button>
                <button
                    class="nxp-ec-btn nxp-ec-btn--primary"
                    type="submit"
                    :disabled="saving"
                >
                    {{
                        saving
                            ? __("JPROCESSING_REQUEST", "Saving...")
                            : __("JSAVE", "Save")
                    }}
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
/**
 * DigitalTab - Digital product settings.
 *
 * Handles configuration for digital downloads: max downloads,
 * link expiry, storage path, and allowed file types.
 *
 * @since 0.3.2
 */

const props = defineProps({
    /**
     * Draft object containing digital settings values.
     */
    draft: {
        type: Object,
        required: true,
    },
    /**
     * File type categories for the checkbox grid.
     */
    fileTypeCategories: {
        type: Object,
        default: () => ({
            archives: ["zip", "rar", "7z", "tar", "gz", "tgz"],
            audio: ["mp3", "wav", "flac"],
            video: ["mp4", "webm", "mov", "avi", "mkv"],
            images: ["jpg", "jpeg", "png", "gif", "svg", "webp", "avif"],
            documents: ["pdf", "txt", "rtf", "doc", "docx", "xls", "xlsx", "ppt", "pptx", "odt", "ods", "odp", "csv"],
            ebooks: ["epub", "mobi"],
            installers: ["exe", "msi", "deb", "rpm", "dmg", "app", "pkg", "apk", "ipa"],
        }),
    },
    /**
     * Translation function from parent.
     */
    translate: {
        type: Function,
        required: true,
    },
    /**
     * Loading state.
     */
    loading: {
        type: Boolean,
        default: false,
    },
    /**
     * Saving state.
     */
    saving: {
        type: Boolean,
        default: false,
    },
    /**
     * Error message.
     */
    error: {
        type: String,
        default: "",
    },
});

defineEmits(["refresh", "save", "reset"]);

const __ = props.translate;

// Format category name for display
const formatCategoryName = (category) => {
    const names = {
        archives: "Archives",
        audio: "Audio",
        video: "Video",
        images: "Images",
        documents: "Documents",
        ebooks: "E-books",
        installers: "Installers",
    };
    return names[category] ?? category.charAt(0).toUpperCase() + category.slice(1);
};

// Get all predefined extensions as a flat array
const getAllPredefinedExtensions = () => {
    return Object.values(props.fileTypeCategories).flat();
};

// Select all file types
const selectAllFileTypes = () => {
    props.draft.digitalAllowedExtensions = getAllPredefinedExtensions();
};

// Deselect all file types
const selectNoFileTypes = () => {
    props.draft.digitalAllowedExtensions = [];
};
</script>
