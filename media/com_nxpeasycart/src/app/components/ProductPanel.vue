<template>
    <section class="nxp-ec-admin-panel">
        <header class="nxp-ec-admin-panel__header">
            <div>
                <h2 class="nxp-ec-admin-panel__title">
                    {{
                        __(
                            "COM_NXPEASYCART_MENU_PRODUCTS",
                            "Products",
                            [],
                            "productsPanelTitle"
                        )
                    }}
                </h2>
                <p class="nxp-ec-admin-panel__lead">
                    {{
                        __(
                            "COM_NXPEASYCART_PRODUCTS_LEAD",
                            "Manage products from a single dashboard.",
                            [],
                            "productsPanelLead"
                        )
                    }}
                </p>
            </div>
            <div class="nxp-ec-admin-panel__actions">
                <input
                    type="search"
                    class="nxp-ec-admin-search"
                    :placeholder="
                        __(
                            'COM_NXPEASYCART_PRODUCTS_SEARCH_PLACEHOLDER',
                            'Search products',
                            [],
                            'productsSearchPlaceholder'
                        )
                    "
                    v-model="state.search"
                    @keyup.enter="emitSearch"
                    :aria-label="
                        __(
                            'COM_NXPEASYCART_PRODUCTS_SEARCH_PLACEHOLDER',
                            'Search products',
                            [],
                            'productsSearchPlaceholder'
                        )
                    "
                />
                <button
                    class="nxp-ec-btn nxp-ec-btn--icon"
                    type="button"
                    @click="emitRefresh"
                    :disabled="state.loading"
                    :title="__(
                        'COM_NXPEASYCART_PRODUCTS_REFRESH',
                        'Refresh',
                        [],
                        'productsRefresh'
                    )"
                    :aria-label="__(
                        'COM_NXPEASYCART_PRODUCTS_REFRESH',
                        'Refresh',
                        [],
                        'productsRefresh'
                    )"
                >
                    <i class="fa-solid fa-rotate"></i>
                    <span class="nxp-ec-sr-only">
                        {{
                            __(
                                "COM_NXPEASYCART_PRODUCTS_REFRESH",
                                "Refresh",
                                [],
                                "productsRefresh"
                            )
                        }}
                    </span>
                </button>
                <button
                    class="nxp-ec-btn nxp-ec-btn--primary"
                    type="button"
                    @click="openCreate"
                >
                    <i class="fa-solid fa-plus"></i>
                    {{ __("COM_NXPEASYCART_PRODUCTS_ADD", "Add product") }}
                </button>
            </div>
        </header>

        <div v-if="state.error" class="nxp-ec-admin-alert nxp-ec-admin-alert--error">
            {{ state.error }}
        </div>

        <div v-if="state.loading" class="nxp-ec-admin-panel__body">
            <SkeletonLoader type="table" :rows="5" :columns="5" />
        </div>

        <div v-if="!state.loading" class="nxp-ec-admin-panel__body">
            <ProductTable
                :items="state.items"
                :translate="__"
                :base-currency="baseCurrency"
                :saving="state.saving"
                :current-user-id="currentUserId"
                @edit="openEdit"
                @delete="confirmDelete"
                @toggle-active="toggleActive"
            />

            <!-- Pagination -->
            <div
                class="nxp-ec-admin-pagination"
                v-if="state.pagination?.pages > 1"
            >
                <button
                    class="nxp-ec-btn"
                    type="button"
                    :disabled="state.pagination.current <= 1"
                    @click="emitPage(state.pagination.current - 1)"
                >
                    ‹
                </button>
                <span class="nxp-ec-admin-pagination__status">
                    {{ state.pagination.current }} /
                    {{ state.pagination.pages }}
                </span>
                <button
                    class="nxp-ec-btn"
                    type="button"
                    :disabled="state.pagination.current >= state.pagination.pages"
                    @click="emitPage(state.pagination.current + 1)"
                >
                    ›
                </button>
            </div>

            <div
                v-if="state.lastUpdated"
                class="nxp-ec-admin-panel__metadata"
                :title="state.lastUpdated"
            >
                {{ __("COM_NXPEASYCART_LAST_UPDATED", "Last updated") }}:
                {{ formatTimestamp(state.lastUpdated) }}
            </div>
        </div>

        <ProductEditor
            :open="isEditorOpen"
            :product="editorProduct"
            :saving="state.saving"
            :base-currency="baseCurrency"
            :translate="__"
            :errors="state.validationErrors"
            :category-options="categoryOptions"
            :media-modal-url="mediaModalUrl"
            :digital-endpoints="digitalEndpoints"
            :csrf-token="csrfToken"
            @submit="handleSubmit"
            @cancel="closeEditor"
        />
    </section>
