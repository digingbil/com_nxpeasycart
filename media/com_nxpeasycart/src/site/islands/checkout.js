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
import { buildGatewayOptions } from "../utils/gatewayOptions.js";

export default function mountCheckoutIsland(el) {
    const locale = el.dataset.nxpLocale || undefined;
    const currencyAttr = (el.dataset.nxpCurrency || "").trim() || undefined;
    const payload = parsePayload(el.dataset.nxpCheckout, {});
    const labelsPayload = parsePayload(el.dataset.nxpLabels, {});
    const prefill = payload.prefill || payload.user || {};
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
        <!-- Floating summary bar (visible when main totals scroll out of view) -->
        <div
          class="nxp-ec-checkout__floating-summary"
          :class="{ 'is-visible': showFloatingSummary && !success }"
          aria-hidden="!showFloatingSummary"
        >
          <div class="nxp-ec-checkout__floating-summary-inner">
            <div class="nxp-ec-checkout__floating-row">
              <span>{{ labels.subtotal }}</span>
              <strong>{{ formatMoney(subtotal) }}</strong>
            </div>
            <div class="nxp-ec-checkout__floating-row" v-if="requiresShipping">
              <span>{{ labels.shipping }}</span>
              <strong>{{ formatMoney(selectedShippingCost) }}</strong>
            </div>
            <div class="nxp-ec-checkout__floating-row" v-if="showTax">
              <span>{{ taxLabel }}</span>
              <strong>{{ formatMoney(taxAmount) }}</strong>
            </div>
            <div class="nxp-ec-checkout__floating-row nxp-ec-checkout__floating-row--total">
              <span>{{ labels.total }}</span>
              <strong>{{ formatMoney(total) }}</strong>
            </div>
          </div>
        </div>

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
                <li v-for="item in cartItems" :key="item.id" class="nxp-ec-checkout__item">
                  <div v-if="item.image" class="nxp-ec-checkout__item-image">
                    <img :src="item.image" :alt="item.product_title || item.title" loading="lazy" />
                  </div>
                  <div class="nxp-ec-checkout__item-details">
                    <div>
                      <strong>{{ item.product_title || item.title }}</strong>
                      <span
                        v-if="item.is_on_sale"
                        class="nxp-ec-checkout__badge nxp-ec-checkout__badge--sale"
                      >{{ labels.sale_badge }}</span>
                      <span
                        v-if="item.is_digital"
                        class="nxp-ec-checkout__badge"
                      >{{ labels.digital_badge }}</span>
                      <span class="nxp-ec-checkout__qty">× {{ item.qty }}</span>
                    </div>
                    <div class="nxp-ec-checkout__price" :class="{ 'nxp-ec-checkout__price--sale': item.is_on_sale }">
                      <template v-if="item.is_on_sale">
                        <span class="nxp-ec-checkout__regular-price">{{ formatMoney(item.regular_total_cents || item.regular_price_cents * item.qty) }}</span>
                        <span class="nxp-ec-checkout__sale-price">{{ formatMoney(item.total_cents) }}</span>
                      </template>
                      <template v-else>{{ formatMoney(item.total_cents) }}</template>
                    </div>
                  </div>
                </li>
              </ul>

              <div class="nxp-ec-checkout__coupon">
                <div v-if="coupon" class="nxp-ec-checkout__coupon-applied">
                  <span class="nxp-ec-checkout__coupon-code">
                    <strong>{{ coupon.code }}</strong>
                  </span>
                  <button type="button" class="nxp-ec-btn nxp-ec-btn--ghost" @click="removeCoupon" :disabled="couponLoading">
                    {{ labels.coupon_remove }}
                  </button>
                </div>
                <details v-else class="nxp-ec-checkout__coupon-form">
                  <summary>{{ labels.coupon_label }}</summary>
                  <div class="nxp-ec-checkout__coupon-input-group">
                    <div class="nxp-ec-checkout__field">
                      <input
                        type="text"
                        v-model="couponCode"
                        :placeholder="labels.coupon_placeholder"
                        @keyup.enter="applyCoupon"
                        :disabled="couponLoading"
                        autocomplete="off"
                      />
                    </div>
                    <button type="button" class="nxp-ec-btn nxp-ec-btn--ghost" @click="applyCoupon" :disabled="couponLoading || !couponCode.trim()">
                      {{ labels.coupon_apply }}
                    </button>
                  </div>
                  <div v-if="couponMessage" class="nxp-ec-checkout__coupon-message" :class="{ 'nxp-ec-checkout__coupon-message--success': coupon }">
                    {{ couponMessage }}
                  </div>
                </details>
              </div>

              <div ref="totalsRef" class="nxp-ec-checkout__totals">
                <div>
                  <span>{{ labels.subtotal }}</span>
                  <strong>{{ formatMoney(subtotal) }}</strong>
                </div>
                <div v-if="saleSavings > 0" class="nxp-ec-checkout__totals-savings">
                  <span>{{ labels.sale_savings }}</span>
                  <strong class="nxp-ec-checkout__savings-amount">-{{ formatMoney(saleSavings) }}</strong>
                </div>
                <div v-if="discountCents > 0">
                  <span>{{ labels.discount }}</span>
                  <strong>-{{ formatMoney(discountCents) }}</strong>
                </div>
                <div v-if="requiresShipping">
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
                <div class="nxp-ec-checkout__field" v-if="showBillingRegionField">
                  <label for="nxp-ec-region">{{ billingRegionLabel }}</label>
                  <select
                    id="nxp-ec-region"
                    v-model="model.billing.region_code"
                    :required="billingRegionRequired"
                    autocomplete="address-level1"
                    class="nxp-ec-checkout__select"
                  >
                    <option value="">{{ billingRegionPlaceholder }}</option>
                    <option
                      v-for="region in billingAvailableRegions"
                      :key="region.code"
                      :value="region.code"
                    >{{ region.name }}</option>
                  </select>
                </div>
              </div>
            </fieldset>

            <fieldset v-if="requiresShipping">
              <legend>{{ labels.shipping_address }}</legend>
              <div class="nxp-ec-checkout__field">
                <label class="nxp-ec-checkout__checkbox">
                  <input type="checkbox" v-model="model.shipToDifferent" />
                  {{ labels.ship_to_different }}
                </label>
              </div>

              <div v-if="model.shipToDifferent">
                <div class="nxp-ec-checkout__grid">
                  <div class="nxp-ec-checkout__field">
                    <label for="nxp-ec-shipping-first-name">{{ labels.first_name }}</label>
                    <input
                      id="nxp-ec-shipping-first-name"
                      type="text"
                      v-model="model.shipping.first_name"
                      required
                      autocomplete="shipping given-name"
                    />
                  </div>
                  <div class="nxp-ec-checkout__field">
                    <label for="nxp-ec-shipping-last-name">{{ labels.last_name }}</label>
                    <input
                      id="nxp-ec-shipping-last-name"
                      type="text"
                      v-model="model.shipping.last_name"
                      required
                      autocomplete="shipping family-name"
                    />
                  </div>
                  <div class="nxp-ec-checkout__field">
                    <label for="nxp-ec-shipping-phone">{{ labels.phone }}</label>
                    <input
                      id="nxp-ec-shipping-phone"
                      type="tel"
                      v-model.trim="model.shipping.phone"
                      :placeholder="phonePlaceholder"
                      inputmode="tel"
                      autocomplete="shipping tel"
                    />
                  </div>
                  <div class="nxp-ec-checkout__field nxp-ec-checkout__field--wide">
                    <label for="nxp-ec-shipping-address">{{ labels.address }}</label>
                    <input
                      id="nxp-ec-shipping-address"
                      type="text"
                      v-model="model.shipping.address_line1"
                      required
                      autocomplete="shipping street-address"
                    />
                  </div>
                  <div class="nxp-ec-checkout__field">
                    <label for="nxp-ec-shipping-city">{{ labels.city }}</label>
                    <input
                      id="nxp-ec-shipping-city"
                      type="text"
                      v-model="model.shipping.city"
                      required
                      autocomplete="shipping address-level2"
                    />
                  </div>
                  <div class="nxp-ec-checkout__field">
                    <label for="nxp-ec-shipping-postcode">{{ labels.postcode }}</label>
                    <input
                      id="nxp-ec-shipping-postcode"
                      type="text"
                      v-model="model.shipping.postcode"
                      required
                      autocomplete="shipping postal-code"
                    />
                  </div>
                  <div class="nxp-ec-checkout__field">
                    <label for="nxp-ec-shipping-country">{{ labels.country }}</label>
                    <select
                      id="nxp-ec-shipping-country"
                      v-model="model.shipping.country_code"
                      required
                      autocomplete="shipping country"
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
                  <div class="nxp-ec-checkout__field" v-if="showShippingRegionField">
                    <label for="nxp-ec-shipping-region">{{ shippingRegionLabel }}</label>
                    <select
                      id="nxp-ec-shipping-region"
                      v-model="model.shipping.region_code"
                      :required="shippingRegionRequired"
                      autocomplete="shipping address-level1"
                      class="nxp-ec-checkout__select"
                    >
                      <option value="">{{ shippingRegionPlaceholder }}</option>
                      <option
                        v-for="region in shippingAvailableRegions"
                        :key="region.code"
                        :value="region.code"
                      >{{ region.name }}</option>
                    </select>
                  </div>
                </div>
              </div>
            </fieldset>

            <fieldset v-if="requiresShipping">
              <legend>{{ labels.shipping }}</legend>
              <p class="nxp-ec-checkout__radio-group">
                <template v-if="!destinationCountry">
                  <span class="nxp-ec-checkout__notice">{{ labels.shipping_select_country }}</span>
                </template>
                <template v-else-if="applicableShippingRules.length === 0">
                  <span class="nxp-ec-checkout__notice">{{ formatShippingNoRules(getCountryName(destinationCountry)) }}</span>
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
          <p>
            <span v-for="(part, index) in formatOrderCreatedParts(orderNumber)" :key="index">
              <strong v-if="part.bold">{{ part.text }}</strong>
              <template v-else>{{ part.text }}</template>
            </span>
          </p>
          <a class="nxp-ec-btn" :href="orderUrl">{{ labels.view_order }}</a>
        </div>
      </div>
    `,
        setup() {
            const cartItems = reactive(
                (cart.items || []).map((item) => ({ ...item }))
            );
            const initialHasPhysical =
                cart.has_physical !== undefined
                    ? Boolean(cart.has_physical)
                    : cart.requires_shipping !== undefined
                        ? Boolean(cart.requires_shipping)
                        : (cart.items || []).some((item) => !item.is_digital);
            const initialHasDigital =
                cart.has_digital !== undefined
                    ? Boolean(cart.has_digital)
                    : (cart.items || []).some((item) => item.is_digital);
            const cartFlags = reactive({
                hasPhysical: initialHasPhysical,
                hasDigital: initialHasDigital,
            });
            const currency =
                cart.summary?.currency ||
                settings.base_currency ||
                currencyAttr ||
                "USD";
            const coupon = ref(cart.coupon || null);
            const couponCode = ref("");
            const couponMessage = ref("");
            const couponLoading = ref(false);
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
                    name: rate.name || null,
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

            const gateways = buildGatewayOptions(payments, cartItems);
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

            const normaliseString = (value) =>
                typeof value === "string" ? value.trim() : "";

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
            const hasPhysicalItems = computed(
                () => {
                    const itemHasPhysical = cartItems.some(
                        (item) => !item.is_digital
                    );
                    return cartItems.length
                        ? itemHasPhysical || cartFlags.hasPhysical
                        : cartFlags.hasPhysical;
                }
            );
            const hasDigitalItems = computed(
                () => {
                    const itemHasDigital = cartItems.some(
                        (item) => item.is_digital
                    );
                    return cartItems.length
                        ? itemHasDigital || cartFlags.hasDigital
                        : cartFlags.hasDigital;
                }
            );
            const requiresShipping = computed(() => hasPhysicalItems.value);
            const isDigitalOnly = computed(
                () => hasDigitalItems.value && !hasPhysicalItems.value
            );

            // Country/Region data
            const countries = getCountries();

            const initialEmail = normaliseString(prefill.email || "");
            const initialFirstName = normaliseString(prefill.first_name || "");
            const initialLastName = normaliseString(prefill.last_name || "");

            const state = reactive({
                email: initialEmail,
                billing: {
                    first_name: initialFirstName,
                    last_name: initialLastName,
                    phone: "",
                    address_line1: "",
                    city: "",
                    postcode: "",
                    country_code: "",
                    region_code: "",
                },
                shipping: {
                    first_name: initialFirstName,
                    last_name: initialLastName,
                    phone: "",
                    address_line1: "",
                    city: "",
                    postcode: "",
                    country_code: "",
                    region_code: "",
                },
                shipToDifferent: false,
                shipping_rule_id: null,
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
                shipping_address: labelsPayload.shipping_address || "Shipping address",
                ship_to_different: labelsPayload.ship_to_different || "Ship to a different address",
                shipping_select_country: labelsPayload.shipping_select_country || "Please select your country above to see available shipping options.",
                shipping_no_rules: labelsPayload.shipping_no_rules || "No shipping available to %s. Please contact us for assistance.",
                shipping_required: labelsPayload.shipping_required || "Please enter a shipping address.",
                payment_method: labelsPayload.payment_method || "Payment method",
                payment_offline: labelsPayload.payment_offline || "Payments will be captured offline once this order is submitted.",
                processing: labelsPayload.processing || "Processing…",
                submit: labelsPayload.submit || "Complete order",
                order_summary: labelsPayload.order_summary || "Order summary",
                subtotal: labelsPayload.subtotal || "Subtotal",
                discount: labelsPayload.discount || "Discount",
                total: labelsPayload.total || "Total",
                empty_cart: labelsPayload.empty_cart || "Your cart is empty.",
                thank_you: labelsPayload.thank_you || "Thank you!",
                order_created: labelsPayload.order_created || "Your order %s was created successfully.",
                view_order: labelsPayload.view_order || "View order summary",
                error_generic: labelsPayload.error_generic || "Unable to complete checkout right now.",
                coupon_label: labelsPayload.coupon_label || "Have a coupon code?",
                coupon_placeholder: labelsPayload.coupon_placeholder || "Enter code",
                coupon_apply: labelsPayload.coupon_apply || "Apply",
                coupon_remove: labelsPayload.coupon_remove || "Remove",
                coupon_applied: labelsPayload.coupon_applied || "Coupon applied!",
                coupon_removed: labelsPayload.coupon_removed || "Coupon removed.",
                coupon_code_required: labelsPayload.coupon_code_required || "Please enter a coupon code.",
                digital_badge: labelsPayload.digital_badge || "Instant download",
                digital_note:
                    labelsPayload.digital_note ||
                    "No shipping needed for digital items.",
                sale_badge: labelsPayload.sale_badge || "Sale",
                sale_savings: labelsPayload.sale_savings || "Sale savings",
            };

            const updateCartFlags = (dataCart) => {
                if (!dataCart) {
                    return;
                }

                if (dataCart.has_physical !== undefined) {
                    cartFlags.hasPhysical = Boolean(dataCart.has_physical);
                }

                if (dataCart.requires_shipping !== undefined) {
                    cartFlags.hasPhysical = Boolean(dataCart.requires_shipping);
                }

                if (dataCart.has_digital !== undefined) {
                    cartFlags.hasDigital = Boolean(dataCart.has_digital);
                }

                if (
                    dataCart.has_physical === undefined &&
                    dataCart.requires_shipping === undefined &&
                    Array.isArray(dataCart.items)
                ) {
                    cartFlags.hasPhysical = dataCart.items.some(
                        (item) => !item.is_digital
                    );
                }

                if (
                    dataCart.has_digital === undefined &&
                    Array.isArray(dataCart.items)
                ) {
                    cartFlags.hasDigital = dataCart.items.some(
                        (item) => item.is_digital
                    );
                }
            };

            const resolveRegionLabel = (countryCode) => {
                const code = (countryCode || "").toUpperCase();
                if (code === "US") return labels.region_state;
                if (code === "CA") return labels.region_province;
                if (code === "AU") return labels.region_territory;
                if (code === "GB" || code === "UK") return labels.region_county;
                return labels.region;
            };

            const resolveRegionPlaceholder = (countryCode) => {
                const code = (countryCode || "").toUpperCase();
                if (code === "US") return labels.select_state;
                if (code === "CA") return labels.select_province;
                return labels.select_region;
            };

            const isRegionRequired = (countryCode) => {
                const code = (countryCode || "").toUpperCase();
                return ["US", "CA", "AU", "IN", "BR", "MX"].includes(code);
            };

            const billingAvailableRegions = computed(() => {
                if (!state.billing.country_code) {
                    return [];
                }
                return getRegions(state.billing.country_code);
            });

            const shippingAvailableRegions = computed(() => {
                if (!state.shipping.country_code) {
                    return [];
                }
                return getRegions(state.shipping.country_code);
            });

            const showBillingRegionField = computed(() => {
                return (
                    state.billing.country_code &&
                    hasRegions(state.billing.country_code)
                );
            });

            const showShippingRegionField = computed(() => {
                return (
                    state.shipToDifferent &&
                    state.shipping.country_code &&
                    hasRegions(state.shipping.country_code)
                );
            });

            const billingRegionLabel = computed(() =>
                resolveRegionLabel(state.billing.country_code)
            );
            const shippingRegionLabel = computed(() =>
                resolveRegionLabel(state.shipping.country_code)
            );

            const billingRegionPlaceholder = computed(() =>
                resolveRegionPlaceholder(state.billing.country_code)
            );
            const shippingRegionPlaceholder = computed(() =>
                resolveRegionPlaceholder(state.shipping.country_code)
            );

            const billingRegionRequired = computed(() =>
                isRegionRequired(state.billing.country_code)
            );
            const shippingRegionRequired = computed(
                () =>
                    state.shipToDifferent &&
                    isRegionRequired(state.shipping.country_code)
            );

            const isShippingEmpty = () =>
                !state.shipping.first_name &&
                !state.shipping.last_name &&
                !state.shipping.address_line1 &&
                !state.shipping.city &&
                !state.shipping.postcode &&
                !state.shipping.country_code &&
                !state.shipping.region_code &&
                !state.shipping.phone;

            const copyBillingToShipping = () => {
                state.shipping.first_name = state.billing.first_name;
                state.shipping.last_name = state.billing.last_name;
                state.shipping.phone = state.billing.phone;
                state.shipping.address_line1 = state.billing.address_line1;
                state.shipping.city = state.billing.city;
                state.shipping.postcode = state.billing.postcode;
                state.shipping.country_code = state.billing.country_code;
                state.shipping.region_code = state.billing.region_code;
            };

            const destination = computed(() =>
                state.shipToDifferent ? state.shipping : state.billing
            );
            const destinationCountry = computed(() =>
                (destination.value.country_code || "").trim().toUpperCase()
            );
            const destinationRegion = computed(() =>
                (destination.value.region_code || "").trim().toUpperCase()
            );

            // Filter shipping rules based on selected country/region
            const applicableShippingRules = computed(() => {
                if (!requiresShipping.value) {
                    return [];
                }

                const selectedCountry = destinationCountry.value;
                const selectedRegion = destinationRegion.value;

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

            watch(requiresShipping, (required) => {
                if (!required) {
                    state.shipping_rule_id = null;
                    state.shipToDifferent = false;
                }
            });

            // Clear region and reset shipping when country changes
            watch(
                () => state.billing.country_code,
                () => {
                    state.billing.region_code = "";
                    // Shipping rule will be auto-selected by applicableShippingRules watcher
                }
            );

            watch(
                () => state.shipping.country_code,
                () => {
                    state.shipping.region_code = "";
                }
            );

            watch(
                () => state.shipToDifferent,
                (enabled) => {
                    if (enabled && isShippingEmpty()) {
                        copyBillingToShipping();
                    }
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

            // Calculate total sale savings (difference between regular and sale prices)
            const saleSavings = computed(() => {
                return cartItems.reduce((total, item) => {
                    if (item.is_on_sale && item.regular_price_cents > item.unit_price_cents) {
                        const savingsPerItem = item.regular_price_cents - item.unit_price_cents;
                        return total + (savingsPerItem * item.qty);
                    }
                    return total;
                }, 0);
            });

            const discountCents = computed(() => {
                return coupon.value?.discount_cents || 0;
            });

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
                if (!requiresShipping.value) {
                    return null;
                }

                const match = applicableShippingRules.value.find(
                    (rule) => String(rule.id) === String(state.shipping_rule_id)
                );
                return match || applicableShippingRules.value[0] || null;
            });

            const selectedShippingCost = computed(() =>
                requiresShipping.value
                    ? shippingCostForRule(selectedShippingRule.value)
                    : 0
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
                const taxableAmount = Math.max(0, subtotal.value - discountCents.value);

                return rate.inclusive
                    ? Math.round(taxableAmount - taxableAmount / (1 + percent))
                    : Math.round(taxableAmount * percent);
            });

            const taxLabel = computed(() => {
                const rate = activeTaxRate.value;

                if (!rate || !rate.rate) {
                    return "Tax";
                }

                const label = rate.name || "Tax";
                return rate.inclusive
                    ? `${label} (${rate.rate}% incl.)`
                    : `${label} (${rate.rate}%)`;
            });

            const total = computed(
                () =>
                    subtotal.value -
                    discountCents.value +
                    selectedShippingCost.value +
                    (activeTaxRate.value?.inclusive ? 0 : taxAmount.value)
            );
            const showTax = computed(
                () =>
                    Boolean(activeTaxRate.value?.rate) &&
                    taxAmount.value > 0
            );

            const applyCoupon = async () => {
                const code = couponCode.value.trim().toUpperCase();
                if (!code) {
                    couponMessage.value = labels.coupon_code_required || "Please enter a coupon code.";
                    return;
                }

                couponLoading.value = true;
                couponMessage.value = "";

                try {
                    const response = await api.postJson(endpoints.applyCoupon, { code });
                    const data = response?.data || response;

                    if (data.cart && data.cart.coupon) {
                        coupon.value = data.cart.coupon;
                        couponCode.value = "";
                        couponMessage.value = data.message || labels.coupon_applied || "Coupon applied!";

                        // Update cart items if returned
                        if (data.cart.items) {
                            cartItems.splice(0, cartItems.length, ...data.cart.items.map(item => ({ ...item })));
                        }

                        updateCartFlags(data.cart);
                    }
                } catch (error) {
                    const serverMessage =
                        error?.details?.message ||
                        error?.payload?.data?.message ||
                        error?.payload?.message ||
                        error?.message ||
                        "";
                    couponMessage.value = serverMessage || labels.error_generic || "Unable to apply coupon.";
                } finally {
                    couponLoading.value = false;
                }
            };

            const removeCoupon = async () => {
                couponLoading.value = true;
                couponMessage.value = "";

                try {
                    const response = await api.postJson(endpoints.removeCoupon, {});
                    const data = response?.data || response;

                    coupon.value = null;
                    couponMessage.value = data.message || labels.coupon_removed || "Coupon removed.";

                    // Update cart items if returned
                    if (data.cart && data.cart.items) {
                        cartItems.splice(0, cartItems.length, ...data.cart.items.map(item => ({ ...item })));
                    }
                    updateCartFlags(data.cart);
                } catch (error) {
                    const serverMessage =
                        error?.details?.message ||
                        error?.payload?.data?.message ||
                        error?.payload?.message ||
                        error?.message ||
                        "";
                    couponMessage.value = serverMessage || labels.error_generic || "Unable to remove coupon.";
                } finally {
                    couponLoading.value = false;
                }
            };

            const buildAddressPayload = (source, phoneOverride = null) => {
                const countryCode = normaliseString(source.country_code || "");
                const regionCode = normaliseString(source.region_code || "");
                const phoneValue =
                    phoneOverride !== null
                        ? phoneOverride
                        : normaliseString(source.phone || "");

                return {
                    first_name: normaliseString(source.first_name || ""),
                    last_name: normaliseString(source.last_name || ""),
                    phone: phoneValue,
                    address_line1: normaliseString(source.address_line1 || ""),
                    city: normaliseString(source.city || ""),
                    postcode: normaliseString(source.postcode || ""),
                    country_code: countryCode,
                    country: getCountryName(countryCode),
                    region_code: regionCode,
                    region: regionCode
                        ? getRegionName(countryCode, regionCode)
                        : "",
                };
            };

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

                if (requiresShipping.value && state.shipToDifferent) {
                    const requiredShippingFields = [
                        "first_name",
                        "last_name",
                        "address_line1",
                        "city",
                        "postcode",
                        "country_code",
                    ];

                    const missingShippingField = requiredShippingFields.find(
                        (field) => normaliseString(state.shipping[field] || "") === ""
                    );

                    if (missingShippingField) {
                        ui.error = labels.shipping_required;
                        return;
                    }

                    if (
                        shippingRegionRequired.value &&
                        normaliseString(state.shipping.region_code || "") === ""
                    ) {
                        ui.error = labels.shipping_required;
                        return;
                    }
                } else if (!requiresShipping.value) {
                    state.shipToDifferent = false;
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
                const shippingCost = requiresShipping.value
                    ? selectedShippingCost.value
                    : 0;
                const taxCents = taxAmount.value;

                // Build billing object with resolved names for storage
                const billingPayload = buildAddressPayload(state.billing, phone);
                const shippingPayload = requiresShipping.value
                    ? buildAddressPayload(
                          state.shipToDifferent ? state.shipping : state.billing,
                          state.shipToDifferent ? null : phone
                      )
                    : null;

                const payloadBody = {
                    email: normaliseString(state.email),
                    billing: billingPayload,
                    shipping: shippingPayload,
                    ship_to_different: requiresShipping.value
                        ? state.shipToDifferent
                        : false,
                    shipping_rule_id: requiresShipping.value
                        ? state.shipping_rule_id
                        : null,
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
                    // Honeypot fields kept blank intentionally; server requires presence and emptiness.
                    company_website: "",
                    website: "",
                    url: "",
                    honeypot: "",
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

            const formatOrderCreatedParts = (orderNo) => {
                // Safely split the message and mark the order number as bold
                // This prevents XSS by avoiding v-html and using Vue's safe text interpolation
                const template = labels.order_created || "Your order %s was created successfully.";
                const parts = [];
                const marker = "%s";
                const index = template.indexOf(marker);

                if (index === -1) {
                    // No marker found, return whole message as plain text
                    parts.push({ text: template, bold: false });
                } else {
                    // Split around the marker
                    if (index > 0) {
                        parts.push({ text: template.substring(0, index), bold: false });
                    }
                    parts.push({ text: String(orderNo || ""), bold: true });
                    if (index + marker.length < template.length) {
                        parts.push({ text: template.substring(index + marker.length), bold: false });
                    }
                }

                return parts;
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
                        updateCartFlags(freshCart);
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
                updateCartFlags(freshCart);
            });

            // Floating summary visibility
            const totalsRef = ref(null);
            const showFloatingSummary = ref(false);

            // Refresh cart on mount and setup IntersectionObserver for floating summary
            onMounted(() => {
                refreshCart();

                // Setup IntersectionObserver to show/hide floating summary
                if (totalsRef.value && "IntersectionObserver" in window) {
                    const observer = new IntersectionObserver(
                        (entries) => {
                            // Show floating summary when totals section is NOT visible
                            showFloatingSummary.value = !entries[0].isIntersecting;
                        },
                        {
                            root: null,
                            rootMargin: "-80px 0px 0px 0px", // Account for potential fixed headers
                            threshold: 0,
                        }
                    );
                    observer.observe(totalsRef.value);
                }
            });

            return {
                totalsRef,
                showFloatingSummary,
                model: state,
                cartItems,
                shippingRules: shipping,
                applicableShippingRules,
                countries,
                billingAvailableRegions,
                shippingAvailableRegions,
                showBillingRegionField,
                showShippingRegionField,
                billingRegionLabel,
                shippingRegionLabel,
                billingRegionPlaceholder,
                shippingRegionPlaceholder,
                billingRegionRequired,
                shippingRegionRequired,
                destinationCountry,
                requiresShipping,
                isDigitalOnly,
                subtotal,
                saleSavings,
                discountCents,
                selectedShippingCost,
                taxAmount,
                taxLabel,
                showTax,
                total,
                coupon,
                couponCode,
                couponMessage,
                couponLoading,
                applyCoupon,
                removeCoupon,
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
                formatOrderCreatedParts,
            };
        },
    });

    app.mount(el);
}
