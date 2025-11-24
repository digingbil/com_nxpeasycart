import { createApp, reactive, computed } from "vue";
import parsePayload from "../utils/parsePayload.js";
import formatMoney from "../utils/formatMoney.js";

export default function mountCartIsland(el) {
    const locale = el.dataset.nxpLocale || undefined;
    const currencyAttr = (el.dataset.nxpCurrency || "").trim() || undefined;
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
          <table class="nxp-ec-cart__table">
            <thead>
              <tr>
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
                <td :data-label="labels.product">
                  <strong>{{ item.product_title || item.title }}</strong>
                  <ul v-if="item.options && item.options.length" class="nxp-ec-cart__options">
                    <li v-for="(option, index) in item.options" :key="index">
                      <span>{{ option.name }}:</span> {{ option.value }}
                    </li>
                  </ul>
                </td>
                <td :data-label="labels.price">{{ format(item.unit_price_cents) }}</td>
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

          <aside class="nxp-ec-cart__summary">
            <h2>{{ labels.summary }}</h2>
            <dl>
              <div>
                <dt>{{ labels.subtotal }}</dt>
                <dd>{{ format(summary.subtotal_cents) }}</dd>
              </div>
              <div>
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
                            return;
                        }
                    } catch (error) {
                        // Fall back to client-side removal.
                    }
                }

                items.splice(index, 1);
                recalcSummary();
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
                            window.dispatchEvent(
                                new CustomEvent("nxp-cart:updated", {
                                    detail: cart,
                                })
                            );
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
                remove,
                updateQty,
                format: (cents) => formatMoney(cents, currency, locale),
            };
        },
    });

    app.mount(el);
}
