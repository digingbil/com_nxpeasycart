import { createApp, reactive, computed, ref } from "vue";

const formatMoney = (cents, currency) => {
    const amount = (cents || 0) / 100;

    try {
        return new Intl.NumberFormat(undefined, {
            style: "currency",
            currency: currency || "USD",
            minimumFractionDigits: 2,
        }).format(amount);
    } catch (error) {
        const symbol = currency ? `${currency} ` : "";
        return `${symbol}${amount.toFixed(2)}`;
    }
};

const parsePayload = (value, fallback = {}) => {
    if (!value) {
        return fallback;
    }

    try {
        return JSON.parse(value);
    } catch (error) {
        console.warn("[NXP Easy Cart] Failed to parse island payload", error);
        return fallback;
    }
};

const mountCategoryIsland = (el) => {
    const category = parsePayload(el.dataset.nxpCategory, {});
    const products = parsePayload(el.dataset.nxpProducts, []);

    el.innerHTML = "";

    const app = createApp({
        template: `
      <div class="nxp-category" v-cloak>
        <header class="nxp-category__header">
          <h1 class="nxp-category__title">{{ title }}</h1>
          <div class="nxp-category__search">
            <input
              type="search"
              class="nxp-admin-search"
              v-model="search"
              :placeholder="searchPlaceholder"
            />
          </div>
        </header>

        <p v-if="filteredProducts.length === 0" class="nxp-category__empty">
          {{ emptyCopy }}
        </p>

        <div v-else class="nxp-category__grid">
          <article
            v-for="product in filteredProducts"
            :key="product.id"
            class="nxp-product-card"
          >
            <figure v-if="product.images && product.images.length" class="nxp-product-card__media">
              <img :src="product.images[0]" :alt="product.title" loading="lazy" />
            </figure>
            <div class="nxp-product-card__body">
              <h2 class="nxp-product-card__title">
                <a :href="product.link">{{ product.title }}</a>
              </h2>
              <p v-if="product.short_desc" class="nxp-product-card__intro">
                {{ product.short_desc }}
              </p>
              <a class="nxp-btn nxp-btn--ghost" :href="product.link">
                {{ viewCopy }}
              </a>
            </div>
          </article>
        </div>
      </div>
    `,
        setup() {
            const title = category?.title || "Products";
            const search = ref("");

            const filteredProducts = computed(() => {
                if (!search.value) {
                    return products;
                }

                const query = search.value.toLowerCase();

                return products.filter((product) => {
                    const haystack =
                        `${product.title} ${product.short_desc || ""}`.toLowerCase();
                    return haystack.includes(query);
                });
            });

            return {
                title,
                search,
                filteredProducts,
                searchPlaceholder: "Search products",
                emptyCopy: "No products found in this category yet.",
                viewCopy: "View product",
            };
        },
    });

    app.mount(el);
};