</template>

<script setup>
import { computed, reactive, ref, watch, onBeforeUnmount } from "vue";
import ProductTable from "./ProductTable.vue";
import ProductEditor from "./ProductEditor.vue";
import SkeletonLoader from "./SkeletonLoader.vue";

const props = defineProps({
    state: {
        type: Object,
        required: true,
    },
    translate: {
        type: Function,
        required: true,
    },
    baseCurrency: {
        type: String,
        default: "USD",
    },
    categoryOptions: {
        type: Array,
        default: () => [],
    },
    mediaModalUrl: {
        type: String,
        default: "",
    },
    digitalEndpoints: {
        type: Object,
        default: () => ({}),
    },
    csrfToken: {
        type: String,
        default: "",
    },
    checkoutProduct: {
        type: Function,
        default: null,
    },
    checkinProduct: {
        type: Function,
        default: null,
    },
    forceCheckinProduct: {
        type: Function,
        default: null,
    },
    currentUserId: {
        type: Number,
        default: 0,
    },
});

const emit = defineEmits(["create", "update", "delete", "refresh", "search", "page"]);

const __ = props.translate;

const editorState = reactive({
    mode: "create",
    product: null,
});

const isEditorOpen = ref(false);
const lockedProductId = ref(null);

const baseCurrency = computed(() =>
    (props.baseCurrency || "USD").toUpperCase()
);

const editorProduct = computed(() => editorState.product);

const mediaModalUrl = computed(() => (props.mediaModalUrl || "").trim());
const digitalEndpoints = computed(() => props.digitalEndpoints || {});
const csrfToken = computed(() => props.csrfToken || "");
const currentUserId = computed(() => Number(props.currentUserId || 0));

const STATUS_ACTIVE = 1;
const STATUS_OUT_OF_STOCK = -1;
const STATUS_INACTIVE = 0;

const normaliseStatus = (value, outOfStockFlag = false) => {
    const numeric = Number(value);

    if (Number.isFinite(numeric)) {
        if (numeric === STATUS_OUT_OF_STOCK) {
            return STATUS_OUT_OF_STOCK;
        }

        if (numeric === STATUS_INACTIVE) {
            return STATUS_INACTIVE;
        }

        return STATUS_ACTIVE;
    }

    if (outOfStockFlag) {
        return STATUS_OUT_OF_STOCK;
    }

    if (typeof value === "string") {
        const trimmed = value.trim().toLowerCase();

        if (trimmed === "out_of_stock" || trimmed === "out-of-stock" || trimmed === "-1") {
            return STATUS_OUT_OF_STOCK;
        }

        if (trimmed === "inactive" || trimmed === "0") {
            return STATUS_INACTIVE;
        }
    }

    return STATUS_ACTIVE;
};

const cycleStatus = (value, outOfStockFlag = false) => {
    const status = normaliseStatus(value, outOfStockFlag);

    if (status === STATUS_ACTIVE) {
        return STATUS_OUT_OF_STOCK;
    }

    if (status === STATUS_OUT_OF_STOCK) {
        return STATUS_INACTIVE;
    }

    return STATUS_ACTIVE;
};

const isLockedByOther = (product) =>
    product?.checked_out &&
    Number(product.checked_out) !== 0 &&
    Number(product.checked_out) !== currentUserId.value;

