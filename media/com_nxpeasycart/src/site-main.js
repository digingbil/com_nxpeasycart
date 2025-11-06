import { createApp, reactive, computed, ref, watch } from "vue";

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

const mountLandingIsland = (el) => {
    const payload = parsePayload(el.dataset.nxpLanding, {});
    const hero = payload.hero || {};
    const search = payload.search || {};
    const categoryTiles = Array.isArray(payload.categories)
        ? payload.categories
        : [];
    const sections = Array.isArray(payload.sections) ? payload.sections : [];
    const labelsPayload = payload.labels || {};
    const trust = payload.trust || {};

    const defaultSearchAction =
        search.action || "index.php?option=com_nxpeasycart&view=category";
    const defaultPlaceholder =
        search.placeholder || "Search for shoes, laptops, gifts…";
    const defaultCtaLabel = hero?.cta?.label || "Shop Best Sellers";
    const defaultCtaLink = hero?.cta?.link || defaultSearchAction;

    const labels = {
        search_label:
            labelsPayload.search_label || "Search the catalogue",
        search_button: labelsPayload.search_button || "Search",
        view_all: labelsPayload.view_all || "View all",
        view_product: labelsPayload.view_product || "View product",
        categories_aria:
            labelsPayload.categories_aria || "Browse categories",
    };

    el.innerHTML = "";

    const app = createApp({
        template: `
      <div class="nxp-ec-landing__inner" v-cloak>
        <header class="nxp-ec-landing__hero">
          <div class="nxp-ec-landing__hero-copy">
            <p v-if="hero.eyebrow" class="nxp-ec-landing__eyebrow">{{ hero.eyebrow }}</p>
            <h1 class="nxp-ec-landing__title">{{ hero.title }}</h1>
            <p v-if="hero.subtitle" class="nxp-ec-landing__subtitle">{{ hero.subtitle }}</p>
            <div class="nxp-ec-landing__actions">
              <a class="nxp-ec-btn nxp-ec-btn--primary" :href="cta.link">
                {{ cta.label }}
              </a>
            </div>
          </div>
          <form class="nxp-ec-landing__search" @submit.prevent="submitSearch">
            <label class="sr-only" for="nxp-ec-landing-search-input">
              {{ labels.search_label }}
            </label>
            <input
              id="nxp-ec-landing-search-input"
              type="search"
              v-model="term"
              :placeholder="searchPlaceholder"
            />
            <button type="submit" class="nxp-ec-btn nxp-ec-btn--ghost">
              {{ labels.search_button }}
            </button>
          </form>
        </header>

        <section
          v-if="categoryTiles.length"
          class="nxp-ec-landing__categories"
          :aria-label="labels.categories_aria"
        >
          <a
            v-for="category in categoryTiles"
            :key="category.id || category.slug || category.title"
            class="nxp-ec-landing__category"
            :href="category.link"
          >
            <span class="nxp-ec-landing__category-title">{{ category.title }}</span>
          </a>
        </section>

        <section
          v-for="section in visibleSections"
          :key="section.key"
          class="nxp-ec-landing__section"
        >
          <header class="nxp-ec-landing__section-header">
            <h2 class="nxp-ec-landing__section-title">{{ section.title }}</h2>
            <a class="nxp-ec-landing__section-link" :href="searchAction">
              {{ labels.view_all }}
            </a>
          </header>
          <div class="nxp-ec-landing__grid">
            <article
              v-for="item in section.items"
              :key="item.id || item.slug || item.title"
              class="nxp-ec-landing__card"
            >
              <figure v-if="item.images && item.images.length" class="nxp-ec-landing__card-media">
                <img :src="item.images[0]" :alt="item.title" loading="lazy" />
              </figure>
              <div class="nxp-ec-landing__card-body">
                <h3 class="nxp-ec-landing__card-title">
                  <a :href="item.link">{{ item.title }}</a>
                </h3>
                <p v-if="item.short_desc" class="nxp-ec-landing__card-intro">
                  {{ item.short_desc }}
                </p>
                <p v-if="item.price_label" class="nxp-ec-landing__card-price">
                  {{ item.price_label }}
                </p>
                <a class="nxp-ec-btn nxp-ec-btn--ghost" :href="item.link">
                  {{ labels.view_product }}
                </a>
              </div>
            </article>
          </div>
        </section>

        <aside v-if="trust.text" class="nxp-ec-landing__trust">
          <p class="nxp-ec-landing__trust-text">{{ trust.text }}</p>
        </aside>
      </div>
    `,
        setup() {
            const heroData = {
                eyebrow: hero.eyebrow || "",
                title: hero.title || "Shop",
                subtitle: hero.subtitle || "",
            };

            const cta = {
                label: hero?.cta?.label || defaultCtaLabel,
                link: hero?.cta?.link || defaultCtaLink,
            };

            const searchAction = search.action || defaultSearchAction;
            const searchPlaceholder =
                search.placeholder || defaultPlaceholder;

            const sectionsWithItems = sections.filter(
                (section) => Array.isArray(section.items) && section.items.length
            );

            const visibleSections = computed(() =>
                sectionsWithItems.map((section) => ({
                    key: section.key || section.title,
                    title: section.title || "",
                    items: section.items.slice(0, 12),
                }))
            );

            const term = ref("");

            const submitSearch = () => {
                const action = search.action || defaultSearchAction;
                const value = term.value.trim();

                try {
                    const target = new URL(action, window.location.origin);

                    if (value) {
                        target.searchParams.set("q", value);
                    } else {
                        target.searchParams.delete("q");
                    }

                    window.location.href = target.toString();
                } catch (error) {
                    if (value) {
                        const separator = action.includes("?") ? "&" : "?";
                        window.location.href = `${action}${separator}q=${encodeURIComponent(
                            value
                        )}`;
                        return;
                    }

                    window.location.href = action;
                }
            };

            return {
                hero: heroData,
                cta,
                term,
                submitSearch,
                searchPlaceholder,
                searchAction,
                labels,
                categoryTiles,
                visibleSections,
                trust:
                    typeof trust.text === "string"
                        ? { text: trust.text }
                        : { text: "" },
            };
        },
    });

    app.mount(el);
};

