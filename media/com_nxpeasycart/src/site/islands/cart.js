import { createApp, reactive } from "vue";
import parsePayload from "../utils/parsePayload.js";
import formatMoney from "../utils/formatMoney.js";

export default function mountCartIsland(el) {
    const locale = el.dataset.nxpLocale || undefined;
    const currencyAttr = (el.dataset.nxpCurrency || "").trim() || undefined;
    const payload = parsePayload(el.dataset.nxpCart, {
        items: [],
        summary: {},
    });
    const summaryEndpoint = (payload.endpoints?.summary || "").trim();

    el.innerHTML = "";

    const app = createApp({
        template: `
      <div class="nxp-ec-cart" v-cloak>
        <header class="nxp-ec-cart__header">
          <h1 class="nxp-ec-cart__title">Your cart</h1>
          <p class="nxp-ec-cart__lead">
            Review your items and proceed to checkout.
          </p>
        </header>

        <div v-if="items.length === 0" class="nxp-ec-cart__empty">
          <p>Your cart is currently empty.</p>
          <a class="nxp-ec-btn" href="index.php?option=com_nxpeasycart&view=category">
            Continue browsing
          </a>
        </div>

        <div v-else class="nxp-ec-cart__content">
          <table class="nxp-ec-cart__table">
            <thead>
              <tr>
                <th scope="col">Product</th>
                <th scope="col">Price</th>
                <th scope="col">Qty</th>
                <th scope="col">Total</th>
                <th scope="col" class="nxp-ec-cart__actions"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in items" :key="item.id">
                <td data-label="Product">
                  <strong>{{ item.product_title || item.title }}</strong>
                  <ul v-if="item.options && item.options.length" class="nxp-ec-cart__options">
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
                <td class="nxp-ec-cart__actions">
                  <button type="button" class="nxp-ec-link-button" @click="remove(item)">
                    Remove
                  </button>
                </td>
              </tr>
            </tbody>
          </table>

          <aside class="nxp-ec-cart__summary">
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
                <dd class="nxp-ec-cart__summary-total">{{ format(summary.total_cents) }}</dd>
              </div>
            </dl>

            <a class="nxp-ec-btn nxp-ec-btn--primary" href="index.php?option=com_nxpeasycart&view=checkout">
              Proceed to checkout
            </a>
          </aside>
        </div>
      </div>
    `,
        setup() {
            const items = reactive(payload.items || []);
            const currency = payload.summary?.currency || currencyAttr || "USD";

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

            const applyCart = (cart) => {
                const nextItems = Array.isArray(cart?.items) ? cart.items : [];
                items.splice(0, items.length, ...nextItems);

                summary.subtotal_cents = Number(
                    cart?.summary?.subtotal_cents || 0
                );
                summary.total_cents = Number(cart?.summary?.total_cents || 0);
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
                items,
                summary,
                remove,
                updateQty,
                format: (cents) => formatMoney(cents, currency, locale),
            };
        },
    });

    app.mount(el);
}
