import { createApp, reactive, computed, ref, watch } from "vue";
import parsePayload from "../utils/parsePayload.js";
import { createApiClient } from "../utils/apiClient.js";

const normaliseImages = (raw = []) =>
    Array.isArray(raw)
        ? raw
              .filter((src) => typeof src === "string" && src.trim() !== "")
              .map((src) => src.trim())
        : [];

const clampIndex = (index, length) => {
    if (!Number.isFinite(index) || length <= 0) {
        return 0;
    }

    const max = length - 1;

    if (index < 0) {
        return max;
    }

    if (index > max) {
        return 0;
    }

    return index;
};

const createLightbox = () => {
    const wrapper = document.createElement("div");
    wrapper.className = "nxp-ec-lightbox";
    wrapper.innerHTML = `
      <div class="nxp-ec-lightbox__backdrop" data-nxp-lightbox-close></div>
      <div class="nxp-ec-lightbox__dialog" role="dialog" aria-modal="true">
        <button type="button" class="nxp-ec-lightbox__close" data-nxp-lightbox-close aria-label="Close gallery">
          <span aria-hidden="true">&times;</span>
        </button>
        <div class="nxp-ec-lightbox__stage">
          <button type="button" class="nxp-ec-lightbox__nav is-prev" data-nxp-lightbox-prev aria-label="Previous image">&#8249;</button>
          <img class="nxp-ec-lightbox__image" data-nxp-lightbox-image alt="" />
          <button type="button" class="nxp-ec-lightbox__nav is-next" data-nxp-lightbox-next aria-label="Next image">&#8250;</button>
        </div>
        <p class="nxp-ec-lightbox__counter" data-nxp-lightbox-counter></p>
      </div>
    `;

    document.body.appendChild(wrapper);

    return wrapper;
};