const lockOwnerName = (product) => {
    if (!product) {
        return "";
    }

    const name = product.checked_out_user?.name ?? "";

    if (name) {
        return name;
    }

    if (product.checked_out === currentUserId.value) {
        return __("JGLOBAL_YOU", "You");
    }

    return "";
};

const releaseLock = async () => {
    if (!lockedProductId.value || !props.checkinProduct) {
        lockedProductId.value = null;
        return;
    }

    try {
        await props.checkinProduct(lockedProductId.value);
    } catch (error) {
        // Best-effort check-in; ignore failures here.
    } finally {
        lockedProductId.value = null;
    }
};

const openCreate = async () => {
    await releaseLock();
    props.state.validationErrors = [];
    props.state.error = "";
    editorState.mode = "create";
    editorState.product = {
        title: "",
        slug: "",
        short_desc: "",
        long_desc: "",
        status: STATUS_ACTIVE,
        active: true,
        featured: false,
        images: [],
        categories: [],
        variants: [],
        product_type: "physical",
    };
    isEditorOpen.value = true;
};

const openEdit = async (product) => {
    await releaseLock();
    props.state.validationErrors = [];
    props.state.error = "";
    editorState.mode = "edit";
    const payload = JSON.parse(JSON.stringify(product));
    if (!payload.product_type) {
        payload.product_type = "physical";
    }
    const lockedByName = lockOwnerName(product);

    if (isLockedByOther(product)) {
        const forced = await forceCheckoutIfAllowed(product, lockedByName);

        if (!forced) {
            props.state.error =
                lockedByName !== ""
                    ? __(
                          "COM_NXPEASYCART_ERROR_PRODUCT_CHECKED_OUT",
                          "This product is currently checked out by %s.",
                          [lockedByName]
                      )
                    : __(
                          "COM_NXPEASYCART_ERROR_PRODUCT_CHECKED_OUT_GENERIC",
                          "This product is currently checked out by another user."
                      );

            return;
        }

        return;
    }

    if (product?.id && props.checkoutProduct) {
        try {
            const locked = await props.checkoutProduct(product.id);
            if (locked) {
                lockedProductId.value = locked.id;
                editorState.product = JSON.parse(JSON.stringify(locked));
                isEditorOpen.value = true;
                return;
            }
        } catch (error) {
            props.state.error =
                error?.message ||
                __(
                    "COM_NXPEASYCART_ERROR_PRODUCT_CHECKED_OUT_GENERIC",
                    "Unable to edit this product right now."
                );
            return;
        }
    }

    editorState.product = payload;
    isEditorOpen.value = true;
};

const forceCheckoutIfAllowed = async (product, lockedByName = "") => {
    if (!product?.id || typeof props.forceCheckinProduct !== "function") {
        return null;
    }

    const prompt = __(
        "COM_NXPEASYCART_FORCE_CHECKIN_PRODUCT",
        "This product is checked out by %s. Force check-in?",
        [lockedByName || __("COM_NXPEASYCART_ERROR_PRODUCT_CHECKED_OUT_GENERIC", "another user")]
    );

    if (typeof window !== "undefined" && !window.confirm(prompt)) {
        return null;
    }

    try {
        const unlocked = await props.forceCheckinProduct(product.id);

        if (unlocked) {
            lockedProductId.value = unlocked.id;
            editorState.product = JSON.parse(JSON.stringify(unlocked));
            isEditorOpen.value = true;
            return unlocked;
        }
    } catch (error) {
        props.state.error =
            error?.message ||
            __(
                "COM_NXPEASYCART_ERROR_PRODUCT_CHECKED_OUT_GENERIC",
                "Unable to check in this product."
            );
    }

    return null;
};

const closeEditor = async () => {
    await releaseLock();
    isEditorOpen.value = false;
    props.state.validationErrors = [];
};

