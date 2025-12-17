<template>
    <div class="nxp-ec-settings-panel">
        <header class="nxp-ec-settings-panel__header">
            <h3>
                {{
                    __(
                        "COM_NXPEASYCART_SETTINGS_PAYMENTS_TITLE",
                        "Payment gateways"
                    )
                }}
            </h3>
            <div class="nxp-ec-settings-actions">
                <button
                    class="nxp-ec-btn"
                    type="button"
                    @click="$emit('refresh')"
                    :disabled="loading"
                >
                    {{
                        __(
                            "COM_NXPEASYCART_SETTINGS_PAYMENTS_REFRESH",
                            "Refresh"
                        )
                    }}
                </button>
            </div>
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
                    "COM_NXPEASYCART_SETTINGS_PAYMENTS_LOADING",
                    "Loading payment configuration..."
                )
            }}
        </div>

        <form
            v-else
            class="nxp-ec-settings-form nxp-ec-settings-form--payments"
            @submit.prevent="$emit('save')"
        >
            <!-- Stripe Section -->
            <fieldset>
                <legend class="nxp-ec-fieldset-legend-with-help">
                    <span>
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_PAYMENTS_STRIPE",
                                "Stripe"
                            )
                        }}
                    </span>
                    <a
                        href="https://docs.stripe.com/keys"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="nxp-ec-btn nxp-ec-btn--sm nxp-ec-help-link"
                        :title="__('COM_NXPEASYCART_SETTINGS_PAYMENTS_STRIPE_SETUP_GUIDE', 'View Stripe setup documentation')"
                    >
                        {{ __("COM_NXPEASYCART_SETTINGS_SETUP_GUIDE", "Setup Guide") }}
                    </a>
                </legend>
                <div class="nxp-ec-form-field">
                    <label
                        class="nxp-ec-form-label"
                        for="nxp-ec-stripe-publishable"
                    >
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_PAYMENTS_STRIPE_PUBLISHABLE",
                                "Publishable key"
                            )
                        }}
                        <a
                            href="https://dashboard.stripe.com/apikeys"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="nxp-ec-field-help-link"
                            :title="__('COM_NXPEASYCART_SETTINGS_GET_STRIPE_KEYS', 'Get your API keys from Stripe Dashboard')"
                        >
                            [link]
                        </a>
                    </label>
                    <input
                        id="nxp-ec-stripe-publishable"
                        class="nxp-ec-form-input"
                        type="text"
                        v-model.trim="draft.stripe.publishable_key"
                        :placeholder="__('COM_NXPEASYCART_SETTINGS_STRIPE_PK_PLACEHOLDER', 'pk_test_... or pk_live_...')"
                    />
                    <p class="nxp-ec-form-help">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_STRIPE_PK_HELP",
                                "Find in Stripe Dashboard -> Developers -> API Keys. Starts with pk_test_ (test) or pk_live_ (live)."
                            )
                        }}
                    </p>
                </div>

                <div class="nxp-ec-form-field">
                    <label class="nxp-ec-form-label" for="nxp-ec-stripe-secret">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_PAYMENTS_STRIPE_SECRET",
                                "Secret key"
                            )
                        }}
                        <a
                            href="https://dashboard.stripe.com/apikeys"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="nxp-ec-field-help-link"
                            :title="__('COM_NXPEASYCART_SETTINGS_GET_STRIPE_KEYS', 'Get your API keys from Stripe Dashboard')"
                        >
                            [link]
                        </a>
                    </label>
                    <input
                        id="nxp-ec-stripe-secret"
                        class="nxp-ec-form-input"
                        type="password"
                        v-model.trim="draft.stripe.secret_key"
                        autocomplete="off"
                        :placeholder="__('COM_NXPEASYCART_SETTINGS_STRIPE_SK_PLACEHOLDER', 'sk_test_... or sk_live_...')"
                    />
                    <p class="nxp-ec-form-help">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_STRIPE_SK_HELP",
                                "Find in Stripe Dashboard -> Developers -> API Keys. Click 'Reveal' to view. Starts with sk_test_ (test) or sk_live_ (live)."
                            )
                        }}
                    </p>
                </div>

                <div class="nxp-ec-form-field">
                    <label class="nxp-ec-form-label" for="nxp-ec-stripe-webhook">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_PAYMENTS_STRIPE_WEBHOOK",
                                "Webhook secret"
                            )
                        }}
                        <a
                            href="https://dashboard.stripe.com/webhooks"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="nxp-ec-field-help-link"
                            :title="__('COM_NXPEASYCART_SETTINGS_CREATE_STRIPE_WEBHOOK', 'Create webhook in Stripe Dashboard')"
                        >
                            [link]
                        </a>
                    </label>
                    <input
                        id="nxp-ec-stripe-webhook"
                        class="nxp-ec-form-input"
                        type="password"
                        v-model.trim="draft.stripe.webhook_secret"
                        autocomplete="off"
                        :placeholder="__('COM_NXPEASYCART_SETTINGS_STRIPE_WH_PLACEHOLDER', 'whsec_...')"
                    />
                    <p class="nxp-ec-form-help">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_PAYMENTS_STRIPE_HELP",
                                "Create webhook in Stripe Dashboard -> Developers -> Webhooks. Your webhook URL:"
                            )
                        }}
                        <code style="display: block; margin-top: 0.25rem; word-break: break-all;">{{ siteUrl }}/index.php?option=com_nxpeasycart&task=webhook.stripe</code>
                    </p>
                </div>

                <div class="nxp-ec-form-field">
                    <label class="nxp-ec-form-label" for="nxp-ec-stripe-mode">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_PAYMENTS_MODE",
                                "Mode"
                            )
                        }}
                    </label>
                    <select
                        id="nxp-ec-stripe-mode"
                        class="nxp-ec-form-input"
                        v-model="draft.stripe.mode"
                    >
                        <option value="test">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_PAYMENTS_MODE_TEST",
                                    "Test"
                                )
                            }}
                        </option>
                        <option value="live">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_PAYMENTS_MODE_LIVE",
                                    "Live"
                                )
                            }}
                        </option>
                    </select>
                </div>
            </fieldset>

            <!-- PayPal Section -->
            <fieldset>
                <legend class="nxp-ec-fieldset-legend-with-help">
                    <span>
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_PAYMENTS_PAYPAL",
                                "PayPal"
                            )
                        }}
                    </span>
                    <a
                        href="https://developer.paypal.com/api/rest/"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="nxp-ec-btn nxp-ec-btn--sm nxp-ec-help-link"
                        :title="__('COM_NXPEASYCART_SETTINGS_PAYMENTS_PAYPAL_SETUP_GUIDE', 'View PayPal setup documentation')"
                    >
                        {{ __("COM_NXPEASYCART_SETTINGS_SETUP_GUIDE", "Setup Guide") }}
                    </a>
                </legend>
                <div class="nxp-ec-form-field">
                    <label
                        class="nxp-ec-form-label"
                        for="nxp-ec-paypal-client-id"
                    >
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_PAYMENTS_PAYPAL_CLIENT_ID",
                                "Client ID"
                            )
                        }}
                        <a
                            href="https://developer.paypal.com/dashboard/applications/sandbox"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="nxp-ec-field-help-link"
                            :title="__('COM_NXPEASYCART_SETTINGS_GET_PAYPAL_CREDS', 'Get credentials from PayPal Developer Dashboard')"
                        >
                            [link]
                        </a>
                    </label>
                    <input
                        id="nxp-ec-paypal-client-id"
                        class="nxp-ec-form-input"
                        type="text"
                        v-model.trim="draft.paypal.client_id"
                    />
                    <p class="nxp-ec-form-help">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_PAYPAL_CLIENT_ID_HELP",
                                "Find in PayPal Developer Dashboard -> Apps & Credentials -> Your App. Long alphanumeric string."
                            )
                        }}
                    </p>
                </div>

                <div class="nxp-ec-form-field">
                    <label
                        class="nxp-ec-form-label"
                        for="nxp-ec-paypal-client-secret"
                    >
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_PAYMENTS_PAYPAL_CLIENT_SECRET",
                                "Client secret"
                            )
                        }}
                        <a
                            href="https://developer.paypal.com/dashboard/applications/sandbox"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="nxp-ec-field-help-link"
                            :title="__('COM_NXPEASYCART_SETTINGS_GET_PAYPAL_CREDS', 'Get credentials from PayPal Developer Dashboard')"
                        >
                            [link]
                        </a>
                    </label>
                    <input
                        id="nxp-ec-paypal-client-secret"
                        class="nxp-ec-form-input"
                        type="password"
                        v-model.trim="draft.paypal.client_secret"
                        autocomplete="off"
                    />
                    <p class="nxp-ec-form-help">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_PAYPAL_CLIENT_SECRET_HELP",
                                "Find in PayPal Developer Dashboard -> Apps & Credentials -> Your App. Click 'Show' to reveal."
                            )
                        }}
                    </p>
                </div>

                <div class="nxp-ec-form-field">
                    <label
                        class="nxp-ec-form-label"
                        for="nxp-ec-paypal-webhook-id"
                    >
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_PAYMENTS_PAYPAL_WEBHOOK",
                                "Webhook ID"
                            )
                        }}
                        <a
                            href="https://developer.paypal.com/api/rest/webhooks/"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="nxp-ec-field-help-link"
                            :title="__('COM_NXPEASYCART_SETTINGS_CREATE_PAYPAL_WEBHOOK', 'Learn about PayPal webhooks')"
                        >
                            [link]
                        </a>
                    </label>
                    <input
                        id="nxp-ec-paypal-webhook-id"
                        class="nxp-ec-form-input"
                        type="text"
                        v-model.trim="draft.paypal.webhook_id"
                        :placeholder="__('COM_NXPEASYCART_SETTINGS_PAYPAL_WH_PLACEHOLDER', 'WH-...')"
                    />
                    <p class="nxp-ec-form-help">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_PAYMENTS_PAYPAL_HELP",
                                "Create webhook in your PayPal app -> Webhooks section. Required for signature verification. Your webhook URL:"
                            )
                        }}
                        <code style="display: block; margin-top: 0.25rem; word-break: break-all;">{{ siteUrl }}/index.php?option=com_nxpeasycart&task=webhook.paypal</code>
                    </p>
                </div>

                <div class="nxp-ec-form-field">
                    <label class="nxp-ec-form-label" for="nxp-ec-paypal-mode">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_PAYMENTS_MODE",
                                "Mode"
                            )
                        }}
                    </label>
                    <select
                        id="nxp-ec-paypal-mode"
                        class="nxp-ec-form-input"
                        v-model="draft.paypal.mode"
                    >
                        <option value="sandbox">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_PAYMENTS_MODE_SANDBOX",
                                    "Sandbox"
                                )
                            }}
                        </option>
                        <option value="live">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_PAYMENTS_MODE_LIVE",
                                    "Live"
                                )
                            }}
                        </option>
                    </select>
                </div>
            </fieldset>

            <!-- Cash on Delivery Section -->
            <fieldset>
                <legend>
                    {{
                        __(
                            "COM_NXPEASYCART_SETTINGS_PAYMENTS_COD",
                            "Cash on delivery"
                        )
                    }}
                </legend>
                <div class="nxp-ec-form-field nxp-ec-form-field--inline">
                    <label class="nxp-ec-form-label" for="nxp-ec-cod-enabled">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_PAYMENTS_COD_ENABLED",
                                "Enable cash on delivery"
                            )
                        }}
                    </label>
                    <input
                        id="nxp-ec-cod-enabled"
                        type="checkbox"
                        v-model="draft.cod.enabled"
                    />
                </div>
                <div class="nxp-ec-form-field">
                    <label class="nxp-ec-form-label" for="nxp-ec-cod-label">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_PAYMENTS_COD_LABEL",
                                "Checkout label"
                            )
                        }}
                    </label>
                    <input
                        id="nxp-ec-cod-label"
                        class="nxp-ec-form-input"
                        type="text"
                        v-model.trim="draft.cod.label"
                        placeholder="Cash on delivery"
                    />
                </div>
            </fieldset>

            <!-- Bank Transfer Section -->
            <fieldset>
                <legend>
                    {{
                        __(
                            "COM_NXPEASYCART_SETTINGS_PAYMENTS_BANK_TRANSFER",
                            "Bank transfer"
                        )
                    }}
                </legend>
                <div class="nxp-ec-form-field nxp-ec-form-field--inline">
                    <label class="nxp-ec-form-label" for="nxp-ec-bank-transfer-enabled">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_PAYMENTS_BANK_TRANSFER_ENABLED",
                                "Enable bank transfer"
                            )
                        }}
                    </label>
                    <input
                        id="nxp-ec-bank-transfer-enabled"
                        type="checkbox"
                        v-model="draft.bank_transfer.enabled"
                    />
                </div>
                <div class="nxp-ec-form-field">
                    <label class="nxp-ec-form-label" for="nxp-ec-bank-transfer-label">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_PAYMENTS_BANK_TRANSFER_LABEL",
                                "Checkout label"
                            )
                        }}
                    </label>
                    <input
                        id="nxp-ec-bank-transfer-label"
                        class="nxp-ec-form-input"
                        type="text"
                        v-model.trim="draft.bank_transfer.label"
                        placeholder="Bank transfer"
                    />
                </div>
                <div class="nxp-ec-form-field">
                    <label class="nxp-ec-form-label" for="nxp-ec-bank-transfer-instructions">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_PAYMENTS_BANK_TRANSFER_INSTRUCTIONS",
                                "Payment instructions"
                            )
                        }}
                    </label>
                    <textarea
                        id="nxp-ec-bank-transfer-instructions"
                        class="nxp-ec-form-input"
                        rows="4"
                        v-model="draft.bank_transfer.instructions"
                        :placeholder="__(
                            'COM_NXPEASYCART_SETTINGS_PAYMENTS_BANK_TRANSFER_INSTRUCTIONS_PLACEHOLDER',
                            'Share how to complete the transfer and include the order number reference.'
                        )"
                    ></textarea>
                    <p class="nxp-ec-form-help">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_PAYMENTS_BANK_TRANSFER_INSTRUCTIONS_HELP",
                                "Customers will see this in the checkout email alongside their invoice."
                            )
                        }}
                    </p>
                </div>
                <div class="nxp-ec-form-grid">
                    <div class="nxp-ec-form-field">
                        <label class="nxp-ec-form-label" for="nxp-ec-bank-transfer-account-name">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_PAYMENTS_BANK_TRANSFER_ACCOUNT_NAME",
                                    "Account name"
                                )
                            }}
                        </label>
                        <input
                            id="nxp-ec-bank-transfer-account-name"
                            class="nxp-ec-form-input"
                            type="text"
                            v-model.trim="draft.bank_transfer.account_name"
                        />
                    </div>
                    <div class="nxp-ec-form-field">
                        <label class="nxp-ec-form-label" for="nxp-ec-bank-transfer-iban">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_PAYMENTS_BANK_TRANSFER_IBAN",
                                    "IBAN"
                                )
                            }}
                        </label>
                        <input
                            id="nxp-ec-bank-transfer-iban"
                            class="nxp-ec-form-input"
                            type="text"
                            v-model.trim="draft.bank_transfer.iban"
                            maxlength="34"
                        />
                    </div>
                    <div class="nxp-ec-form-field">
                        <label class="nxp-ec-form-label" for="nxp-ec-bank-transfer-bic">
                            {{
                                __(
                                    "COM_NXPEASYCART_SETTINGS_PAYMENTS_BANK_TRANSFER_BIC",
                                    "BIC/SWIFT"
                                )
                            }}
                        </label>
                        <input
                            id="nxp-ec-bank-transfer-bic"
                            class="nxp-ec-form-input"
                            type="text"
                            v-model.trim="draft.bank_transfer.bic"
                            maxlength="11"
                        />
                    </div>
                </div>
            </fieldset>

            <!-- Status Section -->
            <fieldset>
                <legend>
                    {{
                        __(
                            "COM_NXPEASYCART_SETTINGS_PAYMENTS_STATUS",
                            "Status"
                        )
                    }}
                </legend>
                <div class="nxp-ec-form-field nxp-ec-form-field--inline">
                    <label
                        class="nxp-ec-form-label"
                        for="settings-payments-configured"
                    >
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_GENERAL_PAYMENTS_CONFIGURED",
                                "Payments configured"
                            )
                        }}
                    </label>
                    <input
                        id="settings-payments-configured"
                        class="nxp-ec-form-checkbox"
                        type="checkbox"
                        v-model="settingsDraft.paymentsConfigured"
                    />
                    <p class="nxp-ec-form-help">
                        {{
                            __(
                                "COM_NXPEASYCART_SETTINGS_GENERAL_PAYMENTS_HELP",
                                "Track when core payment settings are complete (used by dashboard checklist)."
                            )
                        }}
                    </p>
                </div>
            </fieldset>

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
                                  "COM_NXPEASYCART_SETTINGS_PAYMENTS_SAVE",
                                  "Save payments"
                              )
                    }}
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
/**
 * PaymentsTab - Payment gateway configuration.
 *
 * Handles Stripe, PayPal, Cash on Delivery, and Bank Transfer settings.
 * Uses both payments draft (gateway configs) and settings draft (paymentsConfigured flag).
 *
 * @since 0.3.2
 */

const props = defineProps({
    /**
     * Draft object containing payment gateway configurations.
     * Structure: { stripe: {...}, paypal: {...}, cod: {...}, bank_transfer: {...} }
     */
    draft: {
        type: Object,
        required: true,
    },
    /**
     * Settings draft for the paymentsConfigured flag.
     */
    settingsDraft: {
        type: Object,
        required: true,
    },
    /**
     * Site URL for webhook endpoint display.
     */
    siteUrl: {
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
