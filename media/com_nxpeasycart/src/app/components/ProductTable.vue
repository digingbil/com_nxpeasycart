<template>
    <table class="nxp-ec-admin-table" aria-describedby="nxp-ec-products-caption">
        <caption id="nxp-ec-products-caption" class="visually-hidden">
            {{
                __(
                    "COM_NXPEASYCART_MENU_PRODUCTS",
                    "Products",
                    [],
                    "productsPanelTitle"
                )
            }}
        </caption>
        <thead>
            <tr>
                <th scope="col" class="nxp-ec-admin-table__sortable" @click="handleSort('id')">
                    {{ __("JGRID_HEADING_ID", "ID") }}
                    <i :class="sortIcon('id')" class="nxp-ec-admin-table__sort-icon" aria-hidden="true"></i>
                </th>
                <th scope="col" class="nxp-ec-admin-table__sortable" @click="handleSort('title')">
                    {{ __("COM_NXPEASYCART_PRODUCTS_TABLE_TITLE", "Product") }}
                    <i :class="sortIcon('title')" class="nxp-ec-admin-table__sort-icon" aria-hidden="true"></i>
                </th>
                <th scope="col">
                    {{ __("COM_NXPEASYCART_PRODUCTS_TABLE_PRICE", "Price") }}
                </th>
                <th scope="col">
                    {{
                        __(
                            "COM_NXPEASYCART_PRODUCTS_TABLE_VARIANTS",
                            "Variants"
                        )
                    }}
                </th>
                <th scope="col">
                    {{ __("COM_NXPEASYCART_PRODUCTS_TABLE_STOCK", "Stock") }}
                </th>
                <th scope="col">
                    {{
                        __(
                            "COM_NXPEASYCART_PRODUCTS_TABLE_CATEGORIES",
                            "Categories"
                        )
                    }}
                </th>
                <th scope="col" class="nxp-ec-admin-table__sortable" @click="handleSort('status')">
                    {{ __("JSTATUS", "Status") }}
                    <i :class="sortIcon('status')" class="nxp-ec-admin-table__sort-icon" aria-hidden="true"></i>
                </th>
                <th scope="col" class="nxp-ec-admin-table__sortable" @click="handleSort('modified')">
                    {{
                        __("COM_NXPEASYCART_PRODUCTS_TABLE_UPDATED", "Updated")
                    }}
                    <i :class="sortIcon('modified')" class="nxp-ec-admin-table__sort-icon" aria-hidden="true"></i>
                </th>
                <th scope="col" class="nxp-ec-admin-table__actions">
                    {{ __("JGLOBAL_ACTIONS", "Actions") }}
                </th>
            </tr>
        </thead>
        <tbody>
            <tr v-if="!items.length">
                <td colspan="8" class="nxp-ec-admin-table__empty">
                    {{
                        __(
                            "COM_NXPEASYCART_PRODUCTS_EMPTY",
                            "No products found.",
                            [],
                            "productsEmpty"
                        )
                    }}
                </td>
            </tr>
            <tr v-for="item in items" :key="item.id">
                <td :data-label="__('JGRID_HEADING_ID', 'ID')" class="nxp-ec-admin-table__id">{{ item.id }}</td>
                <td class="nxp-ec-admin-table__primary">
                    <div class="nxp-ec-products-table__title">
                        {{ item.title }}
                    </div>
                    <div class="nxp-ec-products-table__slug">{{ item.slug }}</div>
                    <div
                        v-if="item.checked_out"
                        class="nxp-ec-products-table__badge"
                    >
                        <span class="nxp-ec-status nxp-ec-status--muted">
                            <i class="fa-solid fa-lock" aria-hidden="true"></i>
                            {{ lockLabel(item) }}
                        </span>
                    </div>
                    <div v-if="item.featured" class="nxp-ec-products-table__badge">
                        <span class="nxp-ec-status nxp-ec-status--featured">
                            <i class="fa-solid fa-sun" aria-hidden="true"></i>
                            {{
                                __(
                                    "COM_NXPEASYCART_PRODUCTS_BADGE_FEATURED",
                                    "Featured"
                                )
                            }}
                        </span>
                    </div>
                </td>
                <td :data-label="__('COM_NXPEASYCART_PRODUCTS_TABLE_PRICE', 'Price')">
                    <span v-if="variantPrice(item)">
                        {{ variantPrice(item) }}
                    </span>
                    <span v-else>
                        {{ __("COM_NXPEASYCART_PRODUCTS_PRICE_UNKNOWN", "—") }}
                    </span>
                </td>
                <td :data-label="__('COM_NXPEASYCART_PRODUCTS_TABLE_VARIANTS', 'Variants')">
                    {{ variantCountLabel(item) }}
                </td>
                <td :data-label="__('COM_NXPEASYCART_PRODUCTS_TABLE_STOCK', 'Stock')">
                    <span
                        :class="[
                            'nxp-ec-status',
                            item.summary?.variants?.stock_zero
                                ? 'nxp-ec-status--danger'
                                : item.summary?.variants?.stock_low
                                  ? 'nxp-ec-status--warning'
                                  : 'nxp-ec-status--muted',
                        ]"
                    >
                        <template
                            v-if="item.summary?.variants?.stock_zero || item.summary?.variants?.stock_total === 0"
                        >
                            {{
                                __(
                                    "COM_NXPEASYCART_PRODUCTS_STOCK_UNAVAILABLE",
                                    "Unavailable"
                                )
                            }}
                        </template>
                        <template v-else>
                            {{
                                __(
                                    "COM_NXPEASYCART_PRODUCTS_STOCK_COUNT",
                                    "%s in stock",
                                    [String(item.summary?.variants?.stock_total ?? 0)]
                                )
                            }}
                        </template>
                    </span>
                </td>
                <td :data-label="__('COM_NXPEASYCART_PRODUCTS_TABLE_CATEGORIES', 'Categories')">
                    <span v-if="item.categories?.length">
                        {{ categorySummary(item.categories) }}
                    </span>
                    <span
                        v-else
                        class="nxp-ec-status nxp-ec-status--warning"
                        :title="__(
                            'COM_NXPEASYCART_PRODUCTS_NO_CATEGORIES_WARNING',
                            'This product has no categories and may not appear in category listings'
                        )"
                    >
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        {{
                            __(
                                "COM_NXPEASYCART_PRODUCTS_NO_CATEGORIES",
                                "Uncategorised"
                            )
                        }}
                    </span>
                </td>
                <td :data-label="__('JSTATUS', 'Status')">
                    <button
                        type="button"
                        :class="[
                            'nxp-ec-status',
                            'nxp-ec-status-button',
                            statusMeta(item).className,
                        ]"
                        :disabled="saving || isLocked(item)"
                        :aria-pressed="statusMeta(item).isActive ? 'true' : 'false'"
                        :title="statusMeta(item).title"
                        :aria-label="statusMeta(item).title"
                        @click="$emit('toggle-active', item)"
                    >
                        <i
                            :class="statusMeta(item).icon"
                            aria-hidden="true"
                        ></i>
                        {{ statusMeta(item).label }}
                        <span class="nxp-ec-sr-only">
                            {{
                                __(
                                    "COM_NXPEASYCART_PRODUCTS_STATUS_TOGGLE",
                                    "Toggle status",
                                    [],
                                    "productsStatusToggle"
                                )
                            }}
                        </span>
                    </button>
                </td>
                <td :data-label="__('COM_NXPEASYCART_PRODUCTS_TABLE_UPDATED', 'Updated')">{{ item.modified || item.created }}</td>
                <td class="nxp-ec-admin-table__actions">
                    <button
                        class="nxp-ec-btn nxp-ec-btn--link nxp-ec-btn--icon"
                        type="button"
                        @click="$emit('edit', item)"
                        :title="__('JGLOBAL_EDIT', 'Edit')"
                        :disabled="saving"
                        :aria-label="__('JGLOBAL_EDIT', 'Edit')"
                    >
                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                        <span class="nxp-ec-sr-only">
                            {{ __("JGLOBAL_EDIT", "Edit") }}
                        </span>
                    </button>
                    <button
                        class="nxp-ec-btn nxp-ec-btn--link nxp-ec-btn--danger nxp-ec-btn--icon"
                        type="button"
                        @click="$emit('delete', item)"
                        :title="__('JTRASH', 'Delete')"
                        :disabled="saving || isLocked(item)"
                        :aria-label="__('JTRASH', 'Delete')"
                    >
                        <i class="fa-solid fa-trash" aria-hidden="true"></i>
                        <span class="nxp-ec-sr-only">
                            {{ __("JTRASH", "Delete") }}
                        </span>
                    </button>
                </td>
            </tr>
        </tbody>
    </table>
