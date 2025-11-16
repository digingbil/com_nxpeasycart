import { createApp, reactive, computed, ref } from "vue";
import parsePayload from "../utils/parsePayload.js";
import formatMoney from "../utils/formatMoney.js";
import { createApiClient } from "../utils/apiClient.js";

export default function mountCheckoutIsland(el) {
    const locale = el.dataset.nxpLocale || undefined;
    const currencyAttr = (el.dataset.nxpCurrency || "").trim() || undefined;
    const payload = parsePayload(el.dataset.nxpCheckout, {});
    const cart = payload.cart || { items: [], summary: {} };
    const shippingRules = payload.shipping_rules || [];
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
                <input id="nxp-ec-checkout-email" type="email" v-model="model.email" required />
              </div>
            </fieldset>

            <fieldset>
              <legend>Billing address</legend>
              <div class="nxp-ec-checkout__grid">
                <div class="nxp-ec-checkout__field">
                  <label for="nxp-ec-first-name">First name</label>
                  <input id="nxp-ec-first-name" type="text" v-model="model.billing.first_name" required />
                </div>
                <div class="nxp-ec-checkout__field">
                  <label for="nxp-ec-last-name">Last name</label>
                  <input id="nxp-ec-last-name" type="text" v-model="model.billing.last_name" required />
                </div>
                <div class="nxp-ec-checkout__field nxp-ec-checkout__field--wide">
                  <label for="nxp-ec-address-line1">Address</label>
                  <input id="nxp-ec-address-line1" type="text" v-model="model.billing.address_line1" required />
                </div>
                <div class="nxp-ec-checkout__field">
                  <label for="nxp-ec-city">City</label>
                  <input id="nxp-ec-city" type="text" v-model="model.billing.city" required />
                </div>
                <div class="nxp-ec-checkout__field">
                  <label for="nxp-ec-postcode">Postcode</label>
                  <input id="nxp-ec-postcode" type="text" v-model="model.billing.postcode" required />
                </div>
                <div class="nxp-ec-checkout__field">
                  <label for="nxp-ec-country">Country</label>
                  <input id="nxp-ec-country" type="text" v-model="model.billing.country" required />
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
            const shipping = shippingRules.map((rule, index) => ({
                ...rule,
                price_cents: rule.price_cents || 0,
                default: index === 0,
            }));

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

            const gateways = gatewayOptions;
            const selectedGateway = ref(gateways[0]?.id || "");
            const hostedCheckoutAvailable =
                gateways.length > 0 && Boolean(endpoints.payment);

            const state = reactive({
                email: "",
                billing: {
                    first_name: "",
                    last_name: "",
                    address_line1: "",
                    city: "",
                    postcode: "",
                    country: "",
                },
                shipping_rule_id: shipping[0]?.id || null,
            });

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

            const selectedShippingCost = computed(() => {
                const selected = shipping.find(
                    (rule) => String(rule.id) === String(state.shipping_rule_id)
                );
                return selected ? selected.price_cents : 0;
            });

            const total = computed(
                () => subtotal.value + selectedShippingCost.value
            );

            const submit = async () => {
                ui.error = "";

                if (cartItems.length === 0) {
                    ui.error = "Your cart is empty.";
                    return;
                }

                ui.loading = true;

                const gateway = selectedGateway.value || gateways[0]?.id || "";

                const payloadBody = {
                    email: state.email,
                    billing: state.billing,
                    shipping_rule_id: state.shipping_rule_id,
                    items: cartItems.map((item) => ({
                        sku: item.sku,
                        qty: item.qty,
                        product_id: item.product_id,
                        variant_id: item.variant_id,
                        unit_price_cents: item.unit_price_cents,
                        total_cents: item.total_cents,
                        currency,
                        title: item.title,
                    })),
                    currency,
                    totals: {
                        subtotal_cents: subtotal.value,
                        shipping_cents: selectedShippingCost.value,
                        total_cents: total.value,
                    },
                    gateway,
                };

                try {
                    if (hostedCheckoutAvailable && gateway) {
                        const data = await api.postJson(endpoints.payment, payloadBody);
                        const redirectUrl = data?.checkout?.url;

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

                    const data = await api.postJson(endpoints.checkout, payloadBody);
                    const order = data?.order || {};

                    ui.success = true;
                    ui.orderNumber = order.order_no || "";
                    ui.orderUrl = `index.php?option=com_nxpeasycart&view=order&no=${encodeURIComponent(ui.orderNumber)}`;
                } catch (error) {
                    ui.error =
                        error.message ||
                        "Unable to complete checkout right now.";
                } finally {
                    ui.loading = false;
                }
            };

            return {
                model: state,
                cartItems,
                shippingRules: shipping,
                subtotal,
                selectedShippingCost,
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
            };
        },
    });

    app.mount(el);
}
