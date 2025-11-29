import { createApp, computed, onMounted, reactive } from "vue";
import parsePayload from "../utils/parsePayload.js";
import formatMoney from "../utils/formatMoney.js";

export default function mountCartSummaryIsland(el) {
    // Hide the static PHP fallback now that Vue is mounting
    const fallback = el.querySelector(".nxp-ec-cart-summary__fallback");
    if (fallback) {
        fallback.style.display = "none";
    }

    const locale = el.dataset.nxpLocale || undefined;
    const currencyAttr = (el.dataset.nxpCurrency || "").trim() || undefined;
    const payload = parsePayload(el.dataset.nxpCartSummary, {});
    const labels = payload.labels || {};
    const links = payload.links || {};
    const summaryEndpoint = (payload.endpoints?.summary || "").trim();

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
                    const cart = json?.data?.cart || json?.cart || json?.data;

                    if (!cart || !cart.summary) {
                        return;
                    }

                    const items = Array.isArray(cart.items) ? cart.items : [];
                    const nextCount = items.reduce(
                        (total, item) => total + (item.qty || 0),
                        0
                    );
                    const nextTotal = Number(cart.summary.total_cents || 0);
                    const nextCurrency =
                        cart.summary.currency || state.currency;

                    // If the API returns an empty cart but we already have a non-zero
                    // payload (e.g., module cache vs. session timing), keep the
                    // current state instead of clobbering it.
                    if (
                        nextCount === 0 &&
                        nextTotal === 0 &&
                        state.count > 0
                    ) {
                        return;
                    }

                    state.count = nextCount;
                    state.total_cents = nextTotal;
                    state.currency = nextCurrency;
                } catch (error) {
                    // Non-fatal: keep existing state.
                }
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
            onMounted(refresh);

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