const mountCartIsland = (el) => {
    const payload = parsePayload(el.dataset.nxpCart, {
        items: [],
        summary: {},
    });

    el.innerHTML = "";

    const app = createApp({
        template: `
      <div class="nxp-cart" v-cloak>
        <header class="nxp-cart__header">
          <h1 class="nxp-cart__title">Your cart</h1>
          <p class="nxp-cart__lead">
            Review your items and proceed to checkout.
          </p>
        </header>

        <div v-if="items.length === 0" class="nxp-cart__empty">
          <p>Your cart is currently empty.</p>
          <a class="nxp-btn" href="index.php?option=com_nxpeasycart&view=category">
            Continue browsing
          </a>
        </div>

        <div v-else class="nxp-cart__content">
          <table class="nxp-cart__table">
            <thead>
              <tr>
                <th scope="col">Product</th>
                <th scope="col">Price</th>
                <th scope="col">Qty</th>
                <th scope="col">Total</th>
                <th scope="col" class="nxp-cart__actions"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in items" :key="item.id">
                <td data-label="Product">
                  <strong>{{ item.product_title || item.title }}</strong>
                  <ul v-if="item.options && item.options.length" class="nxp-cart__options">
                    <li v-for="(option, index) in item.options" :key="index">
                      <span>{{ option.name }}:</span> {{ option.value }}
                    </li>
                  </ul>
                </td>
                <td data-label="Price">{{ format(item.unit_price_cents) }}</td>
                <td data-label="Qty">
                  <input
                    type="number"
                    min="1"
                    :value="item.qty"
                    @input="updateQty(item, $event.target.value)"
                  />
                </td>
                <td data-label="Total">{{ format(item.total_cents) }}</td>
                <td class="nxp-cart__actions">
                  <button type="button" class="nxp-link-button" @click="remove(item)">
                    Remove
                  </button>
                </td>
              </tr>
            </tbody>
          </table>

          <aside class="nxp-cart__summary">
            <h2>Summary</h2>
            <dl>
              <div>
                <dt>Subtotal</dt>
                <dd>{{ format(summary.subtotal_cents) }}</dd>
              </div>
              <div>
                <dt>Shipping</dt>
                <dd>Calculated at checkout</dd>
              </div>
              <div>
                <dt>Total</dt>
                <dd class="nxp-cart__summary-total">{{ format(summary.total_cents) }}</dd>
              </div>
            </dl>

            <a class="nxp-btn nxp-btn--primary" href="index.php?option=com_nxpeasycart&view=checkout">
              Proceed to checkout
            </a>
          </aside>
        </div>
      </div>
    `,
        setup() {
            const items = reactive(payload.items || []);
            const currency = payload.summary?.currency || "USD";

            const summary = reactive({
                subtotal_cents: payload.summary?.subtotal_cents || 0,
                total_cents: payload.summary?.total_cents || 0,
            });

            const recalcSummary = () => {
                const subtotal = items.reduce(
                    (total, item) => total + (item.total_cents || 0),
                    0
                );
                summary.subtotal_cents = subtotal;
                summary.total_cents = subtotal;
            };

            const remove = (item) => {
                const index = items.indexOf(item);

                if (index >= 0) {
                    items.splice(index, 1);
                    recalcSummary();
                }
            };

            const updateQty = (item, value) => {
                const qty = Math.max(1, parseInt(value, 10) || 1);
                item.qty = qty;
                item.total_cents = qty * (item.unit_price_cents || 0);
                recalcSummary();
            };

            return {
                items,
                summary,
                remove,
                updateQty,
                format: (cents) => formatMoney(cents, currency),
            };
        },
    });

    app.mount(el);
};

