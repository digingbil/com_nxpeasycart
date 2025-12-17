<template>
    <div class="nxp-ec-settings-panel">
        <header class="nxp-ec-settings-panel__header">
            <h3>
                {{
                    __(
                        "COM_NXPEASYCART_SETTINGS_SECURITY_TITLE",
                        "Security & anti-spam"
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
                        "COM_NXPEASYCART_COUPONS_REFRESH",
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

        <form
            v-else
            class="nxp-ec-settings-form nxp-ec-settings-form--security"
            @submit.prevent="$emit('save')"
        >
            <p class="nxp-ec-form-help">
                {{
                    __(
                        "COM_NXPEASYCART_SETTINGS_SECURITY_LEAD",
                        "Tighten checkout and offline payment limits to reduce bot spam. Set a value to 0 to disable a limit."
                    )
                }}
            </p>

            <!-- Time Windows -->
            <div class="nxp-ec-form-grid">
                <div class="nxp-ec-form-field">
                    <label class="nxp-ec-form-label" for="settings-checkout-window">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_SECURITY_CHECKOUT_WINDOW",
                                "Checkout window (minutes)"
                            )
                        }}
                    </label>
                    <input
                        id="settings-checkout-window"
                        class="nxp-ec-form-input"
                        type="number"
                        min="0"
                        step="1"
                        v-model.number="draft.checkoutWindowMinutes"
                    />
                    <p class="nxp-ec-form-help">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_SECURITY_CHECKOUT_WINDOW_HELP",
                                "Applies to all gateways; counts reset after this window."
                            )
                        }}
                    </p>
                </div>
                <div class="nxp-ec-form-field">
                    <label class="nxp-ec-form-label" for="settings-offline-window">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_SECURITY_OFFLINE_WINDOW",
                                "Offline payments window (minutes)"
                            )
                        }}
                    </label>
                    <input
                        id="settings-offline-window"
                        class="nxp-ec-form-input"
                        type="number"
                        min="0"
                        step="1"
                        v-model.number="draft.offlineWindowMinutes"
                    />
                    <p class="nxp-ec-form-help">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_SECURITY_OFFLINE_WINDOW_HELP",
                                "Used for COD and bank transfer spam protection."
                            )
                        }}
                    </p>
                </div>
            </div>

            <!-- Checkout Rate Limits -->
            <div class="nxp-ec-form-grid">
                <div class="nxp-ec-form-field">
                    <label class="nxp-ec-form-label" for="settings-checkout-ip-limit">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_SECURITY_CHECKOUT_IP_LIMIT",
                                "Checkout attempts per IP"
                            )
                        }}
                    </label>
                    <input
                        id="settings-checkout-ip-limit"
                        class="nxp-ec-form-input"
                        type="number"
                        min="0"
                        step="1"
                        v-model.number="draft.checkoutIpLimit"
                    />
                </div>
                <div class="nxp-ec-form-field">
                    <label class="nxp-ec-form-label" for="settings-checkout-email-limit">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_SECURITY_CHECKOUT_EMAIL_LIMIT",
                                "Checkout attempts per email"
                            )
                        }}
                    </label>
                    <input
                        id="settings-checkout-email-limit"
                        class="nxp-ec-form-input"
                        type="number"
                        min="0"
                        step="1"
                        v-model.number="draft.checkoutEmailLimit"
                    />
                </div>
                <div class="nxp-ec-form-field">
                    <label class="nxp-ec-form-label" for="settings-checkout-session-limit">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_SECURITY_CHECKOUT_SESSION_LIMIT",
                                "Checkout attempts per session"
                            )
                        }}
                    </label>
                    <input
                        id="settings-checkout-session-limit"
                        class="nxp-ec-form-input"
                        type="number"
                        min="0"
                        step="1"
                        v-model.number="draft.checkoutSessionLimit"
                    />
                </div>
            </div>

            <!-- Offline Payment Rate Limits -->
            <div class="nxp-ec-form-grid">
                <div class="nxp-ec-form-field">
                    <label class="nxp-ec-form-label" for="settings-offline-ip-limit">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_SECURITY_OFFLINE_IP_LIMIT",
                                "Offline attempts per IP"
                            )
                        }}
                    </label>
                    <input
                        id="settings-offline-ip-limit"
                        class="nxp-ec-form-input"
                        type="number"
                        min="0"
                        step="1"
                        v-model.number="draft.offlineIpLimit"
                    />
                </div>
                <div class="nxp-ec-form-field">
                    <label class="nxp-ec-form-label" for="settings-offline-email-limit">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_SECURITY_OFFLINE_EMAIL_LIMIT",
                                "Offline attempts per email"
                            )
                        }}
                    </label>
                    <input
                        id="settings-offline-email-limit"
                        class="nxp-ec-form-input"
                        type="number"
                        min="0"
                        step="1"
                        v-model.number="draft.offlineEmailLimit"
                    />
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
                            "COM_NXPEASYCART_SETTINGS_GENERAL_CANCEL",
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
                                  "COM_NXPEASYCART_SETTINGS_SECURITY_SAVE",
                                  "Save security"
                              )
                    }}
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
/**
 * SecurityTab - Rate limiting and anti-spam settings.
 *
 * Configures checkout and offline payment rate limits to prevent
 * bot abuse and spam orders.
 *
 * @since 0.3.2
 */

const props = defineProps({
    /**
     * Draft object containing security rate limit settings.
     * Structure: {
     *   checkoutWindowMinutes, checkoutIpLimit, checkoutEmailLimit, checkoutSessionLimit,
     *   offlineWindowMinutes, offlineIpLimit, offlineEmailLimit
     * }
     */
    draft: {
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
</script>
