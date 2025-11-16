import { createApp, computed, reactive } from "vue";
import parsePayload from "../utils/parsePayload.js";
import formatMoney from "../utils/formatMoney.js";

export default function mountCartSummaryIsland(el) {
    const locale = el.dataset.nxpLocale || undefined;
    const currencyAttr = (el.dataset.nxpCurrency || "").trim() || undefined;
    const payload = parsePayload(el.dataset.nxpCartSummary, {});
    const labels = payload.labels || {};
    const links = payload.links || {};

    el.innerHTML = "";

    const app = createApp({
        template: `
      <div class="nxp-ec-cart-summary__inner" v-cloak>
        <p v-if="state.count === 0" class="nxp-ec-cart-summary__empty">
          {{ labels.empty || "Your cart is empty." }}
        </p>
        <div v-else class="nxp-ec-cart-summary__content">
          <a :href="links.cart || '#'" class="nxp-ec-cart-summary__link">
            <span class="nxp-ec-cart-summary__count">{{ countLabel }}</span>
            <span class="nxp-ec-cart-summary__total">
              {{ (labels.total_label || "Total") + ": " + totalLabel }}
            </span>
          </a>
          <div class="nxp-ec-cart-summary__actions">
            <a
              v-if="links.cart"
              class="nxp-ec-btn nxp-ec-btn--ghost"
              :href="links.cart"
            >
              {{ labels.view_cart || "View cart" }}
            </a>
            <a
              v-if="links.checkout"
              class="nxp-ec-btn nxp-ec-btn--primary"
              :href="links.checkout"
            >
              {{ labels.checkout || "Checkout" }}
            </a>
          </div>
        </div>
      </div>
    `,
        setup() {
            const state = reactive({
                count: Number(payload.count || 0),
                total_cents: Number(payload.total_cents || 0),
                currency: payload.currency || currencyAttr || "USD",
            });

            const update = (event) => {
                const cart = event?.detail || {};
                const items = Array.isArray(cart.items) ? cart.items : [];
                state.count = items.reduce(
                    (total, item) => total + (item.qty || 0),
                    0
                );
                state.total_cents = Number(cart.summary?.total_cents || 0);
                state.currency = cart.summary?.currency || state.currency;
            };

            const countLabel = computed(
                () =>
                    state.count +
                    " " +
                    (state.count === 1
                        ? labels.item_single || "item"
                        : labels.item_plural || "items")
            );

            const totalLabel = computed(() =>
                formatMoney(state.total_cents, state.currency, locale)
            );

            window.addEventListener("nxp-cart:updated", update);

            return {
                labels,
                links,
                state,
                countLabel,
                totalLabel,
            };
        },
    });

    app.mount(el);
}