const mountCheckoutIsland = (el) => {
    const payload = parsePayload(el.dataset.nxpCheckout, {});
    const cart = payload.cart || { items: [], summary: {} };
    const shippingRules = payload.shipping_rules || [];
    const taxRates = payload.tax_rates || [];
    const settings = payload.settings || {};
    const payments = payload.payments || {};
    const endpoints = payload.endpoints || {};
    const token = payload.token || "";

    el.innerHTML = "";

    const app = createApp({
        template: `
      <div class="nxp-checkout" v-cloak>
        <header class="nxp-checkout__header">
          <h1 class="nxp-checkout__title">Checkout</h1>
          <p class="nxp-checkout__lead">
            Enter your details to complete the order.
          </p>
        </header>

        <div class="nxp-checkout__layout" v-if="!success">
          <form class="nxp-checkout__form" @submit.prevent="submit">
            <fieldset>
              <legend>Contact</legend>
              <div class="nxp-checkout__field">
                <label for="nxp-checkout-email">Email</label>
                <input id="nxp-checkout-email" type="email" v-model="model.email" required />
              </div>
            </fieldset>

            <fieldset>
              <legend>Billing address</legend>
              <div class="nxp-checkout__grid">
                <div class="nxp-checkout__field">
                  <label for="nxp-first-name">First name</label>
                  <input id="nxp-first-name" type="text" v-model="model.billing.first_name" required />
                </div>
                <div class="nxp-checkout__field">
                  <label for="nxp-last-name">Last name</label>
                  <input id="nxp-last-name" type="text" v-model="model.billing.last_name" required />
                </div>
                <div class="nxp-checkout__field nxp-checkout__field--wide">
                  <label for="nxp-address-line1">Address</label>
                  <input id="nxp-address-line1" type="text" v-model="model.billing.address_line1" required />
                </div>
                <div class="nxp-checkout__field">
                  <label for="nxp-city">City</label>
                  <input id="nxp-city" type="text" v-model="model.billing.city" required />
                </div>
                <div class="nxp-checkout__field">
                  <label for="nxp-postcode">Postcode</label>
                  <input id="nxp-postcode" type="text" v-model="model.billing.postcode" required />
                </div>
                <div class="nxp-checkout__field">
                  <label for="nxp-country">Country</label>
                  <input id="nxp-country" type="text" v-model="model.billing.country" required />
                </div>
              </div>
            </fieldset>

            <fieldset>
              <legend>Shipping</legend>
              <p class="nxp-checkout__radio-group">
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
              <p class="nxp-checkout__radio-group" v-if="gateways.length">
                <label
                  v-for="gateway in gateways"
                  :key="gateway.id"
                >
                  <input
                    type="radio"
                    name="nxp-checkout-gateway"
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

            <div v-if="error" class="nxp-admin-alert nxp-admin-alert--error">
              {{ error }}
            </div>

            <button type="submit" class="nxp-btn nxp-btn--primary" :disabled="loading">
              <span v-if="loading">Processing…</span>
              <span v-else>Complete order</span>
            </button>
          </form>

          <aside class="nxp-checkout__summary">
            <h2>Order summary</h2>
            <div class="nxp-checkout__cart" v-if="cartItems.length">
              <ul>
                <li v-for="item in cartItems" :key="item.id">
                  <div>
                    <strong>{{ item.product_title || item.title }}</strong>
                    <span class="nxp-checkout__qty">× {{ item.qty }}</span>
                  </div>
                  <div class="nxp-checkout__price">{{ formatMoney(item.total_cents) }}</div>
                </li>
              </ul>
              <div class="nxp-checkout__totals">
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

        <div v-else class="nxp-order-confirmation__summary">
          <h2>Thank you!</h2>
          <p>Your order <strong>{{ orderNumber }}</strong> was created successfully.</p>
          <a class="nxp-btn" :href="orderUrl">View order summary</a>
        </div>
      </div>
    `,
        setup() {
            const cartItems = reactive(
                (cart.items || []).map((item) => ({ ...item }))
            );
            const currency =
                cart.summary?.currency || settings.base_currency || "USD";
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
                        const response = await fetch(endpoints.payment, {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/json",
                                "X-CSRF-Token": token,
                                "X-Requested-With": "XMLHttpRequest",
                            },
                            body: JSON.stringify(payloadBody),
                            credentials: "same-origin",
                        });

                        if (!response.ok) {
                            const message = `Checkout failed (${response.status})`;
                            throw new Error(message);
                        }

                        const data = await response.json();
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

                    const response = await fetch(endpoints.checkout, {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-Token": token,
                            "X-Requested-With": "XMLHttpRequest",
                        },
                        body: JSON.stringify(payloadBody),
                        credentials: "same-origin",
                    });

                    if (!response.ok) {
                        const message = `Checkout failed (${response.status})`;
                        throw new Error(message);
                    }

                    const data = await response.json();
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
                formatMoney: (cents) => formatMoney(cents, currency),
                gateways,
                selectedGateway,
            };
        },
    });

    app.mount(el);
};

const islandRegistry = {
    category: mountCategoryIsland,
    cart: mountCartIsland,
    checkout: mountCheckoutIsland,
};

const bootIslands = () => {
    document.querySelectorAll("[data-nxp-island]").forEach((el) => {
        const key = el.dataset.nxpIsland;

        if (!key || !islandRegistry[key]) {
            return;
        }

        islandRegistry[key](el);
    });
};

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bootIslands);
} else {
    bootIslands();
}
