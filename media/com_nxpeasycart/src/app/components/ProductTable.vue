<template>
    <table class="nxp-admin-table" aria-describedby="nxp-products-caption">
        <caption id="nxp-products-caption" class="visually-hidden">
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
                <th scope="col">{{ __("JGRID_HEADING_ID", "ID") }}</th>
                <th scope="col">
                    {{ __("COM_NXPEASYCART_PRODUCTS_TABLE_TITLE", "Product") }}
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
                    {{
                        __(
                            "COM_NXPEASYCART_PRODUCTS_TABLE_CATEGORIES",
                            "Categories"
                        )
                    }}
                </th>
                <th scope="col">{{ __("JSTATUS", "Status") }}</th>
                <th scope="col">
                    {{
                        __("COM_NXPEASYCART_PRODUCTS_TABLE_UPDATED", "Updated")
                    }}
                </th>
                <th scope="col" class="nxp-admin-table__actions">
                    {{ __("JGLOBAL_ACTIONS", "Actions") }}
                </th>
            </tr>
        </thead>
        <tbody>
            <tr v-if="!items.length">
                <td colspan="8" class="nxp-admin-table__empty">
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
                <td>{{ item.id }}</td>
                <td>
                    <div class="nxp-products-table__title">
                        {{ item.title }}
                    </div>
                    <div class="nxp-products-table__slug">{{ item.slug }}</div>
                </td>
                <td>
                    <span v-if="variantPrice(item)">
                        {{ variantPrice(item) }}
                    </span>
                    <span v-else>
                        {{ __("COM_NXPEASYCART_PRODUCTS_PRICE_UNKNOWN", "—") }}
                    </span>
                </td>
                <td>
                    {{ variantCountLabel(item) }}
                </td>
                <td>
                    <span v-if="item.categories?.length">
                        {{ categorySummary(item.categories) }}
                    </span>
                    <span v-else>
                        {{
                            __(
                                "COM_NXPEASYCART_PRODUCTS_NO_CATEGORIES",
                                "Uncategorised"
                            )
                        }}
                    </span>
                </td>
                <td>
                    <span
                        :class="[
                            'nxp-status',
                            item.active
                                ? 'nxp-status--active'
                                : 'nxp-status--inactive',
                        ]"
                    >
                        {{
                            item.active
                                ? __(
                                      "COM_NXPEASYCART_STATUS_ACTIVE",
                                      "Active",
                                      [],
                                      "statusActive"
                                  )
                                : __(
                                      "COM_NXPEASYCART_STATUS_INACTIVE",
                                      "Inactive",
                                      [],
                                      "statusInactive"
                                  )
                        }}
                    </span>
                </td>
                <td>{{ item.modified || item.created }}</td>
                <td class="nxp-admin-table__actions">
                    <button
                        class="nxp-btn nxp-btn--link"
                        type="button"
                        @click="$emit('edit', item)"
                    >
                        {{ __("JGLOBAL_EDIT", "Edit") }}
                    </button>
                    <button
                        class="nxp-btn nxp-btn--link nxp-btn--danger"
                        type="button"
                        @click="$emit('delete', item)"
                    >
                        {{ __("JTRASH", "Delete") }}
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
});

defineEmits(["edit", "delete"]);

const __ = props.translate;

const baseCurrency = computed(() =>
    (props.baseCurrency || "USD").toUpperCase()
);

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