const handleSubmit = async (payload) => {
    const status = normaliseStatus(
        payload.status ?? payload.active,
        payload.out_of_stock
    );

    const data = {
        ...payload,
        status,
        active: status,
        featured: payload.featured ? 1 : 0,
        product_type:
            (typeof payload.product_type === "string" &&
                payload.product_type.trim()) ||
            "physical",
    };

    if (editorState.mode === "edit" && editorState.product?.id) {
        emit("update", { id: editorState.product.id, data });
    } else {
        emit("create", data);
    }
};

const confirmDelete = async (product) => {
    const name = product?.title ?? "";
    const message = __(
        "COM_NXPEASYCART_PRODUCTS_DELETE_CONFIRM_NAME",
        'Delete "%s"?',
        [name || "#"]
    );

    if (window.confirm(message)) {
        emit("delete", [product.id]);
    }
};

const toggleActive = (product) => {
    if (!product?.id || props.state.saving) {
        return;
    }

    const nextStatus = cycleStatus(
        product.status ?? product.active,
        product.out_of_stock
    );

    const normalizeString = (value) => {
        if (value === null || value === undefined) {
            return "";
        }

        return String(value);
    };

    const normalizeNumber = (value, fallback = 0) => {
        const number = Number(value);

        return Number.isFinite(number) ? number : fallback;
    };

    const normalisedVariants = Array.isArray(product.variants)
        ? product.variants
              .map((variant) => {
                  if (!variant) {
                      return null;
                  }

                  const sku = normalizeString(variant.sku).trim();

                  if (!sku) {
                      return null;
                  }

                  let priceCents = normalizeNumber(
                      variant.price_cents,
                      Number.NaN
                  );

                  if (!Number.isFinite(priceCents)) {
                      const price = normalizeString(variant.price);
                      const parsed = price ? Number.parseFloat(price) : NaN;
                      priceCents = Number.isFinite(parsed)
                          ? Math.round(parsed * 100)
                          : NaN;
                  }

                  if (!Number.isFinite(priceCents)) {
                      return null;
                  }

                  const currency = normalizeString(
                      variant.currency ?? baseCurrency.value
                  )
                      .trim()
                      .toUpperCase();

                  if (!currency) {
                      return null;
                  }

                  const options = variant.options ?? null;
                  const stock = Math.max(
                      0,
                      Math.round(normalizeNumber(variant.stock, 0))
                  );

                  // Normalise variant images (array of strings or null)
                  let variantImages = null;
                  if (Array.isArray(variant.images) && variant.images.length > 0) {
                      variantImages = variant.images
                          .map((img) => normalizeString(img).trim())
                          .filter((img) => img !== "");
                      if (variantImages.length === 0) {
                          variantImages = null;
                      }
                  }

                  return {
                      id: normalizeNumber(variant.id, 0),
                      sku,
                      price_cents: Math.round(priceCents),
                      currency,
                      stock,
                      options:
                          options !== null && options !== undefined
                              ? options
                              : null,
                      weight:
                          variant.weight !== null &&
                          variant.weight !== undefined &&
                          variant.weight !== ""
                              ? String(variant.weight)
                              : null,
                      active:
                          variant.active === undefined
                              ? true
                              : Boolean(variant.active),
                      is_digital:
                          variant.is_digital === undefined
                              ? false
                              : Boolean(variant.is_digital),
                      images: variantImages,
                  };
              })
              .filter(Boolean)
        : [];

    if (!normalisedVariants.length) {
        props.state.error = __(
            "COM_NXPEASYCART_ERROR_PRODUCT_VARIANT_REQUIRED",
            "At least one variant with a price is required."
        );

        return;
    }

    const normalisedCategories = Array.isArray(product.categories)
        ? product.categories
              .map((category) => {
                  if (category === null || category === undefined) {
                      return null;
                  }

                  if (typeof category === "number") {
                      return category > 0
                          ? {
                                id: category,
                                title: "",
                                slug: "",
                            }
                          : null;
                  }

                  if (
                      typeof category === "string" &&
                      category.trim() !== "" &&
                      Number.isFinite(Number(category))
                  ) {
                      const numericId = Number(category);

                      return numericId > 0
                          ? {
                                id: numericId,
                                title: "",
                                slug: "",
                            }
                          : null;
                  }

                  if (typeof category === "object") {
                      if (category.id) {
                          const categoryId = normalizeNumber(category.id, 0);

                          return categoryId > 0
                              ? {
                                    id: categoryId,
                                    title: "",
                                    slug: "",
                                }
                              : null;
                      }

                      const categoryId = normalizeNumber(category.id, 0);

                      if (categoryId <= 0 && !category.title) {
                          return null;
                      }

                      return {
                          id: 0,
                          title: normalizeString(category.title),
                          slug: normalizeString(category.slug),
                      };
                  }

                  const title = normalizeString(category).trim();

                  if (!title) {
                      return null;
                  }

                  return {
                      id: 0,
                      title,
                      slug: "",
                  };
              })
              .filter(Boolean)
        : [];

    const normalisedImages = Array.isArray(product.images)
        ? product.images
              .map((image) => normalizeString(image).trim())
              .map((image) => {
                  let value = image;

                  if (!value) {
                      return "";
                  }

                  const metadataIndex = value.indexOf("#joomlaImage://");
                  if (metadataIndex !== -1) {
                      value = value.substring(0, metadataIndex);
                  }

                  const adapterMatch = value.match(/^(?:local-[a-z0-9_-]+|images|videos|audios|documents):\/\/?(.*)$/i);
                  if (adapterMatch && adapterMatch[1]) {
                      value = adapterMatch[1];
                  }

                  if (typeof window !== "undefined") {
                      try {
                          const systemPaths = window.Joomla?.getOptions?.("system.paths", {}) ?? {};
                          const rootFull = systemPaths.rootFull || window.location.origin;
                          const rootRel = systemPaths.root || "/";

                          if (value.startsWith(rootFull)) {
                              value = value.substring(rootFull.length);
                          }

                          if (value.startsWith(rootRel)) {
                              value = value.substring(rootRel.length);
                          }
                      } catch (error) {
                          // ignore
                      }
                  }

                  return value.trim();
              })
              .filter((image) => image !== "")
        : [];

    props.state.error = "";

    emit("update", {
        id: product.id,
        data: {
            title: normalizeString(product.title),
            slug: normalizeString(product.slug),
            short_desc: normalizeString(product.short_desc),
            long_desc: normalizeString(product.long_desc),
            status: nextStatus,
            active: nextStatus,
            featured: product.featured ? 1 : 0,
            images: normalisedImages,
            variants: normalisedVariants,
            categories: normalisedCategories,
            product_type:
                (typeof product.product_type === "string" &&
                    product.product_type.trim()) ||
                "physical",
        },
    });
};

