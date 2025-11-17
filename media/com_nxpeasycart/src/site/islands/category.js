import { createApp, reactive, computed, ref, watch } from "vue";
import parsePayload from "../utils/parsePayload.js";
import formatMoney from "../utils/formatMoney.js";
import { createApiClient } from "../utils/apiClient.js";

export default function mountCategoryIsland(el) {
    const category = parsePayload(el.dataset.nxpCategory, {});
    const productsPayload = parsePayload(el.dataset.nxpProducts, []);
    const categoriesPayload = parsePayload(el.dataset.nxpCategories, []);
    const labelsPayload = parsePayload(el.dataset.nxpLabels, {});
    const linksPayload = parsePayload(el.dataset.nxpLinks, {});
    const initialSearch = (el.dataset.nxpSearch || "").trim();

    const normaliseCents = (value) => {
        if (value === null || value === undefined || value === "") {
            return null;
        }

        const numeric = Number.parseInt(value, 10);

        return Number.isFinite(numeric) ? numeric : null;
    };

    const labels = {
        filters: labelsPayload.filters || "Categories",
        filter_all: labelsPayload.filter_all || "All",
        empty:
            labelsPayload.empty ||
            "No products found in this category yet.",
        view_product: labelsPayload.view_product || "View product",
        search_placeholder:
            labelsPayload.search_placeholder || "Search products",
        search_label:
            labelsPayload.search_label ||
            labelsPayload.search_placeholder ||
            "Search products",
        add_to_cart: labelsPayload.add_to_cart || "Add to cart",
        added: labelsPayload.added || "Added to cart",
        view_cart: labelsPayload.view_cart || "View cart",
        out_of_stock: labelsPayload.out_of_stock || "Out of stock",
        error_generic:
            labelsPayload.error_generic ||
            "We couldn't add this item to your cart. Please try again.",
        select_variant:
            labelsPayload.select_variant || "Choose a variant to continue",
    };

    const links = {
        all:
            typeof linksPayload.all === "string" && linksPayload.all !== ""
                ? linksPayload.all
                : "index.php?option=com_nxpeasycart&view=category",
        search:
            typeof linksPayload.search === "string" &&
            linksPayload.search !== ""
                ? linksPayload.search
                : "index.php?option=com_nxpeasycart&view=category",
    };

    const cartPayload = parsePayload(el.dataset.nxpCart, {});
    const cartToken = cartPayload.token || "";
    const cartEndpoints = cartPayload.endpoints || {};
    const cartLinks = cartPayload.links || {};
    const api = createApiClient(cartToken);
    const locale = el.dataset.nxpLocale || undefined;
    const currencyAttr = (el.dataset.nxpCurrency || "").trim() || undefined;

    const products = Array.isArray(productsPayload)
        ? productsPayload
        : [];
    const categories = Array.isArray(categoriesPayload)
        ? categoriesPayload
        : [];

    const activeSlug =
        category && typeof category.slug === "string"
            ? category.slug
            : "";
    const searchId = `nxp-ec-category-search-${category?.id || "all"}`;

    const enrichedProducts = products
        .filter((item) => item && typeof item === "object")
        .map((item) => {
            const price =
                item.price && typeof item.price === "object"
                    ? item.price
                    : {};
            const min = normaliseCents(price.min_cents);
            const max = normaliseCents(price.max_cents);
            const currency =
                typeof price.currency === "string" && price.currency !== ""
                    ? price.currency
                    : currencyAttr || "USD";

            let priceLabel =
                typeof item.price_label === "string"
                    ? item.price_label
                    : "";

            if (!priceLabel && min !== null && max !== null) {
                if (min === max) {
                    priceLabel = formatMoney(min, currency, locale);
                } else {
                    priceLabel = `${formatMoney(min, currency, locale)} - ${formatMoney(
                        max,
                        currency,
                        locale
                    )}`;
                }
            }

            const images = Array.isArray(item.images)
                ? item.images
                      .filter(
                          (image) =>
                              typeof image === "string" &&
                              image.trim() !== ""
                      )
                      .map((image) => image.trim())
                : [];
            const primaryVariantId = Number.parseInt(
                item.primary_variant_id,
                10
            );
            const variantCount = Number.parseInt(item.variant_count, 10);

            return {
                ...item,
                title:
                    typeof item.title === "string"
                        ? item.title
                        : "",
                short_desc:
                    typeof item.short_desc === "string"
                        ? item.short_desc
                        : "",
                link:
                    typeof item.link === "string" && item.link !== ""
                        ? item.link
                        : "#",
                images,
                price: {
                    currency,
                    min_cents: min,
                    max_cents: max,
                },
                price_label: priceLabel,
                primary_variant_id: Number.isFinite(primaryVariantId)
                    ? primaryVariantId
                    : null,
                variant_count: Number.isFinite(variantCount)
                    ? variantCount
                    : null,
            };
        });

    const filters = categories
        .filter((item) => item && typeof item === "object")
        .map((item, index) => ({
            ...item,
            id: item.id || item.slug || item.title || index,
            title: item.title || "",
            slug: item.slug || "",
            link: item.link || links.all,
        }));

    const updateUrl = (value) => {
        if (
            typeof window === "undefined" ||
            !window.history ||
            typeof window.history.replaceState !== "function"
        ) {
            return;
        }

        try {
            const url = new URL(window.location.href);

            if (value) {
                url.searchParams.set("q", value);
            } else {
                url.searchParams.delete("q");
            }

            window.history.replaceState({}, "", url.toString());
        } catch (error) {
            // Ignore URL parsing failures.
        }
    };

    el.innerHTML = "";

    const app = createApp({
        template: `
      <div class="nxp-ec-category" v-cloak>
        <header class="nxp-ec-category__header">
          <h1 class="nxp-ec-category__title">{{ title }}</h1>
          <form
            class="nxp-ec-category__search"
            method="get"
            :action="links.search"
            @submit.prevent="submitSearch"
          >
            <label class="sr-only" :for="searchId">{{ labels.search_label }}</label>
            <input
              :id="searchId"
              type="search"
              name="q"
              v-model="search"
              :placeholder="labels.search_placeholder"
            />
          </form>
          <nav
            v-if="filters.length"
            class="nxp-ec-category__filters"
            :aria-label="labels.filters"
          >
            <a
              v-for="filter in filters"
              :key="filter.slug || filter.id || filter.title"
              class="nxp-ec-category__filter"
              :class="{ 'is-active': isActive(filter) }"
              :href="filter.link"
            >
              {{ filter.title }}
            </a>
          </nav>
        </header>

        <p
          v-if="filteredProducts.length === 0"
          class="nxp-ec-category__empty"
        >
          {{ labels.empty }}
        </p>

        <div v-else class="nxp-ec-category__grid">
          <article
            v-for="product in filteredProducts"
            :key="product.id || product.slug || product.title"
            class="nxp-ec-product-card"
          >
            <div class="nxp-ec-product-card__media">
              <a
                v-if="product.images.length"
                class="nxp-ec-product-card__image-link"
                :href="product.link"
                :aria-label="labels.view_product + ': ' + product.title"
              >
                <img :src="product.images[0]" :alt="product.title" loading="lazy" />
              </a>
              <button
                type="button"
                class="nxp-ec-quick-add"
                :aria-label="labels.add_to_cart + ': ' + product.title"
                :disabled="quickState[keyFor(product)]?.loading"
                @click="quickAdd(product)"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="24"
                  height="24"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  class="nxp-ec-quick-add__icon"
                  aria-hidden="true"
                >
                  <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                  <path d="M4 19a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" />
                  <path d="M12.5 17h-6.5v-14h-2" />
                  <path d="M6 5l14 1l-.86 6.017m-2.64 .983h-10.5" />
                  <path d="M16 19h6" />
                  <path d="M19 16v6" />
                </svg>
                <span class="nxp-ec-sr-only">{{ labels.add_to_cart }}</span>
              </button>
            </div>
            <div class="nxp-ec-product-card__body">
              <h2 class="nxp-ec-product-card__title">
                <a :href="product.link">{{ product.title }}</a>
              </h2>
              <p v-if="product.short_desc" class="nxp-ec-product-card__intro">
                {{ product.short_desc }}
              </p>
              <p v-if="product.price_label" class="nxp-ec-product-card__price">
                {{ product.price_label }}
              </p>
              <div class="nxp-ec-product-card__actions">
                <a class="nxp-ec-btn nxp-ec-btn--ghost" :href="product.link">
                  {{ labels.view_product }}
                </a>
              </div>
              <p
                v-if="quickState[keyFor(product)]?.error"
                class="nxp-ec-product-card__hint nxp-ec-product-card__hint--error"
              >
                {{ quickState[keyFor(product)].error }}
              </p>
              <p
                v-else-if="quickState[keyFor(product)]?.success"
                class="nxp-ec-product-card__hint"
              >
                {{ labels.added }}
                <template v-if="cartLinks.cart">
                  · <a :href="cartLinks.cart">{{ labels.view_cart }}</a>
                </template>
              </p>
            </div>
          </article>
        </div>
      </div>
    `,
        setup() {
            const title =
                (category &&
                    typeof category.title === "string" &&
                    category.title) ||
                "Products";
            const search = ref(initialSearch);

            const filteredProducts = computed(() => {
                const term = search.value.trim().toLowerCase();

                if (!term) {
                    return enrichedProducts;
                }

                return enrichedProducts.filter((product) => {
                    const haystack = `${product.title} ${
                        product.short_desc || ""
                    }`.toLowerCase();

                    return haystack.includes(term);
                });
            });

            watch(
                search,
                (value, previous) => {
                    const next = value.trim();

                    if (previous !== undefined && previous.trim() === next) {
                        return;
                    }

                    updateUrl(next);
                },
                { immediate: true }
            );

            const submitSearch = () => {
                updateUrl(search.value.trim());
            };

            const isActive = (filter) => {
                const slug =
                    typeof filter.slug === "string" ? filter.slug : "";

                return slug === activeSlug;
            };

            const quickState = reactive({});
            const keyFor = (product) =>
                product.id || product.slug || product.title || "product";

            const hasSingleVariant = (product) => {
                const count = Number.parseInt(
                    product && product.variant_count,
                    10
                );

                if (Number.isFinite(count)) {
                    return count === 1;
                }

                return !!(product && product.primary_variant_id);
            };

            const ensureState = (key) => {
                if (!quickState[key]) {
                    quickState[key] = { loading: false, error: "", success: false };
                }

                return quickState[key];
            };

            const quickAdd = async (product) => {
                const key = keyFor(product);
                const state = ensureState(key);
                state.loading = false;
                state.error = "";
                state.success = false;

                if (!cartEndpoints.add) {
                    window.location.href = product.link || links.search;
                    return;
                }

                if (!hasSingleVariant(product)) {
                    state.error = labels.select_variant;
                    window.location.href = product.link || links.search;
                    return;
                }

                if (!product || !product.primary_variant_id) {
                    state.error = labels.error_generic;
                    return;
                }

                state.loading = true;

                try {
                    const json = await api.postForm(cartEndpoints.add, {
                        product_id: String(product.id || ""),
                        variant_id: String(product.primary_variant_id),
                        qty: "1",
                    });

                    const cart = json.data?.cart || null;

                    state.success = true;

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
                title,
                labels,
                products: enrichedProducts,
                filters,
                links,
                cartLinks,
                search,
                searchId,
                filteredProducts,
                submitSearch,
                isActive,
                quickAdd,
                quickState,
                keyFor,
            };
        },
    });

    app.mount(el);
}
