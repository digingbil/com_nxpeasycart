import { createApp, computed, onMounted, onUnmounted, reactive } from "vue";
import parsePayload from "../utils/parsePayload.js";
import formatMoney from "../utils/formatMoney.js";

// Shared state for all cart summary instances (allows floating bar to stay in sync)
const sharedState = reactive({
    count: 0,
    total_cents: 0,
    currency: "USD",
    initialized: false,
});

const BODY_FLOATING_CLASS = "nxp-ec-floating-cart-visible";

const copyCssVars = (sourceEl, targetEl) => {
    if (
        typeof window === "undefined" ||
        !sourceEl ||
        !targetEl ||
        !window.getComputedStyle
    ) {
        return;
    }

    const styles = window.getComputedStyle(sourceEl);

    if (!styles) {
        return;
    }

    Array.from(styles).forEach((prop) => {
        if (prop.startsWith("--nxp-ec-")) {
            targetEl.style.setProperty(prop, styles.getPropertyValue(prop));
        }
    });
};

const syncBodyPadding = () => {
    if (typeof document === "undefined") {
        return;
    }

    const body = document.body;

    if (!body) {
        return;
    }

    if (sharedState.count > 0) {
        body.classList.add(BODY_FLOATING_CLASS);
    } else {
        body.classList.remove(BODY_FLOATING_CLASS);
    }
};

// Track if floating bar app is already created
let floatingApp = null;
let floatingPortal = null;

export default function mountCartSummaryIsland(el) {
    // Hide the static PHP fallbacks now that Vue is mounting
    el.querySelectorAll(".nxp-ec-cart-summary__fallback, .nxp-ec-cart-summary__floating-fallback").forEach((fallback) => {
        fallback.style.display = "none";
    });

    const locale = el.dataset.nxpLocale || undefined;
    const currencyAttr = (el.dataset.nxpCurrency || "").trim() || undefined;
    const displayMode = el.dataset.nxpDisplayMode || "default";
    const payload = parsePayload(el.dataset.nxpCartSummary, {});
    const labels = payload.labels || {};
    const links = payload.links || {};
    const summaryEndpoint = (payload.endpoints?.summary || "").trim();
    const isCheckout = payload.is_checkout === true;

    // Initialize shared state from first payload (if not already initialized)
    if (!sharedState.initialized) {
        sharedState.count = Number(payload.count || 0);
        sharedState.total_cents = Number(payload.total_cents || 0);
        sharedState.currency = payload.currency || currencyAttr || "USD";
        sharedState.initialized = true;
    }
    syncBodyPadding();

    // Update function for cart events
    const updateSharedState = (event) => {
        const cart = event?.detail || {};
        const items = Array.isArray(cart.items) ? cart.items : [];
        const newCount = items.reduce(
            (total, item) => total + (item.qty || 0),
            0
        );
        const newTotal = Number(cart.summary?.total_cents || 0);
        const newCurrency = cart.summary?.currency || sharedState.currency;

        sharedState.count = newCount;
        sharedState.total_cents = newTotal;
        sharedState.currency = newCurrency;
        syncBodyPadding();
    };

    // Refresh from server
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
            const nextCurrency = cart.summary.currency || sharedState.currency;

            sharedState.count = nextCount;
            sharedState.total_cents = nextTotal;
            sharedState.currency = nextCurrency;
            syncBodyPadding();
        } catch (error) {
            // Non-fatal: keep existing state.
        }
    };

    // Listen for cart updates globally
    window.addEventListener("nxp-cart:updated", updateSharedState);

    // Create the inline view app
    const inlineApp = createApp({
        template: `
      <div class="nxp-ec-cart-summary__inline" v-cloak>
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
            const countLabel = computed(
                () =>
                    sharedState.count +
                    " " +
                    (sharedState.count === 1
                        ? labels.item_single || "item"
                        : labels.item_plural || "items")
            );

            const totalLabel = computed(() =>
                formatMoney(sharedState.total_cents, sharedState.currency, locale)
            );

            onMounted(refresh);

            return {
                labels,
                links,
                state: sharedState,
                countLabel,
                totalLabel,
            };
        },
    });

    inlineApp.mount(el);

    // Create floating bar portal and app (only once, appended to body for reliable fixed positioning)
    if (!floatingApp && !isCheckout) {
        floatingPortal = document.getElementById("nxp-ec-floating-cart-portal");
        if (!floatingPortal) {
            floatingPortal = document.createElement("div");
            floatingPortal.id = "nxp-ec-floating-cart-portal";
            floatingPortal.className = "nxp-ec-cart-summary nxp-ec-cart-summary--floating-only";
            document.body.appendChild(floatingPortal);
            copyCssVars(el, floatingPortal);
        }

        floatingApp = createApp({
            template: `
        <div v-if="state.count > 0" class="nxp-ec-cart-summary__floating nxp-ec-cart-summary__floating--visible" v-cloak>
          <div class="nxp-ec-cart-summary__floating-inner">
            <a :href="links.cart || '#'" class="nxp-ec-cart-summary__floating-info">
              <span class="nxp-ec-cart-summary__floating-badge">{{ state.count }}</span>
              <span class="nxp-ec-cart-summary__floating-total">{{ totalLabel }}</span>
            </a>
            <div class="nxp-ec-cart-summary__floating-actions">
              <a
                v-if="links.cart"
                class="nxp-ec-btn nxp-ec-btn--ghost nxp-ec-cart-summary__floating-cta"
                :href="links.cart"
              >
                {{ labels.view_cart || "View cart" }}
              </a>
              <a
                v-if="links.checkout"
                class="nxp-ec-btn nxp-ec-btn--primary nxp-ec-cart-summary__floating-cta"
                :href="links.checkout"
              >
                {{ labels.checkout || "Checkout" }}
              </a>
            </div>
          </div>
        </div>
      `,
            setup() {
                const totalLabel = computed(() =>
                    formatMoney(sharedState.total_cents, sharedState.currency, locale)
                );

                return {
                    labels,
                    links,
                    state: sharedState,
                    totalLabel,
                };
            },
        });

        floatingApp.mount(floatingPortal);
    }
}
