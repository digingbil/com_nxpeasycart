import { createApp, reactive, computed, ref, watch, onMounted } from "vue";
import parsePayload from "../utils/parsePayload.js";
import formatMoney from "../utils/formatMoney.js";
import { createApiClient } from "../utils/apiClient.js";
import {
    getCountries,
    getRegions,
    hasRegions,
    getCountryName,
    getRegionName,
} from "../utils/countryRegionData.js";

export default function mountCheckoutIsland(el) {
    const locale = el.dataset.nxpLocale || undefined;
    const currencyAttr = (el.dataset.nxpCurrency || "").trim() || undefined;
    const payload = parsePayload(el.dataset.nxpCheckout, {});
    const labelsPayload = parsePayload(el.dataset.nxpLabels, {});
    const cart = payload.cart || { items: [], summary: {} };
    const shippingRules = payload.shipping_rules || [];
    const taxRates = payload.tax_rates || [];
    const settings = payload.settings || {};
    const payments = payload.payments || {};
    const endpoints = payload.endpoints || {};
    const token = payload.token || "";
    const api = createApiClient(token);

    el.innerHTML = "";

    const app = createApp({
        template: `
      <div class="nxp-ec-checkout" v-cloak>
        <header class="nxp-ec-checkout__header">
          <h1 class="nxp-ec-checkout__title">{{ labels.title }}</h1>
          <p class="nxp-ec-checkout__lead">
            {{ labels.lead }}
          </p>
        </header>

        <div class="nxp-ec-checkout__layout" v-if="!success">
          <aside class="nxp-ec-checkout__summary">
            <h2>{{ labels.order_summary }}</h2>
            <div class="nxp-ec-checkout__cart" v-if="cartItems.length">
              <ul>
                <li v-for="item in cartItems" :key="item.id">
                  <div>
                    <strong>{{ item.product_title || item.title }}</strong>
                    <span class="nxp-ec-checkout__qty">× {{ item.qty }}</span>
                  </div>
                  <div class="nxp-ec-checkout__price">{{ formatMoney(item.total_cents) }}</div>
                </li>
              </ul>
              <div class="nxp-ec-checkout__totals">
                <div>
                  <span>{{ labels.subtotal }}</span>
                  <strong>{{ formatMoney(subtotal) }}</strong>
                </div>
                <div>
                  <span>{{ labels.shipping }}</span>
                  <strong>{{ formatMoney(selectedShippingCost) }}</strong>
                </div>
                <div v-if="showTax">
                  <span>{{ taxLabel }}</span>
                  <strong>{{ formatMoney(taxAmount) }}</strong>
                </div>
                <div>
                  <span>{{ labels.total }}</span>
                  <strong>{{ formatMoney(total) }}</strong>
                </div>
              </div>
            </div>
            <p v-else>{{ labels.empty_cart }}</p>
          </aside>

          <form class="nxp-ec-checkout__form" @submit.prevent="submit">
            <fieldset>
              <legend>{{ labels.contact }}</legend>
              <div class="nxp-ec-checkout__field">
                <label for="nxp-ec-checkout-email">{{ labels.email }}</label>
                <input id="nxp-ec-checkout-email" type="email" v-model="model.email" required autocomplete="email" />
              </div>
            </fieldset>

            <fieldset>
              <legend>{{ labels.billing }}</legend>
              <div class="nxp-ec-checkout__grid">
                <div class="nxp-ec-checkout__field">
                  <label for="nxp-ec-first-name">{{ labels.first_name }}</label>
                  <input id="nxp-ec-first-name" type="text" v-model="model.billing.first_name" required autocomplete="given-name" />
                </div>
                <div class="nxp-ec-checkout__field">
                  <label for="nxp-ec-last-name">{{ labels.last_name }}</label>
                  <input id="nxp-ec-last-name" type="text" v-model="model.billing.last_name" required autocomplete="family-name" />
                </div>
                <div class="nxp-ec-checkout__field">
                  <label for="nxp-ec-phone">{{ labels.phone }}</label>
                  <input
                    id="nxp-ec-phone"
                    type="tel"
                    v-model.trim="model.billing.phone"
                    :required="phoneRequired"
                    :placeholder="phonePlaceholder"
                    inputmode="tel"
                    autocomplete="tel"
                  />
                </div>
                <div class="nxp-ec-checkout__field nxp-ec-checkout__field--wide">
                  <label for="nxp-ec-address-line1">{{ labels.address }}</label>
                  <input id="nxp-ec-address-line1" type="text" v-model="model.billing.address_line1" required autocomplete="street-address" />
                </div>
                <div class="nxp-ec-checkout__field">
                  <label for="nxp-ec-city">{{ labels.city }}</label>
                  <input id="nxp-ec-city" type="text" v-model="model.billing.city" required autocomplete="address-level2" />
                </div>
                <div class="nxp-ec-checkout__field">
                  <label for="nxp-ec-postcode">{{ labels.postcode }}</label>
                  <input id="nxp-ec-postcode" type="text" v-model="model.billing.postcode" required autocomplete="postal-code" />
                </div>
                <div class="nxp-ec-checkout__field">
                  <label for="nxp-ec-country">{{ labels.country }}</label>
                  <select
                    id="nxp-ec-country"
                    v-model="model.billing.country_code"
                    required
                    autocomplete="country"
                    class="nxp-ec-checkout__select"
                  >
                    <option value="" disabled>{{ labels.select_country }}</option>
                    <option
                      v-for="country in countries"
                      :key="country.code"
                      :value="country.code"
                    >{{ country.name }}</option>
                  </select>
                </div>
                <div class="nxp-ec-checkout__field" v-if="showRegionField">
                  <label for="nxp-ec-region">{{ regionLabel }}</label>
                  <select
                    id="nxp-ec-region"
                    v-model="model.billing.region_code"
                    :required="regionRequired"
                    autocomplete="address-level1"
                    class="nxp-ec-checkout__select"
                  >
                    <option value="">{{ regionPlaceholder }}</option>
                    <option
                      v-for="region in availableRegions"
                      :key="region.code"
                      :value="region.code"
                    >{{ region.name }}</option>
                  </select>
                </div>
              </div>
            </fieldset>

            <fieldset>
              <legend>{{ labels.shipping }}</legend>
              <p class="nxp-ec-checkout__radio-group">
                <template v-if="!model.billing.country_code">
                  <span class="nxp-ec-checkout__notice">{{ labels.shipping_select_country }}</span>
                </template>
                <template v-else-if="applicableShippingRules.length === 0">
                  <span class="nxp-ec-checkout__notice">{{ formatShippingNoRules(getCountryName(model.billing.country_code)) }}</span>
                </template>
                <template v-else>
                  <label
                    v-for="rule in applicableShippingRules"
                    :key="rule.id"
                  >
                    <input
                      type="radio"
                      name="shipping_rule"
                      :value="rule.id"
                      v-model="model.shipping_rule_id"
                    />
                    <span>{{ rule.name }} — {{ formatMoney(rule.price_cents) }}</span>
                  </label>
                </template>
              </p>
            </fieldset>

            <fieldset>
              <legend>{{ labels.payment_method }}</legend>
              <p class="nxp-ec-checkout__radio-group" v-if="gateways.length">
                <label
                  v-for="gateway in gateways"
                  :key="gateway.id"
                >
                  <input
                    type="radio"
                    name="nxp-ec-checkout-gateway"
                    :value="gateway.id"
                    v-model="selectedGateway"
                  />
                  <span>{{ gateway.label }}</span>
                </label>
              </p>
              <p v-else>
                {{ labels.payment_offline }}
              </p>
            </fieldset>

            <div v-if="error" class="nxp-ec-admin-alert nxp-ec-admin-alert--error">
              {{ error }}
            </div>

            <button type="submit" class="nxp-ec-btn nxp-ec-btn--primary" :disabled="loading">
              <span v-if="loading">{{ labels.processing }}</span>
              <span v-else>{{ labels.submit }}</span>
            </button>
          </form>
        </div>

        <div v-else class="nxp-ec-order-confirmation__summary">
          <h2>{{ labels.thank_you }}</h2>
          <p v-html="formatOrderCreated(orderNumber)"></p>
          <a class="nxp-ec-btn" :href="orderUrl">{{ labels.view_order }}</a>
        </div>
      </div>
    `,
        setup() {
            const cartItems = reactive(
                (cart.items || []).map((item) => ({ ...item }))
            );
            const currency =
                cart.summary?.currency ||
                settings.base_currency ||
                currencyAttr ||
                "USD";
            const shipping = (shippingRules || [])
                .filter(
                    (rule) => rule && (rule.active === undefined || rule.active)
                )
                .map((rule, index) => ({
                    ...rule,
                    id: rule.id ?? index,
                    type: rule.type || "flat",
                    price_cents: Number(rule.price_cents || 0),
                    threshold_cents:
                        rule.threshold_cents !== undefined &&
                        rule.threshold_cents !== null
                            ? Number(rule.threshold_cents)
                            : null,
                    default: index === 0,
                }));
            const normalisedTaxRates = (taxRates || [])
                .filter((rate) => rate && rate.rate !== undefined)
                .map((rate) => ({
                    id:
                        rate.id ??
                        rate.country ??
                        rate.region ??
                        Math.random().toString(36),
                    country: (rate.country || "").toUpperCase(),
                    region: (rate.region || "").toLowerCase(),
                    rate: Number(rate.rate || 0),
                    inclusive: Boolean(rate.inclusive),
                    priority: Number(rate.priority || 0),
                }))
                .sort(
                    (a, b) =>
                        (a.priority ?? 0) - (b.priority ?? 0) ||
                        b.rate - a.rate
                );

            const isConfigured = (config, keys = []) =>
                keys.every((key) => {
                    const value = config[key] ?? "";
                    return String(value).trim() !== "";
                });

            const gatewayOptions = [];

            if (
                isConfigured(payments.stripe ?? {}, [
                    "publishable_key",
                    "secret_key",
                ])
            ) {
                gatewayOptions.push({
                    id: "stripe",
                    label: "Card (Stripe)",
                });
            }

            if (
                isConfigured(payments.paypal ?? {}, [
                    "client_id",
                    "client_secret",
                ])
            ) {
                gatewayOptions.push({
                    id: "paypal",
                    label: "PayPal",
                });
            }

            if ((payments.cod?.enabled ?? true) && cartItems.length > 0) {
                gatewayOptions.push({
                    id: "cod",
                    label: payments.cod?.label || "Cash on delivery",
                });
            }

            const gateways = gatewayOptions;
            const selectedGateway = ref(gateways[0]?.id || "");
            const hostedCheckoutAvailable =
                gateways.length > 0 && Boolean(endpoints.payment);

            const normaliseBool = (value) => {
                if (typeof value === "boolean") {
                    return value;
                }

                if (typeof value === "string") {
                    const trimmed = value.trim().toLowerCase();
                    return trimmed === "1" || trimmed === "true" || trimmed === "yes";
                }

                return Boolean(Number(value || 0));
            };

            const phoneRequired = normaliseBool(
                settings.checkout_phone_required ??
                    settings.checkout?.phone_required ??
                    false
            );

            const phonePlaceholder = computed(() =>
                phoneRequired
                    ? labels.phone_placeholder_required
                    : labels.phone_placeholder
            );

            // Country/Region data
            const countries = getCountries();

            const state = reactive({
                email: "",
                billing: {
                    first_name: "",
                    last_name: "",
                    phone: "",
                    address_line1: "",
                    city: "",
                    postcode: "",
                    country_code: "",
                    region_code: "",
                },
                shipping_rule_id: null,
            });

            // Computed: available regions based on selected country
            const availableRegions = computed(() => {
                if (!state.billing.country_code) {
                    return [];
                }
                return getRegions(state.billing.country_code);
            });

            // Show region field only if country has regions
            const showRegionField = computed(() => {
                return (
                    state.billing.country_code &&
                    hasRegions(state.billing.country_code)
                );
            });

            // Build labels object with fallbacks
            const labels = {
                title: labelsPayload.title || "Checkout",
                lead: labelsPayload.lead || "Enter your details to complete the order.",
                contact: labelsPayload.contact || "Contact",
                email: labelsPayload.email || "Email",
                billing: labelsPayload.billing || "Billing address",
                first_name: labelsPayload.first_name || "First name",
                last_name: labelsPayload.last_name || "Last name",
                address: labelsPayload.address || "Address",
                city: labelsPayload.city || "City",
                postcode: labelsPayload.postcode || "Postcode",
                country: labelsPayload.country || "Country",
                region: labelsPayload.region || "Region/State",
                region_state: labelsPayload.region_state || "State",
                region_province: labelsPayload.region_province || "Province",
                region_territory: labelsPayload.region_territory || "State/Territory",
                region_county: labelsPayload.region_county || "County",
                select_country: labelsPayload.select_country || "Select country...",
                select_region: labelsPayload.select_region || "Select region...",
                select_state: labelsPayload.select_state || "Select state...",
                select_province: labelsPayload.select_province || "Select province...",
                phone: labelsPayload.phone || "Phone",
                phone_placeholder: labelsPayload.phone_placeholder || "Optional, e.g. +1 555 123 4567",
                phone_placeholder_required: labelsPayload.phone_placeholder_required || "Enter a phone number we can reach you on",
                phone_required: labelsPayload.phone_required || "Please enter a phone number so we can reach you about your order.",
                phone_invalid: labelsPayload.phone_invalid || "Please enter a valid phone number (6-20 characters).",
                shipping: labelsPayload.shipping || "Shipping",
                shipping_select_country: labelsPayload.shipping_select_country || "Please select your country above to see available shipping options.",
                shipping_no_rules: labelsPayload.shipping_no_rules || "No shipping available to %s. Please contact us for assistance.",
                payment_method: labelsPayload.payment_method || "Payment method",
                payment_offline: labelsPayload.payment_offline || "Payments will be captured offline once this order is submitted.",
                processing: labelsPayload.processing || "Processing…",
                submit: labelsPayload.submit || "Complete order",
                order_summary: labelsPayload.order_summary || "Order summary",
                subtotal: labelsPayload.subtotal || "Subtotal",
                total: labelsPayload.total || "Total",
                empty_cart: labelsPayload.empty_cart || "Your cart is empty.",
                thank_you: labelsPayload.thank_you || "Thank you!",
                order_created: labelsPayload.order_created || "Your order %s was created successfully.",
                view_order: labelsPayload.view_order || "View order summary",
                error_generic: labelsPayload.error_generic || "Unable to complete checkout right now.",
            };

            // Dynamic label based on country (State for US, Province for CA, Region otherwise)
            const regionLabel = computed(() => {
                const code = state.billing.country_code;
                if (code === "US") return labels.region_state;
                if (code === "CA") return labels.region_province;
                if (code === "AU") return labels.region_territory;
                if (code === "GB" || code === "UK") return labels.region_county;
                return labels.region;
            });

            const regionPlaceholder = computed(() => {
                const code = state.billing.country_code;
                if (code === "US") return labels.select_state;
                if (code === "CA") return labels.select_province;
                return labels.select_region;
            });

            // Region is required for countries with subdivisions
            const regionRequired = computed(() => {
                const code = state.billing.country_code;
                // Make region required for major countries with tax implications
                return ["US", "CA", "AU", "IN", "BR", "MX"].includes(code);
            });

            // Filter shipping rules based on selected country/region
            const applicableShippingRules = computed(() => {
                const selectedCountry = state.billing.country_code?.trim().toUpperCase();
                const selectedRegion = state.billing.region_code?.trim().toUpperCase();

                // If no country selected yet, don't show any rules
                if (!selectedCountry) {
                    return [];
                }

                return shipping.filter((rule) => {
                    // If rule has no regions specified, it's available everywhere
                    if (!rule.regions || rule.regions.length === 0) {
                        return true;
                    }

                    // Normalize regions array (handle both country and region codes)
                    const normalizedRegions = rule.regions.map((r) =>
                        String(r).trim().toUpperCase()
                    );

                    // Check if country matches
                    if (normalizedRegions.includes(selectedCountry)) {
                        return true;
                    }

                    // Check if region matches (for rules targeting specific states/provinces)
                    if (selectedRegion && normalizedRegions.includes(selectedRegion)) {
                        return true;
                    }

                    return false;
                });
            });

            // Auto-select first applicable shipping rule when available rules change
            watch(
                applicableShippingRules,
                (newRules) => {
                    // If current selection is no longer valid, clear it
                    const currentIsValid = newRules.some(
                        (rule) => String(rule.id) === String(state.shipping_rule_id)
                    );

                    if (!currentIsValid) {
                        state.shipping_rule_id = newRules[0]?.id || null;
                    }
                },
                { immediate: true }
            );

            // Clear region and reset shipping when country changes
            watch(
                () => state.billing.country_code,
                () => {
                    state.billing.region_code = "";
                    // Shipping rule will be auto-selected by applicableShippingRules watcher
                }
            );

            const ui = reactive({
                loading: false,
                error: "",
                success: false,
                orderNumber: "",
                orderUrl: "index.php?option=com_nxpeasycart&view=order",
            });

            const subtotal = computed(() =>
                cartItems.reduce(
                    (total, item) => total + (item.total_cents || 0),
                    0
                )
            );

            const shippingCostForRule = (rule) => {
                if (!rule) {
                    return 0;
                }

                if (
                    rule.type === "free_over" &&
                    Number.isFinite(rule.threshold_cents) &&
                    subtotal.value >= Number(rule.threshold_cents)
                ) {
                    return 0;
                }

                return Number(rule.price_cents || 0);
            };

            const selectedShippingRule = computed(() => {
                const match = applicableShippingRules.value.find(
                    (rule) => String(rule.id) === String(state.shipping_rule_id)
                );
                return match || applicableShippingRules.value[0] || null;
            });

            const selectedShippingCost = computed(() =>
                shippingCostForRule(selectedShippingRule.value)
            );

            // Use country code for tax matching
            const billingCountry = computed(() =>
                (state.billing.country_code || "").trim().toUpperCase()
            );
            const billingRegion = computed(() =>
                (state.billing.region_code || "").trim().toLowerCase()
            );

            const activeTaxRate = computed(() => {
                if (!normalisedTaxRates.length) {
                    return null;
                }

                const matches = normalisedTaxRates.filter((rate) => {
                    if (
                        billingCountry.value &&
                        rate.country &&
                        rate.country !== billingCountry.value
                    ) {
                        return false;
                    }

                    if (rate.region) {
                        return (
                            billingRegion.value !== "" &&
                            rate.region === billingRegion.value
                        );
                    }

                    if (rate.country && billingCountry.value === "") {
                        return false;
                    }

                    return true;
                });

                const globalRate =
                    normalisedTaxRates.find(
                        (rate) => !rate.country && !rate.region
                    ) || null;

                return matches[0] ?? globalRate;
            });

            const taxAmount = computed(() => {
                const rate = activeTaxRate.value;

                if (!rate || !rate.rate) {
                    return 0;
                }

                const percent = Number(rate.rate) / 100;

                return rate.inclusive
                    ? Math.round(subtotal.value - subtotal.value / (1 + percent))
                    : Math.round(subtotal.value * percent);
            });

            const taxLabel = computed(() => {
                const rate = activeTaxRate.value;

                if (!rate || !rate.rate) {
                    return "Tax";
                }

                return rate.inclusive
                    ? `Tax (${rate.rate}% incl.)`
                    : `Tax (${rate.rate}%)`;
            });

            const total = computed(
                () =>
                    subtotal.value +
                    selectedShippingCost.value +
                    (activeTaxRate.value?.inclusive ? 0 : taxAmount.value)
            );
            const showTax = computed(
                () =>
                    Boolean(activeTaxRate.value?.rate) &&
                    taxAmount.value > 0
            );

            const submit = async () => {
                ui.error = "";

                if (cartItems.length === 0) {
                    ui.error = labels.empty_cart;
                    return;
                }

                const phone = (state.billing.phone || "")
                    .replace(/\s+/g, " ")
                    .trim();

                if (phoneRequired && phone === "") {
                    ui.error = labels.phone_required;
                    return;
                }

                if (phone !== "" && (phone.length < 6 || phone.length > 20)) {
                    ui.error = labels.phone_invalid;
                    return;
                }

                ui.loading = true;

                const gateway = selectedGateway.value || gateways[0]?.id || "";
                const appliedTaxRate = activeTaxRate.value;
                const taxRateValue = appliedTaxRate?.rate
                    ? Number(appliedTaxRate.rate)
                    : 0;
                const taxRateString = taxRateValue
                    ? taxRateValue.toFixed(2)
                    : "0.00";
                const shippingCost = selectedShippingCost.value;
                const taxCents = taxAmount.value;

                // Build billing object with resolved names for storage
                const billingPayload = {
                    first_name: state.billing.first_name,
                    last_name: state.billing.last_name,
                    phone,
                    address_line1: state.billing.address_line1,
                    city: state.billing.city,
                    postcode: state.billing.postcode,
                    country_code: state.billing.country_code,
                    country: getCountryName(state.billing.country_code),
                    region_code: state.billing.region_code || "",
                    region: state.billing.region_code
                        ? getRegionName(
                              state.billing.country_code,
                              state.billing.region_code
                          )
                        : "",
                };

                const payloadBody = {
                    email: state.email,
                    billing: billingPayload,
                    shipping_rule_id: state.shipping_rule_id,
                    shipping_cents: shippingCost,
                    tax_cents: taxCents,
                    tax_rate: taxRateString,
                    tax_inclusive: Boolean(appliedTaxRate?.inclusive),
                    items: cartItems.map((item) => ({
                        sku: item.sku,
                        qty: item.qty,
                        product_id: item.product_id,
                        variant_id: item.variant_id,
                        unit_price_cents: item.unit_price_cents,
                        total_cents: item.total_cents,
                        currency,
                        title: item.title,
                        tax_rate: taxRateString,
                    })),
                    currency,
                    totals: {
                        subtotal_cents: subtotal.value,
                        shipping_cents: shippingCost,
                        tax_cents: taxCents,
                        total_cents: total.value,
                    },
                    gateway,
                };

                try {
                    if (hostedCheckoutAvailable && gateway) {
                        const response = await api.postJson(
                            endpoints.payment,
                            payloadBody
                        );
                        const envelope = response?.data ?? response ?? {};
                        const checkout =
                            envelope?.checkout ?? envelope?.data?.checkout ?? null;
                        let redirectUrl =
                            checkout?.url || checkout?.redirect || "";

                        if (!redirectUrl) {
                            const orderNo =
                                envelope?.order?.order_no ||
                                checkout?.order_no ||
                                envelope?.order_no ||
                                "";

                            if (orderNo) {
                                redirectUrl = `index.php?option=com_nxpeasycart&view=order&no=${encodeURIComponent(
                                    orderNo
                                )}`;
                            }
                        }

                        if (!redirectUrl) {
                            throw new Error(
                                "Missing checkout URL from gateway."
                            );
                        }

                        window.location.href = redirectUrl;
                        return;
                    }

                    if (!endpoints.checkout) {
                        throw new Error("Checkout endpoint unavailable.");
                    }

                    const response = await api.postJson(
                        endpoints.checkout,
                        payloadBody
                    );
                    const envelope = response?.data ?? response ?? {};
                    const order = envelope?.order ?? envelope?.data?.order ?? {};

                    ui.success = true;
                    ui.orderNumber = order.order_no || "";
                    ui.orderUrl = `index.php?option=com_nxpeasycart&view=order&no=${encodeURIComponent(
                        ui.orderNumber
                    )}`;
                    ui.error = "";
                } catch (error) {
                    const serverMessage =
                        error?.details?.message ||
                        error?.payload?.data?.message ||
                        error?.payload?.message ||
                        error?.message ||
                        "";
                    ui.error = serverMessage || labels.error_generic;
                } finally {
                    ui.loading = false;
                }
            };

            // Helper to format string replacements
            const formatShippingNoRules = (countryName) => {
                return labels.shipping_no_rules.replace("%s", countryName);
            };

            const formatOrderCreated = (orderNo) => {
                return labels.order_created.replace("%s", `<strong>${orderNo}</strong>`);
            };

            // Refresh cart data from server
            const refreshCart = async () => {
                const summaryEndpoint = endpoints.summary;
                if (!summaryEndpoint) {
                    return;
                }

                try {
                    const response = await fetch(summaryEndpoint, {
                        method: "GET",
                        headers: {
                            Accept: "application/json",
                            "X-Requested-With": "XMLHttpRequest",
                        },
                        credentials: "same-origin",
                    });

                    if (!response.ok) {
                        return;
                    }

                    const json = await response.json().catch(() => null);

                    const freshCart =
                        json?.data?.cart ||
                        json?.cart ||
                        json?.data ||
                        null;

                    if (freshCart && Array.isArray(freshCart.items)) {
                        // Clear and update cart items - must copy objects to ensure reactivity
                        cartItems.splice(0, cartItems.length, ...freshCart.items.map(item => ({ ...item })));
                    }
                } catch (error) {
                    // Non-fatal; keep existing state
                }
            };

            // Listen for cart updates from other components
            window.addEventListener("nxp-cart:updated", (event) => {
                const freshCart = event?.detail || {};
                if (freshCart.items) {
                    cartItems.splice(0, cartItems.length, ...freshCart.items);
                }
            });

            // Refresh cart on mount
            onMounted(refreshCart);

            return {
                model: state,
                cartItems,
                shippingRules: shipping,
                applicableShippingRules,
                countries,
                availableRegions,
                showRegionField,
                regionLabel,
                regionPlaceholder,
                regionRequired,
                subtotal,
                selectedShippingCost,
                taxAmount,
                taxLabel,
                showTax,
                total,
                submit,
                loading: computed(() => ui.loading),
                error: computed(() => ui.error),
                success: computed(() => ui.success),
                orderNumber: computed(() => ui.orderNumber),
                orderUrl: computed(() => ui.orderUrl),
                formatMoney: (cents) => formatMoney(cents, currency, locale),
                gateways,
                selectedGateway,
                labels,
                phoneRequired,
                phonePlaceholder,
                getCountryName,
                formatShippingNoRules,
                formatOrderCreated,
            };
        },
    });

    app.mount(el);
}