const setupGallery = (el, images = [], title = "") => {
    const galleryRoot =
        el?.closest(".nxp-ec-product")?.querySelector("[data-nxp-gallery]") ||
        el?.closest("[data-nxp-gallery]");
    const imageList = normaliseImages(images);

    if (!galleryRoot || imageList.length === 0 || galleryRoot.dataset.nxpGalleryReady === "1") {
        return;
    }

    galleryRoot.dataset.nxpGalleryReady = "1";

    const trigger = galleryRoot.querySelector("[data-nxp-gallery-trigger]");
    const mainImage = galleryRoot.querySelector("[data-nxp-gallery-main]");
    const thumbs = Array.from(
        galleryRoot.querySelectorAll("[data-nxp-gallery-thumb]")
    );
    const lightbox = createLightbox();
    const lightboxImage = lightbox.querySelector("[data-nxp-lightbox-image]");
    const lightboxCounter = lightbox.querySelector("[data-nxp-lightbox-counter]");
    const btnPrev = lightbox.querySelector("[data-nxp-lightbox-prev]");
    const btnNext = lightbox.querySelector("[data-nxp-lightbox-next]");
    const closeEls = lightbox.querySelectorAll("[data-nxp-lightbox-close]");

    let current = 0;
    let keyListener = null;

    const markActiveThumb = (index) => {
        thumbs.forEach((thumb) => {
            const thumbIndex = Number.parseInt(
                thumb.dataset.nxpGalleryThumb,
                10
            );
            thumb.classList.toggle(
                "is-active",
                Number.isFinite(thumbIndex) && thumbIndex === index
            );
        });
    };

    const setActive = (index) => {
        current = clampIndex(index, imageList.length);
        const src = imageList[current];

        if (mainImage && src) {
            mainImage.src = src;
        }

        markActiveThumb(current);
    };

    const renderLightbox = (index) => {
        current = clampIndex(index, imageList.length);
        const src = imageList[current];

        if (lightboxImage && src) {
            lightboxImage.src = src;
            lightboxImage.alt = title ? `${title} (${current + 1}/${imageList.length})` : "";
        }

        if (lightboxCounter) {
            lightboxCounter.textContent = `${current + 1} / ${imageList.length}`;
        }

        markActiveThumb(current);
    };

    const closeLightbox = () => {
        lightbox.classList.remove("is-open");
        document.body.classList.remove("nxp-ec-lightbox-open");

        if (keyListener) {
            window.removeEventListener("keydown", keyListener);
            keyListener = null;
        }
    };

    const openLightbox = (index = current) => {
        renderLightbox(index);
        lightbox.classList.add("is-open");
        document.body.classList.add("nxp-ec-lightbox-open");

        keyListener = (event) => {
            if (event.key === "Escape") {
                closeLightbox();
            } else if (event.key === "ArrowRight") {
                renderLightbox(current + 1);
            } else if (event.key === "ArrowLeft") {
                renderLightbox(current - 1);
            }
        };

        window.addEventListener("keydown", keyListener);
    };

    closeEls.forEach((button) => {
        button.addEventListener("click", closeLightbox);
    });

    if (btnPrev) {
        btnPrev.addEventListener("click", () => renderLightbox(current - 1));
    }

    if (btnNext) {
        btnNext.addEventListener("click", () => renderLightbox(current + 1));
    }

    lightbox.addEventListener("click", (event) => {
        if (event.target?.closest("[data-nxp-lightbox-close]")) {
            closeLightbox();
        }
    });

    thumbs.forEach((thumb) => {
        thumb.addEventListener("click", () => {
            const thumbIndex = Number.parseInt(
                thumb.dataset.nxpGalleryThumb,
                10
            );

            if (Number.isFinite(thumbIndex)) {
                setActive(thumbIndex);
                openLightbox(thumbIndex);
            }
        });
    });

    if (trigger) {
        trigger.addEventListener("click", () => openLightbox(current));
        trigger.addEventListener("keydown", (event) => {
            if (event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                openLightbox(current);
            }
        });
    }

    setActive(0);
};

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
        sale_badge: payload.labels?.sale_badge || "Sale",
        discount_off: payload.labels?.discount_off || "off",
    };

    const endpoints = payload.endpoints || {};
    const links = payload.links || {};
    const token = payload.token || "";
    const productStatus = Number.isFinite(Number(product.status))
        ? Number(product.status)
        : product.active
          ? 1
          : 0;
    const productOutOfStock =
        Boolean(product.out_of_stock) || productStatus === -1;

    const api = createApiClient(token);

    el.innerHTML = "";

    const app = createApp({
        template: `
      <div v-cloak class="nxp-ec-product__actions">
        <div v-if="state.toast" class="nxp-ec-toast">
          {{ state.toast }}
        </div>
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
          :class="{ 'is-disabled': isDisabled, 'is-out-of-stock': isOutOfStock }"
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
          class="nxp-ec-product__message nxp-ec-product__message--alert nxp-ec-product__message--badge"
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
            let toastTimer = null;

            const state = reactive({
                variantId: variants.length === 1 ? variants[0].id : null,
                qty: 1,
                loading: false,
                success: false,
                successMessage: "",
                error: "",
                toast: "",
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
                if (productOutOfStock) {
                    return true;
                }

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

            const isDisabled = computed(
                () =>
                    state.loading ||
                    !endpoints.add ||
                    isOutOfStock.value
            );

            const add = async () => {
                state.error = "";
                state.success = false;
                state.successMessage = "";
                state.toast = "";

                if (!endpoints.add) {
                    state.error = labels.error_generic;
                    return;
                }

                const variant = selectedVariant.value;

                if (variants.length && !variant) {
                    state.error = labels.select_variant;

                    if (toastTimer) {
                        clearTimeout(toastTimer);
                    }

                    state.toast = labels.select_variant;
                    toastTimer = window.setTimeout(() => {
                        state.toast = "";
                    }, 2500);

                    if (typeof window !== "undefined" && typeof window.alert === "function") {
                        window.alert(labels.select_variant);
                    }

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

    setupGallery(el, normaliseImages(product.images || []), product.title || "");
}
