import { createApp, reactive, computed, ref, watch } from "vue";
import parsePayload from "../utils/parsePayload.js";
import { createApiClient } from "../utils/apiClient.js";

export default function mountProductIsland(el) {
    const locale = el.dataset.nxpLocale || undefined;
    const currencyAttr = (el.dataset.nxpCurrency || "").trim() || undefined;
    const payload = parsePayload(el.dataset.nxpProduct, {});
    const product = payload.product || {};
    const rawVariants = Array.isArray(payload.variants) ? payload.variants : [];
    const variants = rawVariants
        .map((variant) => ({
            ...variant,
            id: Number(variant.id || 0),
            stock:
                variant.stock === null || variant.stock === undefined
                    ? null
                    : Number(variant.stock),
        }))
        .filter((variant) => Number.isFinite(variant.id) && variant.id > 0);

    const labels = {
        add_to_cart: payload.labels?.add_to_cart || "Add to cart",
        select_variant: payload.labels?.select_variant || "Select a variant",
        out_of_stock: payload.labels?.out_of_stock || "Out of stock",
        added: payload.labels?.added || "Added to cart",
        view_cart: payload.labels?.view_cart || "View cart",
        qty_label: payload.labels?.qty_label || "Quantity",
        error_generic:
            payload.labels?.error_generic ||
            "We couldn't add this item to your cart. Please try again.",
        variant_none: payload.labels?.variant_none || "—",
    };

    const endpoints = payload.endpoints || {};
    const links = payload.links || {};
    const token = payload.token || "";

    const api = createApiClient(token);

    el.innerHTML = "";

    const app = createApp({
        template: `
      <div v-cloak class="nxp-ec-product__actions">
        <div
          v-if="variants.length > 1"
          class="nxp-ec-product__field"
        >
          <label :for="variantSelectId" class="nxp-ec-product__label">
            {{ labels.select_variant }}
          </label>
          <select
            :id="variantSelectId"
            class="nxp-ec-product__select"
            v-model.number="state.variantId"
          >
            <option value="">{{ labels.select_variant }}</option>
            <option
              v-for="variant in variants"
              :key="variant.id"
              :value="variant.id"
              :disabled="variant.stock !== null && variant.stock <= 0"
            >
              {{ variant.sku }}
              <template v-if="variant.price_label">
                — {{ variant.price_label }}
              </template>
            </option>
          </select>
        </div>

        <div class="nxp-ec-product__field">
          <label :for="qtyInputId" class="nxp-ec-product__label">
            {{ labels.qty_label }}
          </label>
          <input
            :id="qtyInputId"
            class="nxp-ec-product__qty-input"
            type="number"
            min="1"
            :max="maxQty"
            v-model.number="state.qty"
          />
        </div>
        <button
          type="button"
          class="nxp-ec-btn nxp-ec-btn--primary nxp-ec-product__buy"
          :disabled="isDisabled"
          @click="add"
        >
          <span
            v-if="state.loading"
            class="nxp-ec-product__spinner"
            aria-hidden="true"
          ></span>
          {{ labels.add_to_cart }}
        </button>

        <p
          v-if="isOutOfStock"
          class="nxp-ec-product__message nxp-ec-product__message--muted"
        >
          {{ labels.out_of_stock }}
        </p>

        <p
          v-if="state.error"
          class="nxp-ec-product__message nxp-ec-product__message--error"
        >
          {{ state.error }}
        </p>

        <p
          v-if="state.success"
          class="nxp-ec-product__message nxp-ec-product__message--success"
        >
          {{ state.successMessage || labels.added }}
          <template v-if="links.cart">
            · <a :href="links.cart">{{ labels.view_cart }}</a>
          </template>
        </p>
      </div>
    `,
        setup() {
            const variantSelectId = `nxp-ec-variant-${product.id || "0"}`;
            const qtyInputId = `nxp-ec-qty-${product.id || "0"}`;

            const state = reactive({
                variantId: variants.length === 1 ? variants[0].id : null,
                qty: 1,
                loading: false,
                success: false,
                successMessage: "",
                error: "",
            });

            const selectedVariant = computed(() => {
                if (!variants.length) {
                    return null;
                }

                if (state.variantId) {
                    return (
                        variants.find(
                            (variant) => variant.id === state.variantId
                        ) || null
                    );
                }

                return variants.length === 1 ? variants[0] : null;
            });

            const maxQty = computed(() => {
                const variant = selectedVariant.value;

                if (!variant) {
                    return undefined;
                }

                if (
                    variant.stock === null ||
                    variant.stock === undefined ||
                    !Number.isFinite(variant.stock)
                ) {
                    return undefined;
                }

                const numericStock = Number(variant.stock);

                if (!Number.isFinite(numericStock) || numericStock <= 0) {
                    return undefined;
                }

                return numericStock;
            });

            const clampQty = (value) => {
                let qty = Number(value);

                if (!Number.isFinite(qty) || qty < 1) {
                    qty = 1;
                }

                const cap = maxQty.value;

                if (Number.isFinite(cap)) {
                    qty = Math.min(qty, cap);
                }

                return qty;
            };

            watch(
                () => state.qty,
                (value) => {
                    const next = clampQty(value);

                    if (next !== value) {
                        state.qty = next;
                    }
                }
            );

            watch(
                () => state.variantId,
                () => {
                    state.error = "";
                    state.success = false;
                    state.successMessage = "";

                    const next = clampQty(state.qty);

                    if (next !== state.qty) {
                        state.qty = next;
                    }
                }
            );

            const isOutOfStock = computed(() => {
                const variant = selectedVariant.value;

                if (!variant) {
                    return false;
                }

                if (
                    variant.stock === null ||
                    variant.stock === undefined
                ) {
                    return false;
                }

                return Number(variant.stock) <= 0;
            });

            const isDisabled = computed(() => {
                if (state.loading) {
                    return true;
                }

                if (!variants.length) {
                    return true;
                }

                if (!selectedVariant.value) {
                    return true;
                }

                if (isOutOfStock.value) {
                    return true;
                }

                return false;
            });

            const add = async () => {
                state.error = "";
                state.success = false;
                state.successMessage = "";

                if (!endpoints.add) {
                    state.error = labels.error_generic;
                    return;
                }

                const variant = selectedVariant.value;

                if (variants.length && !variant) {
                    state.error = labels.select_variant;
                    return;
                }

                if (isOutOfStock.value) {
                    state.error = labels.out_of_stock;
                    return;
                }

                state.loading = true;

                try {
                    const json = await api.postForm(endpoints.add, {
                        product_id: String(product.id || ""),
                        qty: String(clampQty(state.qty)),
                        variant_id: variant ? String(variant.id) : undefined,
                    });

                    const cart = json.data?.cart || null;

                    state.success = true;
                    state.successMessage = json.message || labels.added;

                    if (cart) {
                        window.dispatchEvent(
                            new CustomEvent("nxp-cart:updated", {
                                detail: cart,
                            })
                        );
                    }
                } catch (error) {
                    state.error =
                        (error && error.message) || labels.error_generic;
                } finally {
                    state.loading = false;
                }
            };

            return {
                product,
                variants,
                labels,
                links,
                state,
                add,
                isDisabled,
                isOutOfStock,
                maxQty,
                variantSelectId,
                qtyInputId,
            };
        },
    });

    app.mount(el);
}