const emitRefresh = () => {
    emit("refresh");
};

const emitSearch = () => {
    emit("search");
};

const emitPage = (page) => {
    emit("page", page);
};

const formatTimestamp = (timestamp) => {
    if (!timestamp) {
        return "";
    }

    try {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = now - date;
        const seconds = Math.floor(diff / 1000);
        const minutes = Math.floor(seconds / 60);
        const hours = Math.floor(minutes / 60);

        if (seconds < 60) {
            return __("COM_NXPEASYCART_TIME_SECONDS_AGO", "just now");
        } else if (minutes < 60) {
            return __(
                "COM_NXPEASYCART_TIME_MINUTES_AGO",
                "%s minutes ago",
                [minutes]
            );
        } else if (hours < 24) {
            return __(
                "COM_NXPEASYCART_TIME_HOURS_AGO",
                "%s hours ago",
                [hours]
            );
        } else {
            return date.toLocaleString();
        }
    } catch (error) {
        return timestamp;
    }
};

onBeforeUnmount(() => {
    releaseLock();
});

watch(
    () => props.state.saving,
    (saving, wasSaving) => {
        if (wasSaving && !saving && isEditorOpen.value) {
            if (!props.state.validationErrors.length && !props.state.error) {
                closeEditor();
            }
        }
    }
);
</script>