</template>

<script setup>
import { computed } from "vue";

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    translate: {
        type: Function,
        required: true,
    },
    baseCurrency: {
        type: String,
        default: "USD",
    },
    saving: {
        type: Boolean,
        default: false,
    },
    currentUserId: {
        type: Number,
        default: 0,
    },
    sortColumn: {
        type: String,
        default: "",
    },
    sortDirection: {
        type: String,
        default: "DESC",
    },
});

const emit = defineEmits(["edit", "delete", "toggle-active", "sort"]);

const handleSort = (column) => {
    emit("sort", column);
};

const isSorted = (column) => props.sortColumn === column;
const sortIcon = (column) => {
    if (props.sortColumn !== column) {
        return "fa-solid fa-sort";
    }
    return props.sortDirection === "ASC" ? "fa-solid fa-sort-up" : "fa-solid fa-sort-down";
};

const __ = props.translate;

const baseCurrency = computed(() =>
    (props.baseCurrency || "USD").toUpperCase()
);

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

const statusMeta = (item) => {
    const status = normaliseStatus(item?.status ?? item?.active, item?.out_of_stock);
    const isActive = status === STATUS_ACTIVE;
    const isOutOfStock = status === STATUS_OUT_OF_STOCK;

    return {
        status,
        isActive,
        isOutOfStock,
        label: isActive
            ? __("COM_NXPEASYCART_STATUS_ACTIVE", "Active", [], "statusActive")
            : isOutOfStock
              ? __("COM_NXPEASYCART_STATUS_DEPLETED", "Out of stock")
              : __(
                    "COM_NXPEASYCART_STATUS_INACTIVE",
                    "Inactive",
                    [],
                    "statusInactive"
                ),
        className: isActive
            ? "nxp-ec-status--active"
            : isOutOfStock
              ? "nxp-ec-status--muted"
              : "nxp-ec-status--inactive",
        icon: isActive
            ? "fa-solid fa-circle-check"
            : isOutOfStock
              ? "fa-solid fa-triangle-exclamation"
              : "fa-regular fa-circle",
        title: __(
            "COM_NXPEASYCART_PRODUCTS_STATUS_TOGGLE",
            "Toggle status",
            [],
            "productsStatusToggle"
        ),
    };
};

