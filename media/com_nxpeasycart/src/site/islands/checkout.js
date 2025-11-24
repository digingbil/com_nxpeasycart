import { createApp, reactive, computed, ref, watch } from "vue";
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
    const cart = payload.cart || { items: [], summary: {} };
    const shippingRules = payload.shipping_rules || [];
    const taxRates = payload.tax_rates || [];
    const settings = payload.settings || {};
    const payments = payload.payments || {};
    const endpoints = payload.endpoints || {};
    const token = payload.token || "";
    const i18n = payload.i18n || {};
    const api = createApiClient(token);

    el.innerHTML = "";

    const app = createApp({
        template: `
      <div class="nxp-ec-checkout" v-cloak>
        <header class="nxp-ec-checkout__header">
          <h1 class="nxp-ec-checkout__title">Checkout</h1>
          <p class="nxp-ec-checkout__lead">
            Enter your details to complete the order.
          </p>
        </header>

        <div class="nxp-ec-checkout__layout" v-if="!success">
          <form class="nxp-ec-checkout__form" @submit.prevent="submit">
            <fieldset>
              <legend>Contact</legend>
              <div class="nxp-ec-checkout__field">
                <label for="nxp-ec-checkout-email">Email</label>
                <input id="nxp-ec-checkout-email" type="email" v-model="model.email" required autocomplete="email" />
              </div>
            </fieldset>

            <fieldset>
              <legend>Billing address</legend>
              <div class="nxp-ec-checkout__grid">
                <div class="nxp-ec-checkout__field">
                  <label for="nxp-ec-first-name">First name</label>
                  <input id="nxp-ec-first-name" type="text" v-model="model.billing.first_name" required autocomplete="given-name" />
                </div>
                <div class="nxp-ec-checkout__field">
                  <label for="nxp-ec-last-name">Last name</label>
                  <input id="nxp-ec-last-name" type="text" v-model="model.billing.last_name" required autocomplete="family-name" />
                </div>
                <div class="nxp-ec-checkout__field nxp-ec-checkout__field--wide">
                  <label for="nxp-ec-address-line1">Address</label>
                  <input id="nxp-ec-address-line1" type="text" v-model="model.billing.address_line1" required autocomplete="street-address" />
                </div>
                <div class="nxp-ec-checkout__field">
                  <label for="nxp-ec-city">City</label>
                  <input id="nxp-ec-city" type="text" v-model="model.billing.city" required autocomplete="address-level2" />
                </div>
                <div class="nxp-ec-checkout__field">
                  <label for="nxp-ec-postcode">Postcode</label>
                  <input id="nxp-ec-postcode" type="text" v-model="model.billing.postcode" required autocomplete="postal-code" />
                </div>
                <div class="nxp-ec-checkout__field">
                  <label for="nxp-ec-country">{{ t.country }}</label>
                  <select
                    id="nxp-ec-country"
                    v-model="model.billing.country_code"
                    required
                    autocomplete="country"
                    class="nxp-ec-checkout__select"
                  >
                    <option value="" disabled>{{ t.select_country }}</option>
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
              <legend>Shipping</legend>
              <p class="nxp-ec-checkout__radio-group">
                <label
                  v-for="rule in shippingRules"
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
                <span v-if="shippingRules.length === 0">No shipping rules configured yet.</span>
              </p>
            </fieldset>

            <fieldset>
              <legend>Payment method</legend>
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
                Payments will be captured offline once this order is submitted.
              </p>
            </fieldset>

            <div v-if="error" class="nxp-ec-admin-alert nxp-ec-admin-alert--error">
              {{ error }}
            </div>

            <button type="submit" class="nxp-ec-btn nxp-ec-btn--primary" :disabled="loading">
              <span v-if="loading">Processing…</span>
              <span v-else>Complete order</span>
            </button>
          </form>

          <aside class="nxp-ec-checkout__summary">
            <h2>Order summary</h2>
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
                  <span>Subtotal</span>
                  <strong>{{ formatMoney(subtotal) }}</strong>
                </div>
                <div>
                  <span>Shipping</span>
                  <strong>{{ formatMoney(selectedShippingCost) }}</strong>
                </div>
                <div v-if="showTax">
                  <span>{{ taxLabel }}</span>
                  <strong>{{ formatMoney(taxAmount) }}</strong>
                </div>
                <div>
                  <span>Total</span>
                  <strong>{{ formatMoney(total) }}</strong>
                </div>
              </div>
            </div>
            <p v-else>Your cart is empty.</p>
          </aside>
        </div>

        <div v-else class="nxp-ec-order-confirmation__summary">
          <h2>Thank you!</h2>
          <p>Your order <strong>{{ orderNumber }}</strong> was created successfully.</p>
          <a class="nxp-ec-btn" :href="orderUrl">View order summary</a>
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

            // Country/Region data
            const countries = getCountries();

            const state = reactive({
                email: "",
                billing: {
                    first_name: "",
                    last_name: "",
                    address_line1: "",
                    city: "",
                    postcode: "",
                    country_code: "",
                    region_code: "",
                },
                shipping_rule_id: shipping[0]?.id || null,
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

            // Dynamic label based on country (State for US, Province for CA, Region otherwise)
            const regionLabel = computed(() => {
                const code = state.billing.country_code;
                if (code === "US") return i18n.region_state || "State";
                if (code === "CA") return i18n.region_province || "Province";
                if (code === "AU") return i18n.region_territory || "State/Territory";
                if (code === "GB" || code === "UK") return i18n.region_county || "County";
                return i18n.region || "Region/State";
            });

            const regionPlaceholder = computed(() => {
                const code = state.billing.country_code;
                if (code === "US") return i18n.select_state || "Select state...";
                if (code === "CA") return i18n.select_province || "Select province...";
                return i18n.select_region || "Select region...";
            });

            // Region is required for countries with subdivisions
            const regionRequired = computed(() => {
                const code = state.billing.country_code;
                // Make region required for major countries with tax implications
                return ["US", "CA", "AU", "IN", "BR", "MX"].includes(code);
            });

            // Clear region when country changes
            watch(
                () => state.billing.country_code,
                () => {
                    state.billing.region_code = "";
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
                const match = shipping.find(
                    (rule) => String(rule.id) === String(state.shipping_rule_id)
                );
                return match || shipping[0] || null;
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
                    ui.error = "Your cart is empty.";
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
                ui.orderUrl = `index.php?option=com_nxpeasycart&view=order&no=${encodeURIComponent(ui.orderNumber)}`;
                ui.error = "";
            } catch (error) {
                const serverMessage =
                    error?.details?.message ||
                    error?.payload?.data?.message ||
                    error?.payload?.message ||
                    error?.message ||
                    "";
                ui.error =
                    serverMessage ||
                    "Unable to complete checkout right now.";
            } finally {
                ui.loading = false;
                }
            };

            // Translation helper with fallbacks
            const t = {
                country: i18n.country || "Country",
                select_country: i18n.select_country || "Select country...",
            };

            return {
                model: state,
                cartItems,
                shippingRules: shipping,
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
                t,
            };
        },
    });

    app.mount(el);
}
