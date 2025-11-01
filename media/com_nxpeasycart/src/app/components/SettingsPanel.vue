<template>
  <section class="nxp-admin-panel nxp-admin-panel--settings">
    <header class="nxp-admin-panel__header">
      <div>
        <h2 class="nxp-admin-panel__title">
          {{ __('COM_NXPEASYCART_MENU_SETTINGS', 'Settings', [], 'settingsPanelTitle') }}
        </h2>
        <p class="nxp-admin-panel__lead">
          {{ __('COM_NXPEASYCART_SETTINGS_LEAD', 'Configure taxes, shipping, and store defaults.', [], 'settingsPanelLead') }}
        </p>
      </div>
    </header>

    <nav class="nxp-settings-tabs">
      <button
        type="button"
        class="nxp-settings-tab"
        :class="{ 'is-active': activeTab === 'general' }"
        @click="activeTab = 'general'"
      >
        {{ __('COM_NXPEASYCART_SETTINGS_TAB_GENERAL', 'General', [], 'settingsTabGeneral') }}
      </button>
      <button
        type="button"
        class="nxp-settings-tab"
        :class="{ 'is-active': activeTab === 'tax' }"
        @click="activeTab = 'tax'"
      >
        {{ __('COM_NXPEASYCART_SETTINGS_TAB_TAX', 'Tax rates', [], 'settingsTabTax') }}
      </button>
      <button
        type="button"
        class="nxp-settings-tab"
        :class="{ 'is-active': activeTab === 'shipping' }"
        @click="activeTab = 'shipping'"
      >
        {{ __('COM_NXPEASYCART_SETTINGS_TAB_SHIPPING', 'Shipping rules', [], 'settingsTabShipping') }}
      </button>
      <button
        type="button"
        class="nxp-settings-tab"
        :class="{ 'is-active': activeTab === 'payments' }"
        @click="activeTab = 'payments'"
      >
        {{ __('COM_NXPEASYCART_SETTINGS_TAB_PAYMENTS', 'Payments', [], 'settingsTabPayments') }}
      </button>
    </nav>

    <div v-if="activeTab === 'general'" class="nxp-settings-panel">
      <header class="nxp-settings-panel__header">
        <h3>{{ __('COM_NXPEASYCART_SETTINGS_GENERAL_TITLE', 'Store defaults', [], 'settingsGeneralTitle') }}</h3>
        <button class="nxp-btn" type="button" @click="refreshGeneral" :disabled="settingsState.loading">
          {{ __('COM_NXPEASYCART_SETTINGS_GENERAL_REFRESH', 'Refresh', [], 'settingsGeneralRefresh') }}
        </button>
      </header>

      <div v-if="settingsState.error" class="nxp-admin-alert nxp-admin-alert--error">
        {{ settingsState.error }}
      </div>

      <div v-else-if="settingsState.loading" class="nxp-admin-panel__loading">
        {{ __('COM_NXPEASYCART_SETTINGS_GENERAL_LOADING', 'Loading settings…', [], 'settingsGeneralLoading') }}
      </div>

      <form v-else class="nxp-settings-form" @submit.prevent="saveGeneral">
        <div class="nxp-form-field">
          <label class="nxp-form-label" for="settings-store-name">
            {{ __('COM_NXPEASYCART_SETTINGS_GENERAL_STORE_NAME', 'Store name', [], 'settingsGeneralStoreName') }}
          </label>
          <input
            id="settings-store-name"
            class="nxp-form-input"
            type="text"
            v-model.trim="settingsDraft.storeName"
            maxlength="190"
          />
        </div>

        <div class="nxp-form-field">
          <label class="nxp-form-label" for="settings-store-email">
            {{ __('COM_NXPEASYCART_SETTINGS_GENERAL_STORE_EMAIL', 'Support email', [], 'settingsGeneralStoreEmail') }}
          </label>
          <input
            id="settings-store-email"
            class="nxp-form-input"
            type="email"
            v-model.trim="settingsDraft.storeEmail"
          />
        </div>

        <div class="nxp-form-field">
          <label class="nxp-form-label" for="settings-store-phone">
            {{ __('COM_NXPEASYCART_SETTINGS_GENERAL_STORE_PHONE', 'Support phone', [], 'settingsGeneralStorePhone') }}
          </label>
          <input
            id="settings-store-phone"
            class="nxp-form-input"
            type="text"
            v-model.trim="settingsDraft.storePhone"
            maxlength="64"
          />
        </div>

        <div class="nxp-form-field">
          <label class="nxp-form-label" for="settings-base-currency">
            {{ __('COM_NXPEASYCART_SETTINGS_GENERAL_BASE_CURRENCY', 'Base currency', [], 'settingsGeneralBaseCurrency') }}
          </label>
          <input
            id="settings-base-currency"
            class="nxp-form-input nxp-form-input--uppercase"
            type="text"
            v-model.trim="settingsDraft.baseCurrency"
            minlength="3"
            maxlength="3"
            pattern="[A-Za-z]{3}"
            required
          />
          <p class="nxp-form-help">
            {{ __('COM_NXPEASYCART_SETTINGS_GENERAL_BASE_CURRENCY_HELP', 'Use ISO 4217 currency codes such as USD or EUR.', [], 'settingsGeneralBaseCurrencyHelp') }}
          </p>
        </div>

        <div class="nxp-form-field nxp-form-field--inline">
          <label class="nxp-form-label" for="settings-payments-configured">
            {{ __('COM_NXPEASYCART_SETTINGS_GENERAL_PAYMENTS_CONFIGURED', 'Payments configured', [], 'settingsGeneralPaymentsConfigured') }}
          </label>
          <input
            id="settings-payments-configured"
            class="nxp-form-checkbox"
            type="checkbox"
            v-model="settingsDraft.paymentsConfigured"
          />
          <p class="nxp-form-help">
            {{ __('COM_NXPEASYCART_SETTINGS_GENERAL_PAYMENTS_HELP', 'Track when core payment settings are complete (used by dashboard checklist).', [], 'settingsGeneralPaymentsHelp') }}
          </p>
        </div>

        <div class="nxp-settings-actions">
          <button class="nxp-btn" type="button" @click="resetGeneral" :disabled="settingsState.saving">
            {{ __('COM_NXPEASYCART_SETTINGS_GENERAL_CANCEL', 'Cancel', [], 'settingsGeneralCancel') }}
          </button>
          <button class="nxp-btn nxp-btn--primary" type="submit" :disabled="settingsState.saving">
            {{ settingsState.saving ? __('JPROCESSING_REQUEST', 'Saving…') : __('COM_NXPEASYCART_SETTINGS_GENERAL_SAVE', 'Save settings', [], 'settingsGeneralSave') }}
          </button>
        </div>
      </form>
    </div>

    <div v-else-if="activeTab === 'tax'" class="nxp-settings-panel">
      <header class="nxp-settings-panel__header">
        <h3>{{ __('COM_NXPEASYCART_SETTINGS_TAX_TITLE', 'Tax rates', [], 'settingsTaxTitle') }}</h3>
        <button class="nxp-btn" type="button" @click="refreshTax" :disabled="taxState.loading">
          {{ __('COM_NXPEASYCART_COUPONS_REFRESH', 'Refresh', [], 'couponsRefresh') }}
        </button>
      </header>

      <div v-if="taxState.error" class="nxp-admin-alert nxp-admin-alert--error">
        {{ taxState.error }}
      </div>

      <div v-else class="nxp-settings-grid">
        <div class="nxp-settings-table">
          <table class="nxp-admin-table">
            <thead>
              <tr>
                <th scope="col">{{ __('COM_NXPEASYCART_SETTINGS_TAX_COUNTRY', 'Country', [], 'settingsTaxCountry') }}</th>
                <th scope="col">{{ __('COM_NXPEASYCART_SETTINGS_TAX_REGION', 'Region', [], 'settingsTaxRegion') }}</th>
                <th scope="col">{{ __('COM_NXPEASYCART_SETTINGS_TAX_RATE', 'Rate', [], 'settingsTaxRate') }}</th>
                <th scope="col">{{ __('COM_NXPEASYCART_SETTINGS_TAX_INCLUSIVE', 'Inclusive', [], 'settingsTaxInclusive') }}</th>
                <th scope="col">{{ __('COM_NXPEASYCART_SETTINGS_TAX_PRIORITY', 'Priority', [], 'settingsTaxPriority') }}</th>
                <th scope="col" class="nxp-admin-table__actions"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!taxState.items.length">
                <td colspan="6">
                  {{ __('COM_NXPEASYCART_SETTINGS_TAX_EMPTY', 'No tax rates defined.', [], 'settingsTaxEmpty') }}
                </td>
              </tr>
              <tr
                v-for="rate in taxState.items"
                :key="rate.id"
                :class="{ 'is-active': taxDraft.id === rate.id }"
              >
                <th scope="row">{{ rate.country }}</th>
                <td>{{ rate.region || '—' }}</td>
                <td>{{ (rate.rate ?? 0).toFixed(2) }}%</td>
                <td>{{ rate.inclusive ? __('JYES', 'Yes') : __('JNO', 'No') }}</td>
                <td>{{ rate.priority }}</td>
                <td class="nxp-admin-table__actions">
                  <button class="nxp-btn nxp-btn--link" type="button" @click="editTax(rate)">
                    {{ __('JEDIT', 'Edit') }}
                  </button>
                  <button class="nxp-btn nxp-btn--link nxp-btn--danger" type="button" @click="deleteTax(rate)">
                    {{ __('COM_NXPEASYCART_REMOVE', 'Remove') }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <form class="nxp-settings-form" @submit.prevent="saveTax">
          <h4>
            {{ taxDraft.id ? __('JEDIT', 'Edit') : __('COM_NXPEASYCART_SETTINGS_TAX_ADD', 'Add tax rate', [], 'settingsTaxAdd') }}
          </h4>

          <div class="nxp-form-field">
            <label class="nxp-form-label" for="tax-country">
              {{ __('COM_NXPEASYCART_SETTINGS_TAX_COUNTRY', 'Country', [], 'settingsTaxCountry') }}
            </label>
            <input
              id="tax-country"
              class="nxp-form-input"
              type="text"
              v-model.trim="taxDraft.country"
              maxlength="2"
              required
            />
          </div>

          <div class="nxp-form-field">
            <label class="nxp-form-label" for="tax-region">
              {{ __('COM_NXPEASYCART_SETTINGS_TAX_REGION', 'Region', [], 'settingsTaxRegion') }}
            </label>
            <input
              id="tax-region"
              class="nxp-form-input"
              type="text"
              v-model.trim="taxDraft.region"
            />
          </div>

          <div class="nxp-form-field">
            <label class="nxp-form-label" for="tax-rate">
              {{ __('COM_NXPEASYCART_SETTINGS_TAX_RATE', 'Rate', [], 'settingsTaxRate') }}
            </label>
            <input
              id="tax-rate"
              class="nxp-form-input"
              type="number"
              min="0"
              step="0.01"
              v-model.number="taxDraft.rate"
              required
            />
          </div>

          <div class="nxp-form-field nxp-form-field--inline">
            <label class="nxp-form-label" for="tax-inclusive">
              {{ __('COM_NXPEASYCART_SETTINGS_TAX_INCLUSIVE', 'Inclusive', [], 'settingsTaxInclusive') }}
            </label>
            <input
              id="tax-inclusive"
              class="nxp-form-checkbox"
              type="checkbox"
              v-model="taxDraft.inclusive"
            />
          </div>

          <div class="nxp-form-field">
            <label class="nxp-form-label" for="tax-priority">
              {{ __('COM_NXPEASYCART_SETTINGS_TAX_PRIORITY', 'Priority', [], 'settingsTaxPriority') }}
            </label>
            <input
              id="tax-priority"
              class="nxp-form-input"
              type="number"
              step="1"
              v-model.number="taxDraft.priority"
            />
          </div>

          <div class="nxp-settings-actions">
            <button class="nxp-btn" type="button" @click="resetTax" :disabled="taxState.saving">
              {{ __('COM_NXPEASYCART_COUPONS_FORM_CANCEL', 'Cancel', [], 'couponsFormCancel') }}
            </button>
            <button class="nxp-btn nxp-btn--primary" type="submit" :disabled="taxState.saving">
              {{ taxState.saving ? __('JPROCESSING_REQUEST', 'Saving…') : __('COM_NXPEASYCART_COUPONS_FORM_SAVE', 'Save coupon', [], 'couponsFormSave') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <div v-else-if="activeTab === 'shipping'" class="nxp-settings-panel">
      <header class="nxp-settings-panel__header">
        <h3>{{ __('COM_NXPEASYCART_SETTINGS_SHIPPING_TITLE', 'Shipping rules', [], 'settingsShippingTitle') }}</h3>
        <button class="nxp-btn" type="button" @click="refreshShipping" :disabled="shippingState.loading">
          {{ __('COM_NXPEASYCART_COUPONS_REFRESH', 'Refresh', [], 'couponsRefresh') }}
        </button>
      </header>

      <div v-if="shippingState.error" class="nxp-admin-alert nxp-admin-alert--error">
        {{ shippingState.error }}
      </div>

      <div v-else class="nxp-settings-grid">
        <div class="nxp-settings-table">
          <table class="nxp-admin-table">
            <thead>
              <tr>
                <th scope="col">{{ __('COM_NXPEASYCART_SETTINGS_SHIPPING_NAME', 'Name', [], 'settingsShippingName') }}</th>
                <th scope="col">{{ __('COM_NXPEASYCART_SETTINGS_SHIPPING_TYPE', 'Type', [], 'settingsShippingType') }}</th>
                <th scope="col">{{ __('COM_NXPEASYCART_SETTINGS_SHIPPING_PRICE', 'Price', [], 'settingsShippingPrice') }}</th>
                <th scope="col">{{ __('COM_NXPEASYCART_SETTINGS_SHIPPING_THRESHOLD', 'Threshold', [], 'settingsShippingThreshold') }}</th>
                <th scope="col">{{ __('COM_NXPEASYCART_SETTINGS_SHIPPING_REGIONS', 'Regions', [], 'settingsShippingRegions') }}</th>
                <th scope="col">{{ __('COM_NXPEASYCART_SETTINGS_SHIPPING_ACTIVE', 'Active', [], 'settingsShippingActive') }}</th>
                <th scope="col" class="nxp-admin-table__actions"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!shippingState.items.length">
                <td colspan="7">
                  {{ __('COM_NXPEASYCART_SETTINGS_SHIPPING_EMPTY', 'No shipping rules defined.', [], 'settingsShippingEmpty') }}
                </td>
              </tr>
              <tr
                v-for="rule in shippingState.items"
                :key="rule.id"
                :class="{ 'is-active': shippingDraft.id === rule.id }"
              >
                <th scope="row">{{ rule.name }}</th>
                <td>{{ shippingTypeLabel(rule.type) }}</td>
                <td>{{ formatCurrency(rule.price_cents, baseCurrency) }}</td>
                <td>{{ rule.type === 'free_over' ? formatCurrency(rule.threshold_cents, baseCurrency) : '—' }}</td>
                <td>{{ rule.regions && rule.regions.length ? rule.regions.join(', ') : __('COM_NXPEASYCART_SETTINGS_SHIPPING_ALL', 'All regions', [], 'settingsShippingAll') }}</td>
                <td>{{ rule.active ? __('JYES', 'Yes') : __('JNO', 'No') }}</td>
                <td class="nxp-admin-table__actions">
                  <button class="nxp-btn nxp-btn--link" type="button" @click="editShipping(rule)">
                    {{ __('JEDIT', 'Edit') }}
                  </button>
                  <button class="nxp-btn nxp-btn--link nxp-btn--danger" type="button" @click="deleteShipping(rule)">
                    {{ __('COM_NXPEASYCART_REMOVE', 'Remove') }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <form class="nxp-settings-form" @submit.prevent="saveShipping">
          <h4>
            {{ shippingDraft.id ? __('JEDIT', 'Edit') : __('COM_NXPEASYCART_SETTINGS_SHIPPING_ADD', 'Add shipping rule', [], 'settingsShippingAdd') }}
          </h4>

          <div class="nxp-form-field">
            <label class="nxp-form-label" for="shipping-name">
              {{ __('COM_NXPEASYCART_SETTINGS_SHIPPING_NAME', 'Name', [], 'settingsShippingName') }}
            </label>
            <input
              id="shipping-name"
              class="nxp-form-input"
              type="text"
              v-model.trim="shippingDraft.name"
              required
            />
          </div>

          <div class="nxp-form-field">
            <label class="nxp-form-label" for="shipping-type">
              {{ __('COM_NXPEASYCART_SETTINGS_SHIPPING_TYPE', 'Type', [], 'settingsShippingType') }}
            </label>
            <select id="shipping-type" class="nxp-form-select" v-model="shippingDraft.type">
              <option value="flat">
                {{ __('COM_NXPEASYCART_SETTINGS_SHIPPING_TYPE_FLAT', 'Flat rate', [], 'settingsShippingTypeFlat') }}
              </option>
              <option value="free_over">
                {{ __('COM_NXPEASYCART_SETTINGS_SHIPPING_TYPE_FREE', 'Free over threshold', [], 'settingsShippingTypeFree') }}
              </option>
            </select>
          </div>

          <div class="nxp-form-field">
            <label class="nxp-form-label" for="shipping-price">
              {{ __('COM_NXPEASYCART_SETTINGS_SHIPPING_PRICE', 'Price', [], 'settingsShippingPrice') }}
            </label>
            <input
              id="shipping-price"
              class="nxp-form-input"
              type="number"
              min="0"
              step="0.01"
              v-model.number="shippingDraft.price"
              required
            />
          </div>

          <div class="nxp-form-field" v-if="shippingDraft.type === 'free_over'">
            <label class="nxp-form-label" for="shipping-threshold">
              {{ __('COM_NXPEASYCART_SETTINGS_SHIPPING_THRESHOLD', 'Threshold', [], 'settingsShippingThreshold') }}
            </label>
            <input
              id="shipping-threshold"
              class="nxp-form-input"
              type="number"
              min="0"
              step="0.01"
              v-model.number="shippingDraft.threshold"
              required
            />
          </div>

          <div class="nxp-form-field">
            <label class="nxp-form-label" for="shipping-regions">
              {{ __('COM_NXPEASYCART_SETTINGS_SHIPPING_REGIONS', 'Regions', [], 'settingsShippingRegions') }}
            </label>
            <input
              id="shipping-regions"
              class="nxp-form-input"
              type="text"
              v-model.trim="shippingDraft.regions"
              :placeholder="__('COM_NXPEASYCART_SETTINGS_SHIPPING_REGIONS_PLACEHOLDER', 'Use comma-separated ISO codes (two-letter ISO 3166‑1 alpha‑2), e.g. US,GB,FR,DE,DK,MK,BG', [], 'settingsShippingRegionsPlaceholder')"
            />
          </div>

          <div class="nxp-form-field nxp-form-field--inline">
            <label class="nxp-form-label" for="shipping-active">
              {{ __('COM_NXPEASYCART_SETTINGS_SHIPPING_ACTIVE', 'Active', [], 'settingsShippingActive') }}
            </label>
            <input
              id="shipping-active"
              class="nxp-form-checkbox"
              type="checkbox"
              v-model="shippingDraft.active"
            />
          </div>

          <div class="nxp-settings-actions">
            <button class="nxp-btn" type="button" @click="resetShipping" :disabled="shippingState.saving">
              {{ __('COM_NXPEASYCART_COUPONS_FORM_CANCEL', 'Cancel', [], 'couponsFormCancel') }}
            </button>
            <button class="nxp-btn nxp-btn--primary" type="submit" :disabled="shippingState.saving">
              {{ shippingState.saving ? __('JPROCESSING_REQUEST', 'Saving…') : __('COM_NXPEASYCART_COUPONS_FORM_SAVE', 'Save coupon', [], 'couponsFormSave') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <div v-else-if="activeTab === 'payments'" class="nxp-settings-panel">
      <header class="nxp-settings-panel__header">
        <h3>{{ __('COM_NXPEASYCART_SETTINGS_PAYMENTS_TITLE', 'Payment gateways', [], 'settingsPaymentsTitle') }}</h3>
        <div class="nxp-settings-actions">
          <button class="nxp-btn" type="button" @click="refreshPayments" :disabled="paymentsState.loading">
            {{ __('COM_NXPEASYCART_SETTINGS_PAYMENTS_REFRESH', 'Refresh', [], 'settingsPaymentsRefresh') }}
          </button>
        </div>
      </header>

      <div v-if="paymentsState.error" class="nxp-admin-alert nxp-admin-alert--error">
        {{ paymentsState.error }}
      </div>

      <div v-else-if="paymentsState.loading" class="nxp-admin-panel__loading">
        {{ __('COM_NXPEASYCART_SETTINGS_PAYMENTS_LOADING', 'Loading payment configuration…', [], 'settingsPaymentsLoading') }}
      </div>

      <form v-else class="nxp-settings-form nxp-settings-form--payments" @submit.prevent="savePayments">
        <fieldset>
          <legend>{{ __('COM_NXPEASYCART_SETTINGS_PAYMENTS_STRIPE', 'Stripe', [], 'settingsPaymentsStripe') }}</legend>
          <div class="nxp-form-field">
            <label class="nxp-form-label" for="nxp-stripe-publishable">
              {{ __('COM_NXPEASYCART_SETTINGS_PAYMENTS_STRIPE_PUBLISHABLE', 'Publishable key', [], 'settingsPaymentsStripePublishable') }}
            </label>
            <input
              id="nxp-stripe-publishable"
              class="nxp-form-input"
              type="text"
              v-model.trim="paymentsDraft.stripe.publishable_key"
            />
          </div>

          <div class="nxp-form-field">
            <label class="nxp-form-label" for="nxp-stripe-secret">
              {{ __('COM_NXPEASYCART_SETTINGS_PAYMENTS_STRIPE_SECRET', 'Secret key', [], 'settingsPaymentsStripeSecret') }}
            </label>
            <input
              id="nxp-stripe-secret"
              class="nxp-form-input"
              type="password"
              v-model.trim="paymentsDraft.stripe.secret_key"
              autocomplete="off"
            />
          </div>

          <div class="nxp-form-field">
            <label class="nxp-form-label" for="nxp-stripe-webhook">
              {{ __('COM_NXPEASYCART_SETTINGS_PAYMENTS_STRIPE_WEBHOOK', 'Webhook secret', [], 'settingsPaymentsStripeWebhook') }}
            </label>
            <input
              id="nxp-stripe-webhook"
              class="nxp-form-input"
              type="password"
              v-model.trim="paymentsDraft.stripe.webhook_secret"
              autocomplete="off"
            />
            <p class="nxp-form-help">
              {{ __('COM_NXPEASYCART_SETTINGS_PAYMENTS_STRIPE_HELP', 'Copy the signing secret from your Stripe webhook configuration.', [], 'settingsPaymentsStripeHelp') }}
            </p>
          </div>

          <div class="nxp-form-field">
            <label class="nxp-form-label" for="nxp-stripe-mode">
              {{ __('COM_NXPEASYCART_SETTINGS_PAYMENTS_MODE', 'Mode', [], 'settingsPaymentsMode') }}
            </label>
            <select id="nxp-stripe-mode" class="nxp-form-input" v-model="paymentsDraft.stripe.mode">
              <option value="test">
                {{ __('COM_NXPEASYCART_SETTINGS_PAYMENTS_MODE_TEST', 'Test', [], 'settingsPaymentsModeTest') }}
              </option>
              <option value="live">
                {{ __('COM_NXPEASYCART_SETTINGS_PAYMENTS_MODE_LIVE', 'Live', [], 'settingsPaymentsModeLive') }}
              </option>
            </select>
          </div>
        </fieldset>

        <fieldset>
          <legend>{{ __('COM_NXPEASYCART_SETTINGS_PAYMENTS_PAYPAL', 'PayPal', [], 'settingsPaymentsPayPal') }}</legend>
          <div class="nxp-form-field">
            <label class="nxp-form-label" for="nxp-paypal-client-id">
              {{ __('COM_NXPEASYCART_SETTINGS_PAYMENTS_PAYPAL_CLIENT_ID', 'Client ID', [], 'settingsPaymentsPayPalClientId') }}
            </label>
            <input
              id="nxp-paypal-client-id"
              class="nxp-form-input"
              type="text"
              v-model.trim="paymentsDraft.paypal.client_id"
            />
          </div>

          <div class="nxp-form-field">
            <label class="nxp-form-label" for="nxp-paypal-client-secret">
              {{ __('COM_NXPEASYCART_SETTINGS_PAYMENTS_PAYPAL_CLIENT_SECRET', 'Client secret', [], 'settingsPaymentsPayPalClientSecret') }}
            </label>
            <input
              id="nxp-paypal-client-secret"
              class="nxp-form-input"
              type="password"
              v-model.trim="paymentsDraft.paypal.client_secret"
              autocomplete="off"
            />
          </div>

          <div class="nxp-form-field">
            <label class="nxp-form-label" for="nxp-paypal-webhook-id">
              {{ __('COM_NXPEASYCART_SETTINGS_PAYMENTS_PAYPAL_WEBHOOK', 'Webhook ID', [], 'settingsPaymentsPayPalWebhook') }}
            </label>
            <input
              id="nxp-paypal-webhook-id"
              class="nxp-form-input"
              type="text"
              v-model.trim="paymentsDraft.paypal.webhook_id"
            />
            <p class="nxp-form-help">
              {{ __('COM_NXPEASYCART_SETTINGS_PAYMENTS_PAYPAL_HELP', 'Use the webhook ID from your PayPal app to verify notifications.', [], 'settingsPaymentsPayPalHelp') }}
            </p>
          </div>

          <div class="nxp-form-field">
            <label class="nxp-form-label" for="nxp-paypal-mode">
              {{ __('COM_NXPEASYCART_SETTINGS_PAYMENTS_MODE', 'Mode', [], 'settingsPaymentsMode') }}
            </label>
            <select id="nxp-paypal-mode" class="nxp-form-input" v-model="paymentsDraft.paypal.mode">
              <option value="sandbox">
                {{ __('COM_NXPEASYCART_SETTINGS_PAYMENTS_MODE_SANDBOX', 'Sandbox', [], 'settingsPaymentsModeSandbox') }}
              </option>
              <option value="live">
                {{ __('COM_NXPEASYCART_SETTINGS_PAYMENTS_MODE_LIVE', 'Live', [], 'settingsPaymentsModeLive') }}
              </option>
            </select>
          </div>
        </fieldset>

        <div v-if="paymentsState.message" class="nxp-admin-alert nxp-admin-alert--success">
          {{ paymentsState.message }}
        </div>

        <div class="nxp-settings-actions">
          <button class="nxp-btn" type="button" @click="resetPayments" :disabled="paymentsState.saving">
            {{ __('COM_NXPEASYCART_SETTINGS_GENERAL_CANCEL', 'Cancel', [], 'settingsPaymentsCancel') }}
          </button>
          <button class="nxp-btn nxp-btn--primary" type="submit" :disabled="paymentsState.saving">
            {{ paymentsState.saving ? __('JPROCESSING_REQUEST', 'Saving…') : __('COM_NXPEASYCART_SETTINGS_PAYMENTS_SAVE', 'Save payments', [], 'settingsPaymentsSave') }}
          </button>
        </div>
      </form>
    </div>
  </section>
</template>

<script setup>
import { reactive, ref, watch, computed } from 'vue';

const props = defineProps({
  settingsState: {
    type: Object,
    required: true,
  },
  taxState: {
    type: Object,
    required: true,
  },
  shippingState: {
    type: Object,
    required: true,
  },
  paymentsState: {
    type: Object,
    default: () => ({
      loading: false,
      saving: false,
      error: '',
      message: '',
      config: {
        stripe: {},
        paypal: {},
      },
    }),
  },
  translate: {
    type: Function,
    required: true,
  },
  baseCurrency: {
    type: String,
    default: 'USD',
  },
});

const emit = defineEmits([
  'refresh-settings',
  'save-settings',
  'refresh-tax',
  'save-tax',
  'delete-tax',
  'refresh-shipping',
  'save-shipping',
  'delete-shipping',
  'refresh-payments',
  'save-payments',
]);

const __ = props.translate;
const settingsState = props.settingsState;
const taxState = props.taxState;
const shippingState = props.shippingState;
const paymentsState = props.paymentsState;

const activeTab = ref('general');

const baseCurrency = computed(() => {
  const fallback = props.baseCurrency || 'USD';
  const draft = typeof settingsDraft.baseCurrency === 'string' ? settingsDraft.baseCurrency.trim() : '';
  const stateCurrency = typeof settingsState?.values?.base_currency === 'string'
    ? settingsState.values.base_currency.trim()
    : '';

  const value = draft !== '' ? draft : (stateCurrency !== '' ? stateCurrency : fallback);

  return value.toUpperCase();
});

const settingsDraft = reactive({
  storeName: '',
  storeEmail: '',
  storePhone: '',
  paymentsConfigured: false,
  baseCurrency: '',
});

const taxDraft = reactive({
  id: null,
  country: '',
  region: '',
  rate: 0,
  inclusive: false,
  priority: 0,
});

const shippingDraft = reactive({
  id: null,
  name: '',
  type: 'flat',
  price: 0,
  threshold: 0,
  regions: '',
  active: true,
});

const paymentsDraft = reactive({
  stripe: {
    publishable_key: '',
    secret_key: '',
    webhook_secret: '',
    mode: 'test',
  },
  paypal: {
    client_id: '',
    client_secret: '',
    webhook_id: '',
    mode: 'sandbox',
  },
});

const applySettings = (values = {}) => {
  const store = values?.store ?? {};
  const payments = values?.payments ?? {};

  Object.assign(settingsDraft, {
    storeName: store.name ?? '',
    storeEmail: store.email ?? '',
    storePhone: store.phone ?? '',
    paymentsConfigured: Boolean(payments.configured),
    baseCurrency: typeof values?.base_currency === 'string'
      ? values.base_currency.trim().toUpperCase()
      : (props.baseCurrency || 'USD').toUpperCase(),
  });
};

const applyPayments = (config = {}) => {
  const stripe = config.stripe ?? {};
  const paypal = config.paypal ?? {};

  Object.assign(paymentsDraft.stripe, {
    publishable_key: stripe.publishable_key ?? '',
    secret_key: stripe.secret_key ?? '',
    webhook_secret: stripe.webhook_secret ?? '',
    mode: stripe.mode ?? 'test',
  });

  Object.assign(paymentsDraft.paypal, {
    client_id: paypal.client_id ?? '',
    client_secret: paypal.client_secret ?? '',
    webhook_id: paypal.webhook_id ?? '',
    mode: paypal.mode ?? 'sandbox',
  });
};

watch(
  () => settingsState.values,
  (values) => {
    applySettings(values ?? {});
  },
  { immediate: true }
);

const refreshGeneral = () => emit('refresh-settings');

const saveGeneral = () => {
  const currency = (settingsDraft.baseCurrency || '').trim().toUpperCase();

  emit('save-settings', {
    store: {
      name: settingsDraft.storeName,
      email: settingsDraft.storeEmail,
      phone: settingsDraft.storePhone,
      base_currency: currency,
    },
    payments: {
      configured: settingsDraft.paymentsConfigured,
    },
    base_currency: currency,
  });
};

const resetGeneral = () => {
  applySettings(settingsState.values ?? {});
};

watch(
  () => settingsState.saving,
  (saving, wasSaving) => {
    if (wasSaving && !saving && !settingsState.error) {
      applySettings(settingsState.values ?? {});
    }
  }
);

watch(
  () => settingsDraft.baseCurrency,
  (value) => {
    if (typeof value === 'string') {
      const normalised = value.replace(/[^A-Za-z]/g, '').toUpperCase();

      if (normalised !== value) {
        settingsDraft.baseCurrency = normalised;
      }
    }
  }
);

watch(
  () => paymentsState.config,
  (config) => {
    applyPayments(config ?? {});
  },
  { immediate: true }
);

watch(
  () => paymentsState.saving,
  (saving, wasSaving) => {
    if (wasSaving && !saving && !paymentsState.error) {
      applyPayments(paymentsState.config ?? {});
    }
  }
);

const refreshPayments = () => emit('refresh-payments');
const savePayments = () => {
  const payload = JSON.parse(JSON.stringify(paymentsDraft));
  emit('save-payments', payload);
};
const resetPayments = () => {
  applyPayments(paymentsState.config ?? {});
};

const refreshTax = () => emit('refresh-tax');
const refreshShipping = () => emit('refresh-shipping');

const editTax = (rate) => {
  Object.assign(taxDraft, {
    id: rate.id,
    country: rate.country,
    region: rate.region,
    rate: rate.rate,
    inclusive: rate.inclusive,
    priority: rate.priority,
  });
};

const resetTax = () => {
  Object.assign(taxDraft, {
    id: null,
    country: '',
    region: '',
    rate: 0,
    inclusive: false,
    priority: 0,
  });
};

const saveTax = () => {
  emit('save-tax', {
    id: taxDraft.id || undefined,
    country: taxDraft.country,
    region: taxDraft.region,
    rate: taxDraft.rate,
    inclusive: taxDraft.inclusive,
    priority: taxDraft.priority,
  });
};

const deleteTax = (rate) => {
  const message = __('COM_NXPEASYCART_SETTINGS_TAX_DELETE', 'Delete this tax rate?', [], 'settingsTaxDelete');

  if (window.confirm(message)) {
    emit('delete-tax', [rate.id]);
    if (taxDraft.id === rate.id) {
      resetTax();
    }
  }
};

watch(
  () => taxState.saving,
  (saving, wasSaving) => {
    if (wasSaving && !saving && !taxState.error) {
      resetTax();
    }
  }
);

const editShipping = (rule) => {
  Object.assign(shippingDraft, {
    id: rule.id,
    name: rule.name,
    type: rule.type,
    price: rule.price ?? (rule.price_cents ?? 0) / 100,
    threshold: rule.threshold !== null && rule.threshold !== undefined
      ? rule.threshold
      : (rule.threshold_cents ?? 0) / 100,
    regions: rule.regions && rule.regions.length ? rule.regions.join(', ') : '',
    active: rule.active,
  });
};

const resetShipping = () => {
  Object.assign(shippingDraft, {
    id: null,
    name: '',
    type: 'flat',
    price: 0,
    threshold: 0,
    regions: '',
    active: true,
  });
};

const saveShipping = () => {
  const payload = {
    id: shippingDraft.id || undefined,
    name: shippingDraft.name,
    type: shippingDraft.type,
    price: shippingDraft.price,
    threshold: shippingDraft.type === 'free_over' ? shippingDraft.threshold : 0,
    regions: shippingDraft.regions,
    active: shippingDraft.active,
  };

  emit('save-shipping', payload);
};

const deleteShipping = (rule) => {
  const message = __('COM_NXPEASYCART_SETTINGS_SHIPPING_DELETE', 'Delete this shipping rule?', [], 'settingsShippingDelete');

  if (window.confirm(message)) {
    emit('delete-shipping', [rule.id]);
    if (shippingDraft.id === rule.id) {
      resetShipping();
    }
  }
};

watch(
  () => shippingState.saving,
  (saving, wasSaving) => {
    if (wasSaving && !saving && !shippingState.error) {
      resetShipping();
    }
  }
);

const formatCurrency = (cents, currency) => {
  const amount = (Number(cents) || 0) / 100;
  const code = (currency || '').toUpperCase() || baseCurrency.value;

  try {
    return new Intl.NumberFormat(undefined, {
      style: 'currency',
      currency: code,
    }).format(amount);
  } catch (error) {
    return `${code} ${amount.toFixed(2)}`;
  }
};

const shippingTypeLabel = (type) => {
  return type === 'free_over'
    ? __('COM_NXPEASYCART_SETTINGS_SHIPPING_TYPE_FREE', 'Free over threshold', [], 'settingsShippingTypeFree')
    : __('COM_NXPEASYCART_SETTINGS_SHIPPING_TYPE_FLAT', 'Flat rate', [], 'settingsShippingTypeFlat');
};
</script>

<style scoped>
.nxp-settings-tabs {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1.5rem;
}

.nxp-settings-tab {
  padding: 0.5rem 1rem;
  border: 1px solid #d0d5dd;
  border-radius: 999px;
  background: #fff;
  cursor: pointer;
}

.nxp-settings-tab.is-active {
  background: #4f46e5;
  color: #fff;
  border-color: #4f46e5;
}

.nxp-settings-panel {
  display: grid;
  gap: 1.5rem;
}

.nxp-settings-panel__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.nxp-settings-grid {
  display: grid;
  gap: 1.5rem;
  grid-template-columns: 2fr 1fr;
}

@media (max-width: 960px) {
  .nxp-settings-grid {
    grid-template-columns: 1fr;
  }
}

.nxp-settings-table {
  overflow-x: auto;
}

.nxp-settings-form {
  display: grid;
  gap: 1rem;
  padding: 1rem;
  border: 1px solid #e4e7ec;
  border-radius: 0.75rem;
  background: #fff;
}

.nxp-settings-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
}

.nxp-form-input--uppercase {
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.nxp-form-static {
  padding: 0.5rem 0.75rem;
  border: 1px solid #e4e7ec;
  border-radius: 0.5rem;
  background: #f9fafb;
}
</style>
