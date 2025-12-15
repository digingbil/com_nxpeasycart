import {
    createApp,
    reactive,
    computed,
    ref,
    onMounted,
    onBeforeUnmount,
} from "vue";
import parsePayload from "../utils/parsePayload.js";
import formatMoney from "../utils/formatMoney.js";
import { createApiClient } from "../utils/apiClient.js";
import { useImageRotator } from "../utils/useImageRotator.js";

export default function mountCategoryIsland(el) {
    const category = parsePayload(el.dataset.nxpCategory, {});
    const productsPayload = parsePayload(el.dataset.nxpProducts, []);
    const categoriesPayload = parsePayload(el.dataset.nxpCategories, []);
    const labelsPayload = parsePayload(el.dataset.nxpLabels, {});
    const linksPayload = parsePayload(el.dataset.nxpLinks, {});
    const paginationPayload = parsePayload(el.dataset.nxpPagination, {});
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
        out_of_stock:
            labelsPayload.out_of_stock || "This product is currently out of stock.",
        error_generic:
            labelsPayload.error_generic ||
            "We couldn't add this item to your cart. Please try again.",
        select_variant:
            labelsPayload.select_variant || "Choose a variant to continue",
        prev: labelsPayload.prev || "Previous",
        next: labelsPayload.next || "Next",
        pagination_label:
            labelsPayload.pagination_label || "Pagination navigation",
        page_of: labelsPayload.page_of || "Page %s of %s",
        load_more: labelsPayload.load_more || "Load more",
        loading_more: labelsPayload.loading_more || "Loading...",
        no_more: labelsPayload.no_more || "No more products to show",
        load_error:
            labelsPayload.load_error ||
            "Unable to load more products right now. Please try again.",
        sale_badge: labelsPayload.sale_badge || "Sale",
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

    const normaliseNumber = (value, fallback = 0) => {
        const numeric = Number.parseInt(value, 10);

        return Number.isFinite(numeric) ? numeric : fallback;
    };

    const normaliseProducts = (items = []) =>
        (Array.isArray(items) ? items : [])
            .filter((item) => item && typeof item === "object")
            .map((item) => {
                const price =
                    item.price && typeof item.price === "object"
                        ? item.price
                        : {};
                const min = normaliseCents(price.min_cents);
                const max = normaliseCents(price.max_cents);
                const effectiveMin = normaliseCents(price.effective_min_cents);
                const effectiveMax = normaliseCents(price.effective_max_cents);
                const hasActiveSale = Boolean(price.has_active_sale);
                const currency =
                    typeof price.currency === "string" && price.currency !== ""
                        ? price.currency
                        : currencyAttr || "USD";

                let priceLabel =
                    typeof item.price_label === "string"
                        ? item.price_label
                        : "";
                let regularPriceLabel =
                    typeof price.regular_label === "string"
                        ? price.regular_label
                        : "";

                const status = Number.isFinite(Number(item.status))
                    ? Number(item.status)
                    : item.active
                      ? 1
                      : 0;
                const outOfStock =
                    item.out_of_stock !== undefined
                        ? Boolean(item.out_of_stock)
                        : status === -1;

                // Use effective prices (with sale) if available, else regular
                const displayMin = effectiveMin !== null ? effectiveMin : min;
                const displayMax = effectiveMax !== null ? effectiveMax : max;

                if (!priceLabel && displayMin !== null && displayMax !== null) {
                    if (displayMin === displayMax) {
                        priceLabel = formatMoney(displayMin, currency, locale);
                    } else {
                        priceLabel = `${formatMoney(displayMin, currency, locale)} - ${formatMoney(
                            displayMax,
                            currency,
                            locale
                        )}`;
                    }
                }

                // Generate regular price label for sale display
                if (hasActiveSale && !regularPriceLabel && min !== null && max !== null) {
                    if (min === max) {
                        regularPriceLabel = formatMoney(min, currency, locale);
                    } else {
                        regularPriceLabel = `${formatMoney(min, currency, locale)} - ${formatMoney(
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
                        effective_min_cents: effectiveMin,
                        effective_max_cents: effectiveMax,
                        has_active_sale: hasActiveSale,
                    },
                    price_label: priceLabel,
                    regular_price_label: regularPriceLabel,
                    has_active_sale: hasActiveSale,
                    primary_variant_id: Number.isFinite(primaryVariantId)
                        ? primaryVariantId
                        : null,
                    variant_count: Number.isFinite(variantCount)
                        ? variantCount
                        : null,
                    status,
                    out_of_stock: outOfStock,
                    hint:
                        outOfStock && labels.out_of_stock
                            ? labels.out_of_stock
                            : "",
                };
            });

    const state = reactive({
        items: normaliseProducts(products),
        loadingMore: false,
        loadError: "",
    });

    const pagination = reactive({
        total: normaliseNumber(
            paginationPayload.total,
            state.items.length
        ),
        limit:
            normaliseNumber(
                paginationPayload.limit,
                state.items.length || 12
            ) || 12,
        start: normaliseNumber(paginationPayload.start, 0),
        pages: Math.max(
            1,
            normaliseNumber(paginationPayload.pages, 1)
        ),
        current: Math.max(
            1,
            normaliseNumber(paginationPayload.current, 1)
        ),
        mode:
            paginationPayload.mode === "infinite"
                ? "infinite"
                : "paged",
        base:
            typeof paginationPayload.base === "string" &&
            paginationPayload.base !== ""
                ? paginationPayload.base
                : links.search,
        search:
            typeof paginationPayload.search === "string"
                ? paginationPayload.search
                : initialSearch,
    });

    pagination.total = Math.max(
        pagination.total || state.items.length,
        state.items.length
    );
    pagination.pages = Math.max(1, pagination.pages || 1);
    pagination.current = Math.max(1, pagination.current || 1);

    const filters = categories
        .filter((item) => item && typeof item === "object")
        .map((item, index) => ({
            ...item,
            id: item.id || item.slug || item.title || index,
            title: item.title || "",
            slug: item.slug || "",
            link: item.link || links.all,
        }));

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
            <div
              class="nxp-ec-product-card__media"
              @mouseenter="startCycle(product)"
              @mouseleave="stopCycle(product)"
              @focusin="startCycle(product)"
              @focusout="stopCycle(product)"
            >
              <a
                v-if="product.images.length"
                class="nxp-ec-product-card__image-link"
                :href="product.link"
                :aria-label="labels.view_product + ': ' + product.title"
              >
                <transition name="nxp-ec-fade" mode="out-in">
                  <img
                    :key="activeImage(product)"
                    :src="activeImage(product)"
                    :alt="product.title"
                    loading="lazy"
                  />
                </transition>
              </a>
              <button
                type="button"
                class="nxp-ec-quick-add"
                :aria-label="labels.add_to_cart + ': ' + product.title"
                :class="{
                  'is-disabled': product.out_of_stock || quickState[keyFor(product)]?.loading
                }"
                :disabled="product.out_of_stock || quickState[keyFor(product)]?.loading"
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
              <p v-if="product.price_label" class="nxp-ec-product-card__price" :class="{ 'nxp-ec-product-card__price--sale': product.has_active_sale }">
                <span v-if="product.has_active_sale" class="nxp-ec-product-card__sale-badge">{{ labels.sale_badge }}</span>
                <span v-if="product.has_active_sale && product.regular_price_label" class="nxp-ec-product-card__regular-price">{{ product.regular_price_label }}</span>
                <span :class="{ 'nxp-ec-product-card__sale-price': product.has_active_sale }">{{ product.price_label }}</span>
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
              <p
                v-else-if="product.out_of_stock"
                class="nxp-ec-product-card__hint nxp-ec-product-card__hint--alert"
              >
                {{ product.hint }}
              </p>
            </div>
          </article>
        </div>

        <div
          v-if="pagination.pages > 1 || pagination.mode === 'infinite'"
          class="nxp-ec-category__pagination-shell"
        >
          <template v-if="pagination.mode === 'paged'">
            <nav
              v-if="pagination.pages > 1"
              class="nxp-ec-category__pagination"
              :aria-label="labels.pagination_label"
            >
              <span class="nxp-ec-category__pagination-meta">
                {{ pageSummary }}
              </span>
              <div class="nxp-ec-category__pagination-links">
                <a
                  v-if="prevLink"
                  class="nxp-ec-category__pagination-link"
                  :href="prevLink"
                >
                  {{ labels.prev }}
                </a>
                <a
                  v-if="nextLink"
                  class="nxp-ec-category__pagination-link"
                  :href="nextLink"
                >
                  {{ labels.next }}
                </a>
              </div>
            </nav>
          </template>
          <template v-else>
            <div class="nxp-ec-category__load-more">
              <button
                v-if="hasMore"
                type="button"
                class="nxp-ec-btn nxp-ec-btn--ghost nxp-ec-category__load-more-button"
                :disabled="loadingMore"
                @click="loadMore"
              >
                <span
                  v-if="loadingMore"
                  class="nxp-ec-category__spinner"
                  aria-hidden="true"
                ></span>
                {{ loadingMore ? labels.loading_more : labels.load_more }}
              </button>
              <span
                v-else
                class="nxp-ec-category__load-more-label"
              >
                {{ labels.no_more }}
              </span>
              <p
                v-if="loadError"
                class="nxp-ec-category__hint nxp-ec-category__hint--error"
              >
                {{ loadError }}
              </p>
            </div>
            <div ref="sentinel" class="nxp-ec-category__sentinel" aria-hidden="true"></div>
          </template>
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

            const filteredProducts = computed(() => state.items);

            const isActive = (filter) => {
                const slug =
                    typeof filter.slug === "string" ? filter.slug : "";

                return slug === activeSlug;
            };

            const quickState = reactive({});
            const keyFor = (product) =>
                product.id || product.slug || product.title || "product";
            const { activeImage, startCycle, stopCycle } =
                useImageRotator(keyFor);

            const ensureState = (key) => {
                if (!quickState[key]) {
                    quickState[key] = { loading: false, error: "", success: false };
                }

                return quickState[key];
            };

            const hasNextPage = computed(
                () => pagination.current < pagination.pages
            );
            const hasPrevPage = computed(() => pagination.current > 1);
            const hasMore = computed(
                () => state.items.length < pagination.total
            );

            const buildPageUrl = (startValue, format = "json") => {
                const base = pagination.base || links.search || "";

                try {
                    const url = new URL(base, window.location.origin);
                    const limit = Number.parseInt(pagination.limit, 10);
                    const start = Number.parseInt(startValue, 10);
                    const searchValue =
                        (pagination.search || initialSearch || "").trim();

                    if (Number.isFinite(limit) && limit > 0) {
                        url.searchParams.set("limit", String(limit));
                    } else {
                        url.searchParams.delete("limit");
                    }

                    if (Number.isFinite(start) && start > 0) {
                        url.searchParams.set("start", String(start));
                    } else {
                        url.searchParams.delete("start");
                    }

                    if (searchValue) {
                        url.searchParams.set("q", searchValue);
                    } else {
                        url.searchParams.delete("q");
                    }

                    if (format === "json") {
                        url.searchParams.set("format", "json");
                    } else {
                        url.searchParams.delete("format");
                    }

                    return url.toString();
                } catch (error) {
                    return base;
                }
            };

            const prevLink = computed(() => {
                if (!hasPrevPage.value) {
                    return "";
                }

                const limit = Number.isFinite(Number(pagination.limit))
                    ? Number(pagination.limit)
                    : 0;
                const start = Number.isFinite(Number(pagination.start))
                    ? Number(pagination.start)
                    : 0;
                const prevStart = Math.max(0, start - limit);

                return buildPageUrl(prevStart, "html");
            });

            const nextLink = computed(() => {
                if (!hasNextPage.value) {
                    return "";
                }

                const limit = Number.isFinite(Number(pagination.limit))
                    ? Number(pagination.limit)
                    : 0;
                const startValue = Number.isFinite(Number(pagination.start))
                    ? Number(pagination.start) + limit
                    : state.items.length;

                return buildPageUrl(startValue, "html");
            });

            const pageSummary = computed(() => {
                const template = labels.page_of || "Page %s of %s";

                return template
                    .replace("%s", pagination.current || 1)
                    .replace("%s", pagination.pages || 1);
            });

            const applyPagination = (payload = {}) => {
                const numericKeys = [
                    "total",
                    "limit",
                    "start",
                    "pages",
                    "current",
                ];

                numericKeys.forEach((key) => {
                    const numeric = normaliseNumber(payload[key], NaN);

                    if (Number.isFinite(numeric)) {
                        pagination[key] = numeric;
                    }
                });

                if (
                    payload.mode === "infinite" ||
                    payload.mode === "paged"
                ) {
                    pagination.mode = payload.mode;
                }

                if (typeof payload.search === "string") {
                    pagination.search = payload.search;
                }

                pagination.total = Math.max(
                    pagination.total || state.items.length,
                    state.items.length
                );
                pagination.pages = Math.max(1, pagination.pages || 1);
                pagination.current = Math.max(1, pagination.current || 1);
            };

            const loadMore = async () => {
                if (state.loadingMore || !hasMore.value) {
                    return;
                }

                const limit = Number.isFinite(Number(pagination.limit))
                    ? Number(pagination.limit)
                    : 0;
                const startValue =
                    Number.isFinite(Number(pagination.start)) && limit > 0
                        ? Number(pagination.start) + limit
                        : state.items.length;

                state.loadingMore = true;
                state.loadError = "";

                try {
                    const response = await fetch(
                        buildPageUrl(startValue, "json"),
                        {
                            headers: { Accept: "application/json" },
                        }
                    );

                    if (!response.ok) {
                        throw new Error(labels.error_generic);
                    }

                    const json = await response.json();
                    const data =
                        json && typeof json === "object" && json.data && typeof json.data === "object"
                            ? json.data
                            : json;
                    const newProducts = normaliseProducts(
                        data.products || []
                    );

                    if (newProducts.length) {
                        state.items.push(...newProducts);
                    }

                    applyPagination(data.pagination || {});
                } catch (error) {
                    state.loadError =
                        (error && error.message) || labels.load_error;
                } finally {
                    state.loadingMore = false;
                }
            };

            const quickAdd = async (product) => {
                const key = keyFor(product);
                const stateRef = ensureState(key);
                stateRef.loading = false;
                stateRef.error = "";
                stateRef.success = false;

                if (!cartEndpoints.add) {
                    window.location.href = product.link || links.search;
                    return;
                }

                if (product?.out_of_stock) {
                    stateRef.error = labels.out_of_stock;
                    return;
                }

                if (
                    !product ||
                    !product.primary_variant_id ||
                    (product.variant_count &&
                        Number(product.variant_count) > 1)
                ) {
                    stateRef.error = labels.select_variant;
                    window.location.href = product.link || links.search;
                    return;
                }

                stateRef.loading = true;

                try {
                    const json = await api.postForm(cartEndpoints.add, {
                        product_id: String(product.id || ""),
                        variant_id: String(product.primary_variant_id),
                        qty: "1",
                    });

                    const cart = json.data?.cart || null;

                    stateRef.success = true;

                    if (cart) {
                        window.dispatchEvent(
                            new CustomEvent("nxp-cart:updated", {
                                detail: cart,
                            })
                        );
                    }
                } catch (error) {
                    stateRef.error =
                        (error && error.message) || labels.error_generic;
                } finally {
                    stateRef.loading = false;
                }
            };

            const sentinel = ref(null);
            let observer = null;

            const observeMore = () => {
                if (pagination.mode !== "infinite") {
                    return;
                }

                const target = sentinel.value;

                if (!target || typeof IntersectionObserver === "undefined") {
                    return;
                }

                observer = new IntersectionObserver(
                    (entries) => {
                        entries.forEach((entry) => {
                            if (entry.isIntersecting) {
                                loadMore();
                            }
                        });
                    },
                    { rootMargin: "200px 0px", threshold: 0.1 }
                );

                observer.observe(target);
            };

            onMounted(observeMore);
            onBeforeUnmount(() => {
                if (observer) {
                    observer.disconnect();
                    observer = null;
                }
            });

            return {
                title,
                labels,
                filters,
                links,
                cartLinks,
                search,
                searchId,
                filteredProducts,
                isActive,
                quickAdd,
                quickState,
                keyFor,
                activeImage,
                startCycle,
                stopCycle,
                pagination,
                hasMore,
                loadMore,
                loadingMore: computed(() => state.loadingMore),
                loadError: computed(() => state.loadError),
                prevLink,
                nextLink,
                pageSummary,
                sentinel,
            };
        },
    });

    app.mount(el);
}