const mountProductIsland = (el) => {
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
        variants_heading:
            payload.labels?.variants_heading || "Variants",
        variant_sku:
            payload.labels?.variant_sku || "SKU",
        variant_price:
            payload.labels?.variant_price || "Price",
        variant_stock:
            payload.labels?.variant_stock || "Stock",
        variant_options:
            payload.labels?.variant_options || "Options",
        variant_none: payload.labels?.variant_none || "—",
    };

    const endpoints = payload.endpoints || {};
    const links = payload.links || {};
    const token = payload.token || "";

    const images = Array.isArray(product.images) ? product.images : [];
    const primaryImage = images.length ? images[0] : "";
    const primaryAlt =
        payload.primary_alt || product.title || labels.add_to_cart;

    el.innerHTML = "";

    const app = createApp({
        template: `
      <div v-cloak>
        <div class="nxp-ec-product__media">
          <figure v-if="primaryImage" class="nxp-ec-product__figure">
            <img :src="primaryImage" :alt="primaryAlt" loading="lazy" />
          </figure>
        </div>

        <div class="nxp-ec-product__summary">
          <h1 class="nxp-ec-product__title">{{ product.title }}</h1>

          <ul
            v-if="product.categories && product.categories.length"
            class="nxp-ec-product__categories"
          >
            <li
              v-for="category in product.categories"
              :key="category.id || category.slug || category.title"
            >
              {{ category.title }}
            </li>
          </ul>

          <div v-if="displayPrice" class="nxp-ec-product__price">
            {{ displayPrice }}
          </div>

          <p v-if="product.short_desc" class="nxp-ec-product__intro">
            {{ product.short_desc }}
          </p>

          <div class="nxp-ec-product__actions" v-if="variants.length">
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
        </div>

        <section
          v-if="product.long_desc_html"
          class="nxp-ec-product__description"
          v-html="product.long_desc_html"
        ></section>

        <section
          v-if="variants.length"
          class="nxp-ec-product__variants"
        >
          <h2 class="nxp-ec-product__variants-title">
            {{ labels.variants_heading }}
          </h2>

          <table class="nxp-ec-product__variants-table">
            <thead>
              <tr>
                <th scope="col">{{ labels.variant_sku }}</th>
                <th scope="col">{{ labels.variant_price }}</th>
                <th scope="col">{{ labels.variant_stock }}</th>
                <th scope="col">{{ labels.variant_options }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="variant in variants" :key="'row-' + variant.id">
                <td>{{ variant.sku }}</td>
                <td>{{ variant.price_label }}</td>
                <td>
                  <template v-if="variant.stock !== null">
                    {{ variant.stock }}
                  </template>
                  <template v-else>
                    {{ labels.variant_none }}
                  </template>
                </td>
                <td>
                  <ul
                    v-if="variant.options && variant.options.length"
                    class="nxp-ec-product__variant-options"
                  >
                    <li
                      v-for="(option, index) in variant.options"
                      :key="index"
                    >
                      <strong>{{ option.name }}:</strong>
                      {{ option.value }}
                    </li>
                  </ul>
                  <span
                    v-else
                    class="nxp-ec-product__variant-none"
                  >
                    {{ labels.variant_none }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </section>
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

            const displayPrice = computed(() => {
                if (
                    selectedVariant.value &&
                    selectedVariant.value.price_label
                ) {
                    return selectedVariant.value.price_label;
                }

                return product.price?.label || "";
            });

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
                    const formData = new FormData();

                    if (token) {
                        formData.append(token, "1");
                    }

                    formData.append(
                        "product_id",
                        String(product.id || "")
                    );
                    formData.append("qty", String(clampQty(state.qty)));

                    if (variant) {
                        formData.append("variant_id", String(variant.id));
                    }

                    let json = null;

                    const response = await fetch(endpoints.add, {
                        method: "POST",
                        body: formData,
                        headers: {
                            Accept: "application/json",
                        },
                    });

                    try {
                        json = await response.json();
                    } catch (error) {
                        // Non-JSON payloads fall through to the failure branch.
                    }

                    if (!response.ok || !json || json.success === false) {
                        const message =
                            (json && json.message) || labels.error_generic;
                        throw new Error(message);
                    }

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
                primaryImage,
                primaryAlt,
                state,
                add,
                displayPrice,
                isDisabled,
                isOutOfStock,
                maxQty,
                variantSelectId,
                qtyInputId,
            };
        },
    });

    app.mount(el);
};

const mountCategoryIsland = (el) => {
    const category = parsePayload(el.dataset.nxpCategory, {});
    const products = parsePayload(el.dataset.nxpProducts, []);
    const initialSearch = el.dataset.nxpSearch || "";

    el.innerHTML = "";

    const app = createApp({
        template: `
      <div class="nxp-ec-category" v-cloak>
        <header class="nxp-ec-category__header">
          <h1 class="nxp-ec-category__title">{{ title }}</h1>
          <div class="nxp-ec-category__search">
            <input
              type="search"
              class="nxp-ec-admin-search"
              v-model="search"
              :placeholder="searchPlaceholder"
            />
          </div>
        </header>

        <p v-if="filteredProducts.length === 0" class="nxp-ec-category__empty">
          {{ emptyCopy }}
        </p>

        <div v-else class="nxp-ec-category__grid">
          <article
            v-for="product in filteredProducts"
            :key="product.id"
            class="nxp-ec-product-card"
          >
            <figure v-if="product.images && product.images.length" class="nxp-ec-product-card__media">
              <img :src="product.images[0]" :alt="product.title" loading="lazy" />
            </figure>
            <div class="nxp-ec-product-card__body">
              <h2 class="nxp-ec-product-card__title">
                <a :href="product.link">{{ product.title }}</a>
              </h2>
              <p v-if="product.short_desc" class="nxp-ec-product-card__intro">
                {{ product.short_desc }}
              </p>
              <a class="nxp-ec-btn nxp-ec-btn--ghost" :href="product.link">
                {{ viewCopy }}
              </a>
            </div>
          </article>
        </div>
      </div>
    `,
        setup() {
            const title = category?.title || "Products";
            const search = ref(initialSearch);

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

const mountCartSummaryIsland = (el) => {
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
                currency: payload.currency || "USD",
            });

            const countLabel = computed(() => {
                if (state.count === 1) {
                    return labels.items_single || "1 item";
                }

                const template = labels.items_plural || "%d items";
                return template.replace("%d", state.count);
            });

            const totalLabel = computed(() =>
                formatMoney(state.total_cents, state.currency || "USD")
            );

            const applyCart = (cart) => {
                if (!cart) {
                    return;
                }

                const items = Array.isArray(cart.items) ? cart.items : [];
                let qty = 0;

                items.forEach((item) => {
                    qty += Number(item.qty || 0);
                });

                state.count = qty;
                state.total_cents = Number(
                    cart.summary?.total_cents || state.total_cents
                );
                state.currency =
                    cart.summary?.currency || state.currency || "USD";
            };

            window.addEventListener("nxp-cart:updated", (event) => {
                applyCart(event.detail);
            });

            return {
                state,
                labels,
                links,
                countLabel,
                totalLabel,
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
    product: mountProductIsland,
    category: mountCategoryIsland,
    landing: mountLandingIsland,
    cart: mountCartIsland,
    "cart-summary": mountCartSummaryIsland,
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