const isLocked = (item) =>
    item?.checked_out &&
    Number(item.checked_out) !== 0 &&
    Number(item.checked_out) !== Number(props.currentUserId || 0);

const lockLabel = (item) => {
    const name = item?.checked_out_user?.name ?? "";
    const displayName =
        item?.checked_out === Number(props.currentUserId || 0)
            ? __("JGLOBAL_YOU", "You")
            : name || __("COM_NXPEASYCART_ERROR_PRODUCT_CHECKED_OUT_GENERIC", "Another user");

    return __(
        "COM_NXPEASYCART_CHECKED_OUT_BY",
        "Checked out by %s",
        [displayName],
        "productLockedBy"
    );
};

const variantPrice = (product) => {
    const summary = product?.summary?.variants ?? {};

    if (summary.multiple_currencies) {
        return __("COM_NXPEASYCART_PRODUCTS_PRICE_MIXED", "Mixed currencies");
    }

    const minCents = Number.isFinite(summary.price_min_cents)
        ? summary.price_min_cents
        : null;
    const maxCents = Number.isFinite(summary.price_max_cents)
        ? summary.price_max_cents
        : null;

    if (minCents === null) {
        return "";
    }

    const currency =
        summary.currency ||
        product?.variants?.[0]?.currency ||
        baseCurrency.value;

    if (maxCents === null || maxCents === minCents) {
        return formatMoney(minCents, currency);
    }

    return `${formatMoney(minCents, currency)} – ${formatMoney(maxCents, currency)}`;
};

const variantCountLabel = (product) => {
    const summary = product?.summary?.variants ?? {};
    const count = Number.isFinite(summary.count)
        ? summary.count
        : Array.isArray(product?.variants)
          ? product.variants.length
          : 0;

    if (count === 1) {
        return __("COM_NXPEASYCART_PRODUCTS_VARIANT_COUNT_ONE", "1 variant");
    }

    return __("COM_NXPEASYCART_PRODUCTS_VARIANT_COUNT", "%s variants", [
        String(count),
    ]);
};

const categorySummary = (categories) => {
    if (!Array.isArray(categories) || !categories.length) {
        return "";
    }

    const titles = categories
        .map((category) => category?.title || category?.slug)
        .filter((value) => Boolean(value));

    if (!titles.length) {
        return "";
    }

    if (titles.length <= 3) {
        return titles.join(", ");
    }

    const display = titles.slice(0, 3).join(", ");
    const extra = titles.length - 3;

    return `${display} ${__("COM_NXPEASYCART_PRODUCTS_MORE_CATEGORIES", "+%s more", [String(extra)])}`;
};

const formatMoney = (cents, currency) => {
    const amount = (cents / 100).toFixed(2);

    return `${amount} ${currency}`;
};
</script>
