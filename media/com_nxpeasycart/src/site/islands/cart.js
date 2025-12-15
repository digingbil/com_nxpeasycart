import { createApp, reactive, computed, ref } from "vue";
import parsePayload from "../utils/parsePayload.js";
import formatMoney from "../utils/formatMoney.js";

export default function mountCartIsland(el) {
    const locale = el.dataset.nxpLocale || undefined;
    const currencyAttr = (el.dataset.nxpCurrency || "").trim() || undefined;
    const showCanceled = el.dataset.nxpCanceled === "1";
    const payload = parsePayload(el.dataset.nxpCart, {
        items: [],
        summary: {},
    });
    const labelsPayload = parsePayload(el.dataset.nxpLabels, {});
    const cartToken = payload.token || "";
    const cartEndpoints = payload.endpoints || {};
    const linksPayload = payload.links || {};
    const summaryEndpoint = (payload.endpoints?.summary || "").trim();

    const labels = {
        title: labelsPayload.title || "Your cart",
        lead:
            labelsPayload.lead || "Review your items and proceed to checkout.",
        empty: labelsPayload.empty || "Your cart is currently empty.",
        continue: labelsPayload.continue || "Continue browsing",
        product: labelsPayload.product || "Product",
        price: labelsPayload.price || "Price",
        qty: labelsPayload.qty || "Qty",
        total: labelsPayload.total || "Total",
        actions: labelsPayload.actions || "Actions",
        remove: labelsPayload.remove || "Remove",
        summary: labelsPayload.summary || "Summary",
        subtotal: labelsPayload.subtotal || "Subtotal",
        shipping: labelsPayload.shipping || "Shipping",
        shipping_note:
            labelsPayload.shipping_note || "Calculated at checkout",
        tax: labelsPayload.tax || "Tax",
        total_label: labelsPayload.total_label || "Total",
        checkout: labelsPayload.checkout || "Proceed to checkout",
        canceled_title:
            labelsPayload.canceled_title || "Payment canceled",
        canceled_message:
            labelsPayload.canceled_message ||
            "Your payment was canceled. Your cart items are still saved - you can try again when you're ready.",
        sale_badge: labelsPayload.sale_badge || "Sale",
        sale_savings: labelsPayload.sale_savings || "You save",
    };
    const links = {
        browse:
            typeof linksPayload.browse === "string" && linksPayload.browse !== ""
                ? linksPayload.browse
                : "index.php?option=com_nxpeasycart&view=landing",
        checkout:
            typeof linksPayload.checkout === "string" &&
            linksPayload.checkout !== ""
                ? linksPayload.checkout
                : "index.php?option=com_nxpeasycart&view=checkout",
    };

    el.innerHTML = "";

    const app = createApp({
        template: `
      <div class="nxp-ec-cart" v-cloak>
        <div v-if="canceled" class="nxp-ec-alert nxp-ec-alert--warning" role="alert">
          <strong>{{ labels.canceled_title }}</strong>
          <p>{{ labels.canceled_message }}</p>
          <button type="button" class="nxp-ec-alert__close" @click="dismissCanceled" aria-label="Dismiss">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" width="16" height="16">
              <path d="M18 6L6 18M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <header class="nxp-ec-cart__header">
          <h1 class="nxp-ec-cart__title">{{ labels.title }}</h1>
          <p class="nxp-ec-cart__lead">
            {{ labels.lead }}
          </p>
        </header>

        <div v-if="items.length === 0" class="nxp-ec-cart__empty">
          <p>{{ labels.empty }}</p>
          <a class="nxp-ec-btn" :href="links.browse">
            {{ labels.continue }}
          </a>
        </div>

        <div v-else class="nxp-ec-cart__content">
          <!-- Desktop table view -->
          <table class="nxp-ec-cart__table">
            <thead>
              <tr>
                <th scope="col" class="nxp-ec-cart__image-col">
                  <span class="nxp-ec-sr-only">Image</span>
                </th>
                <th scope="col">{{ labels.product }}</th>
                <th scope="col">{{ labels.price }}</th>
                <th scope="col" class="nxp-ec-cart__qty">{{ labels.qty }}</th>
                <th scope="col">{{ labels.total }}</th>
                <th scope="col" class="nxp-ec-cart__actions">
                  <span class="nxp-ec-sr-only">{{ labels.actions }}</span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in items" :key="item.id">
                <td class="nxp-ec-cart__image-cell">
                  <a v-if="item.image && item.url" :href="item.url" class="nxp-ec-cart__thumb">
                    <img :src="item.image" :alt="item.product_title || item.title" loading="lazy" />
                  </a>
                  <div v-else-if="item.image" class="nxp-ec-cart__thumb">
                    <img :src="item.image" :alt="item.product_title || item.title" loading="lazy" />
                  </div>
                </td>
                <td :data-label="labels.product">
                  <a v-if="item.url" :href="item.url" class="nxp-ec-cart__product-link">
                    <strong>{{ item.product_title || item.title }}</strong>
                  </a>
                  <strong v-else>{{ item.product_title || item.title }}</strong>
                  <ul v-if="item.options && item.options.length" class="nxp-ec-cart__options">
                    <li v-for="(option, index) in item.options" :key="index">
                      <span>{{ option.name }}:</span> {{ option.value }}
                    </li>
                  </ul>
                </td>
                <td :data-label="labels.price" :class="{ 'nxp-ec-cart__price--sale': item.is_on_sale }">
                  <template v-if="item.is_on_sale">
                    <span class="nxp-ec-cart__sale-badge">{{ labels.sale_badge }}</span>
                    <span class="nxp-ec-cart__regular-price">{{ format(item.regular_price_cents) }}</span>
                    <span class="nxp-ec-cart__sale-price">{{ format(item.unit_price_cents) }}</span>
                  </template>
                  <template v-else>{{ format(item.unit_price_cents) }}</template>
                </td>
                <td class="nxp-ec-cart__qty" :data-label="labels.qty">
                  <input
                    class="nxp-ec-cart__qty-input"
                    type="number"
                    min="1"
                    :value="item.qty"
                    @input="updateQty(item, $event.target.value)"
                  />
                </td>
                <td :data-label="labels.total">{{ format(item.total_cents) }}</td>
                <td class="nxp-ec-cart__actions">
                  <button type="button" class="nxp-ec-cart__remove" @click="remove(item)">
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      aria-hidden="true"
                    >
                      <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                      <path d="M4 7h16"></path>
                      <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path>
                      <path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path>
                      <path d="M10 12l4 4m0 -4l-4 4"></path>
                    </svg>
                    <span class="nxp-ec-sr-only">{{ labels.remove }}</span>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>

          <!-- Mobile card view -->
          <div class="nxp-ec-cart__mobile-items">
            <article v-for="item in items" :key="'mobile-' + item.id" class="nxp-ec-cart-item">
              <div v-if="item.image" class="nxp-ec-cart-item__image">
                <a v-if="item.url" :href="item.url">
                  <img :src="item.image" :alt="item.product_title || item.title" loading="lazy" />
                </a>
                <img v-else :src="item.image" :alt="item.product_title || item.title" loading="lazy" />
              </div>
              <div class="nxp-ec-cart-item__body">
                <div class="nxp-ec-cart-item__header">
                  <h3 class="nxp-ec-cart-item__title">
                    <a v-if="item.url" :href="item.url">{{ item.product_title || item.title }}</a>
                    <span v-else>{{ item.product_title || item.title }}</span>
                  </h3>
                  <button type="button" class="nxp-ec-cart-item__remove" @click="remove(item)" :aria-label="labels.remove">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M18 6L6 18M6 6l12 12"/>
                    </svg>
                  </button>
                </div>
                <ul v-if="item.options && item.options.length" class="nxp-ec-cart-item__options">
                  <li v-for="(option, index) in item.options" :key="index">{{ option.name }}: {{ option.value }}</li>
                </ul>
                <div class="nxp-ec-cart-item__meta">
                  <span class="nxp-ec-cart-item__price" :class="{ 'nxp-ec-cart-item__price--sale': item.is_on_sale }">
                    <template v-if="item.is_on_sale">
                      <span class="nxp-ec-cart-item__sale-badge">{{ labels.sale_badge }}</span>
                      <span class="nxp-ec-cart-item__regular-price">{{ format(item.regular_price_cents) }}</span>
                      <span class="nxp-ec-cart-item__sale-price">{{ format(item.unit_price_cents) }}</span>
                    </template>
                    <template v-else>{{ format(item.unit_price_cents) }}</template>
                    {{ labels.each || 'each' }}
                  </span>
                  <div class="nxp-ec-cart-item__qty-group">
                    <label :for="'qty-mobile-' + item.id" class="nxp-ec-cart-item__qty-label">{{ labels.qty }}:</label>
                    <input
                      :id="'qty-mobile-' + item.id"
                      class="nxp-ec-cart-item__qty-input"
                      type="number"
                      min="1"
                      :value="item.qty"
                      @input="updateQty(item, $event.target.value)"
                    />
                  </div>
                </div>
                <div class="nxp-ec-cart-item__total">{{ format(item.total_cents) }}</div>
              </div>
            </article>
          </div>

          <aside class="nxp-ec-cart__summary">
            <h2>{{ labels.summary }}</h2>
            <dl>
              <div>
                <dt>{{ labels.subtotal }}</dt>
                <dd>{{ format(summary.subtotal_cents) }}</dd>
              </div>
              <div v-if="saleSavings > 0" class="nxp-ec-cart__summary-savings">
                <dt>{{ labels.sale_savings }}</dt>
                <dd class="nxp-ec-cart__savings-amount">-{{ format(saleSavings) }}</dd>
              </div>
              <div v-if="requiresShipping">
                <dt>{{ labels.shipping }}</dt>
                <dd>{{ labels.shipping_note }}</dd>
              </div>
              <div v-if="showTax">
                <dt>{{ labels.tax }}</dt>
                <dd>{{ format(summary.tax_cents) }}</dd>
              </div>
              <div>
                <dt>{{ labels.total_label }}</dt>
                <dd class="nxp-ec-cart__summary-total">{{ format(summary.total_cents) }}</dd>
              </div>
            </dl>

            <a class="nxp-ec-btn nxp-ec-btn--primary" :href="links.checkout">
              {{ labels.checkout }}
            </a>
          </aside>
        </div>
      </div>
    `,
        setup() {
            const items = reactive(payload.items || []);
            const currency = payload.summary?.currency || currencyAttr || "USD";
            let taxRate = Number(payload.summary?.tax_rate ?? 0);
            let taxInclusive = Boolean(payload.summary?.tax_inclusive);
            const canceled = ref(showCanceled);

            const dismissCanceled = () => {
                canceled.value = false;
                // Remove the canceled param from URL without reload
                const url = new URL(window.location.href);
                url.searchParams.delete("canceled");
                window.history.replaceState({}, "", url.toString());
            };

            const computeTax = (subtotal) => {
                if (!taxRate) {
                    return 0;
                }

                const rate = Number(taxRate) / 100;

                return taxInclusive
                    ? Math.round(subtotal - subtotal / (1 + rate))
                    : Math.round(subtotal * rate);
            };

            const summary = reactive({
                subtotal_cents: payload.summary?.subtotal_cents || 0,
                tax_cents:
                    payload.summary?.tax_cents ??
                    computeTax(payload.summary?.subtotal_cents || 0) ??
                    0,
                total_cents: payload.summary?.total_cents ?? 0,
            });
            const showTax = computed(
                () =>
                    Number(summary.tax_cents) > 0 ||
                    Number(taxRate) > 0
            );

            // Check if cart has physical items (requires shipping)
            const requiresShipping = computed(() =>
                items.some((item) => !item.is_digital)
            );

            // Calculate total sale savings
            const saleSavings = computed(() => {
                return items.reduce((total, item) => {
                    if (item.is_on_sale && item.regular_price_cents > item.unit_price_cents) {
                        const savingsPerItem = item.regular_price_cents - item.unit_price_cents;
                        return total + (savingsPerItem * item.qty);
                    }
                    return total;
                }, 0);
            });

            const recalcSummary = () => {
                const subtotal = items.reduce(
                    (total, item) => total + (item.total_cents || 0),
                    0
                );
                summary.subtotal_cents = subtotal;
                summary.tax_cents = computeTax(subtotal);
                summary.total_cents =
                    subtotal + (taxInclusive ? 0 : summary.tax_cents);
            };

            const buildBroadcastPayload = (cartPayload = null) => {
                if (cartPayload && cartPayload.summary) {
                    return cartPayload;
                }

                return {
                    items: items.map((item) => ({ ...item })),
                    summary: {
                        subtotal_cents: summary.subtotal_cents,
                        tax_cents: summary.tax_cents,
                        total_cents: summary.total_cents,
                        currency,
                        tax_rate: taxRate,
                        tax_inclusive: taxInclusive,
                    },
                };
            };

            const broadcastCart = (cartPayload = null) => {
                window.dispatchEvent(
                    new CustomEvent("nxp-cart:updated", {
                        detail: buildBroadcastPayload(cartPayload),
                    })
                );
            };

            const remove = async (item) => {
                const index = items.indexOf(item);

                if (index < 0) {
                    return;
                }

                if (cartEndpoints.remove) {
                    try {
                        const formData = new FormData();

                        if (cartToken) {
                            formData.append(cartToken, "1");
                        }

                        formData.append(
                            "variant_id",
                            String(item.variant_id || item.id || "")
                        );
                        formData.append(
                            "product_id",
                            String(item.product_id || item.id || "")
                        );

                        const response = await fetch(cartEndpoints.remove, {
                            method: "POST",
                            body: formData,
                            headers: {
                                Accept: "application/json",
                            },
                            credentials: "same-origin",
                        });

                        const json = await response.json().catch(() => null);
                        const cart =
                            json?.data?.cart ||
                            json?.cart ||
                            json?.data ||
                            null;

                        if (cart) {
                            applyCart(cart);
                            broadcastCart(cart);
                            return;
                        }
                    } catch (error) {
                        // Fall back to client-side removal.
                    }
                }

                items.splice(index, 1);
                recalcSummary();
                broadcastCart();
            };

            const updateQty = async (item, value) => {
                const qty = Math.max(1, parseInt(value, 10) || 1);

                // Optimistically update UI
                item.qty = qty;
                item.total_cents = qty * (item.unit_price_cents || 0);
                recalcSummary();

                // Persist to server
                const updateEndpoint = payload?.endpoints?.update;

                if (updateEndpoint && cartToken) {
                    try {
                        const formData = new FormData();
                        formData.append(cartToken, "1");
                        formData.append("variant_id", item.variant_id || "");
                        formData.append("product_id", item.product_id || "");
                        formData.append("qty", qty);

                        const response = await fetch(updateEndpoint, {
                            method: "POST",
                            body: formData,
                            headers: {
                                Accept: "application/json",
                            },
                            credentials: "same-origin",
                        });

                        const json = await response.json().catch(() => null);

                        if (!response.ok || json?.success === false) {
                            // Server rejected the update (e.g., out of stock)
                            const errorMessage = json?.message || 'Failed to update cart';
                            alert(errorMessage);

                            // Refresh cart to revert to actual state
                            if (summaryEndpoint) {
                                const summaryResponse = await fetch(summaryEndpoint, {
                                    method: "GET",
                                    headers: { Accept: "application/json" },
                                    credentials: "same-origin",
                                });
                                const summaryJson = await summaryResponse.json().catch(() => null);
                                const freshCart = summaryJson?.data?.cart || summaryJson?.cart || null;
                                if (freshCart) {
                                    applyCart(freshCart);
                                }
                            }
                            return;
                        }

                        const cart =
                            json?.data?.cart ||
                            json?.cart ||
                            json?.data ||
                            null;

                        if (cart) {
                            applyCart(cart);
                            broadcastCart(cart);
                        }
                    } catch (error) {
                        // Non-fatal; UI already updated optimistically
                        console.error("Cart update failed:", error);
                    }
                }
            };

            const applyCart = (cart) => {
                const nextItems = Array.isArray(cart?.items) ? cart.items : [];
                items.splice(0, items.length, ...nextItems);

                taxRate = Number(cart?.summary?.tax_rate ?? taxRate ?? 0);
                taxInclusive = Boolean(
                    cart?.summary?.tax_inclusive ?? taxInclusive
                );
                summary.subtotal_cents = Number(
                    cart?.summary?.subtotal_cents || 0
                );
                summary.tax_cents = Number(
                    cart?.summary?.tax_cents ??
                        computeTax(summary.subtotal_cents) ??
                        0
                );
                summary.total_cents = Number(
                    cart?.summary?.total_cents ??
                        summary.subtotal_cents +
                            (taxInclusive ? 0 : summary.tax_cents)
                );
            };

            const refresh = async () => {
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

                    const json = await response.json().catch(() => null);
                    const cart =
                        json?.data?.cart ||
                        json?.cart ||
                        json?.data ||
                        null;

                    if (cart) {
                        applyCart(cart);
                    }
                } catch (error) {
                    // Non-fatal; keep existing state.
                }
            };

            window.addEventListener("nxp-cart:updated", (event) => {
                applyCart(event?.detail || {});
            });

            refresh();

            return {
                labels,
                links,
                items,
                summary,
                showTax,
                requiresShipping,
                saleSavings,
                canceled,
                dismissCanceled,
                remove,
                updateQty,
                format: (cents) => formatMoney(cents, currency, locale),
            };
        },
    });

    app.mount(el);
}
