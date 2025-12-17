<template>
    <div class="nxp-ec-settings-panel">
        <header class="nxp-ec-settings-panel__header">
            <h3>
                {{
                    __(
                        "COM_NXPEASYCART_SETTINGS_VISUAL_TITLE",
                        "Storefront colors"
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
                        "COM_NXPEASYCART_SETTINGS_VISUAL_REFRESH",
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
                    "COM_NXPEASYCART_SETTINGS_VISUAL_LOADING",
                    "Loading visual settings..."
                )
            }}
        </div>

        <form
            v-else
            class="nxp-ec-settings-form nxp-ec-settings-form--visual"
            @submit.prevent="$emit('save')"
        >
            <p class="nxp-ec-form-help">
                {{
                    __(
                        "COM_NXPEASYCART_SETTINGS_VISUAL_HINT",
                        "Override your template's colors. Empty fields will use your current template defaults shown in the color pickers."
                    )
                }}
            </p>

            <p class="nxp-ec-form-help nxp-ec-form-help--muted">
                {{
                    __(
                        "COM_NXPEASYCART_SETTINGS_VISUAL_ADAPTER_NOTE",
                        "The component will attempt to adapt to your current template's styling automatically. If no adapter is available for your template, these fallback colors will be used instead."
                    )
                }}
            </p>

            <div class="nxp-ec-visual-grid">
                <!-- Primary Color -->
                <div class="nxp-ec-visual-field">
                    <label class="nxp-ec-form-label" for="visual-primary-color">
                        {{
                            __(
                                "COM_NXPEASYCART_VISUAL_PRIMARY",
                                "Primary color"
                            )
                        }}
                    </label>
                    <div class="nxp-ec-color-input-group">
                        <input
                            id="visual-primary-color"
                            class="nxp-ec-color-picker"
                            type="color"
                            :value="draft.primaryColor || templateDefaults.primary"
                            @input="draft.primaryColor = $event.target.value"
                        />
                        <input
                            class="nxp-ec-form-input"
                            type="text"
                            v-model.trim="draft.primaryColor"
                            :placeholder="templateDefaults.primary"
                            maxlength="7"
                        />
                        <button
                            type="button"
                            class="nxp-ec-btn nxp-ec-btn--link"
                            @click="draft.primaryColor = ''"
                        >
                            {{
                                __(
                                    "COM_NXPEASYCART_VISUAL_RESET",
                                    "Reset"
                                )
                            }}
                        </button>
                    </div>
                </div>

                <!-- Text Color -->
                <div class="nxp-ec-visual-field">
                    <label class="nxp-ec-form-label" for="visual-text-color">
                        {{
                            __(
                                "COM_NXPEASYCART_VISUAL_TEXT",
                                "Text color"
                            )
                        }}
                    </label>
                    <div class="nxp-ec-color-input-group">
                        <input
                            id="visual-text-color"
                            class="nxp-ec-color-picker"
                            type="color"
                            :value="draft.textColor || templateDefaults.text"
                            @input="draft.textColor = $event.target.value"
                        />
                        <input
                            class="nxp-ec-form-input"
                            type="text"
                            v-model.trim="draft.textColor"
                            :placeholder="templateDefaults.text"
                            maxlength="7"
                        />
                        <button
                            type="button"
                            class="nxp-ec-btn nxp-ec-btn--link"
                            @click="draft.textColor = ''"
                        >
                            {{ __("COM_NXPEASYCART_VISUAL_RESET", "Reset") }}
                        </button>
                    </div>
                </div>

                <!-- Surface/Background Color -->
                <div class="nxp-ec-visual-field">
                    <label class="nxp-ec-form-label" for="visual-surface-color">
                        {{
                            __(
                                "COM_NXPEASYCART_VISUAL_SURFACE",
                                "Background color"
                            )
                        }}
                    </label>
                    <div class="nxp-ec-color-input-group">
                        <input
                            id="visual-surface-color"
                            class="nxp-ec-color-picker"
                            type="color"
                            :value="draft.surfaceColor || templateDefaults.surface"
                            @input="draft.surfaceColor = $event.target.value"
                        />
                        <input
                            class="nxp-ec-form-input"
                            type="text"
                            v-model.trim="draft.surfaceColor"
                            :placeholder="templateDefaults.surface"
                            maxlength="7"
                        />
                        <button
                            type="button"
                            class="nxp-ec-btn nxp-ec-btn--link"
                            @click="draft.surfaceColor = ''"
                        >
                            {{ __("COM_NXPEASYCART_VISUAL_RESET", "Reset") }}
                        </button>
                    </div>
                </div>

                <!-- Border Color -->
                <div class="nxp-ec-visual-field">
                    <label class="nxp-ec-form-label" for="visual-border-color">
                        {{
                            __(
                                "COM_NXPEASYCART_VISUAL_BORDER",
                                "Border color"
                            )
                        }}
                    </label>
                    <div class="nxp-ec-color-input-group">
                        <input
                            id="visual-border-color"
                            class="nxp-ec-color-picker"
                            type="color"
                            :value="draft.borderColor || templateDefaults.border"
                            @input="draft.borderColor = $event.target.value"
                        />
                        <input
                            class="nxp-ec-form-input"
                            type="text"
                            v-model.trim="draft.borderColor"
                            :placeholder="templateDefaults.border"
                            maxlength="7"
                        />
                        <button
                            type="button"
                            class="nxp-ec-btn nxp-ec-btn--link"
                            @click="draft.borderColor = ''"
                        >
                            {{ __("COM_NXPEASYCART_VISUAL_RESET", "Reset") }}
                        </button>
                    </div>
                </div>

                <!-- Muted Text Color -->
                <div class="nxp-ec-visual-field">
                    <label class="nxp-ec-form-label" for="visual-muted-color">
                        {{
                            __(
                                "COM_NXPEASYCART_VISUAL_MUTED",
                                "Muted text color"
                            )
                        }}
                    </label>
                    <div class="nxp-ec-color-input-group">
                        <input
                            id="visual-muted-color"
                            class="nxp-ec-color-picker"
                            type="color"
                            :value="draft.mutedColor || templateDefaults.muted"
                            @input="draft.mutedColor = $event.target.value"
                        />
                        <input
                            class="nxp-ec-form-input"
                            type="text"
                            v-model.trim="draft.mutedColor"
                            :placeholder="templateDefaults.muted"
                            maxlength="7"
                        />
                        <button
                            type="button"
                            class="nxp-ec-btn nxp-ec-btn--link"
                            @click="draft.mutedColor = ''"
                        >
                            {{ __("COM_NXPEASYCART_VISUAL_RESET", "Reset") }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Preview Section -->
            <div class="nxp-ec-visual-preview">
                <h4>
                    {{
                        __(
                            "COM_NXPEASYCART_VISUAL_PREVIEW",
                            "Preview"
                        )
                    }}
                </h4>
                <div class="nxp-ec-preview-box" :style="previewStyles">
                    <button type="button" class="nxp-ec-preview-btn">
                        {{ __("COM_NXPEASYCART_VISUAL_PREVIEW_BUTTON", "Sample Button") }}
                    </button>
                    <p class="nxp-ec-preview-text">
                        {{ __("COM_NXPEASYCART_VISUAL_PREVIEW_TEXT", "Sample text in your chosen colors") }}
                    </p>
                    <p class="nxp-ec-preview-muted">
                        {{ __("COM_NXPEASYCART_VISUAL_PREVIEW_MUTED", "Muted text example") }}
                    </p>
                </div>
            </div>

            <div
                v-if="message"
                class="nxp-ec-admin-alert nxp-ec-admin-alert--success"
            >
                {{ message }}
            </div>

            <div class="nxp-ec-settings-actions">
                <button
                    class="nxp-ec-btn"
                    type="button"
                    @click="$emit('reset')"
                    :disabled="saving"
                >
                    {{
                        __(
                            "COM_NXPEASYCART_SETTINGS_VISUAL_CANCEL",
                            "Cancel"
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
                            : __(
                                  "COM_NXPEASYCART_SETTINGS_VISUAL_SAVE",
                                  "Save colors"
                              )
                    }}
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { computed } from "vue";

/**
 * VisualTab - Storefront color customization.
 *
 * Allows administrators to override template colors for the storefront.
 * Includes live preview of selected colors.
 *
 * @since 0.3.2
 */

const props = defineProps({
    /**
     * Draft object containing visual color settings.
     * Structure: { primaryColor, textColor, surfaceColor, borderColor, mutedColor }
     */
    draft: {
        type: Object,
        required: true,
    },
    /**
     * Template default colors from current Joomla template.
     * Structure: { primary, text, surface, border, muted }
     */
    templateDefaults: {
        type: Object,
        default: () => ({
            primary: "#4f6d7a",
            text: "#1f2933",
            surface: "#ffffff",
            border: "#e4e7ec",
            muted: "#6b7280",
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
    /**
     * Success message.
     */
    message: {
        type: String,
        default: "",
    },
});

defineEmits(["refresh", "save", "reset"]);

const __ = props.translate;

/**
 * Computed CSS custom properties for the live preview.
 */
const previewStyles = computed(() => ({
    "--nxp-ec-color-primary": props.draft.primaryColor || props.templateDefaults.primary,
    "--nxp-ec-color-text": props.draft.textColor || props.templateDefaults.text,
    "--nxp-ec-color-surface": props.draft.surfaceColor || props.templateDefaults.surface,
    "--nxp-ec-color-border": props.draft.borderColor || props.templateDefaults.border,
    "--nxp-ec-color-muted": props.draft.mutedColor || props.templateDefaults.muted,
}));
</script>
