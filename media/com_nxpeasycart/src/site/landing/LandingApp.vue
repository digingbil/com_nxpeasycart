<template>
    <div class="nxp-ec-landing__inner" v-cloak>
        <LandingHero
            :hero="hero"
            :cta="cta"
            :labels="labels"
            :term="term"
            :search-placeholder="searchPlaceholder"
            @update:term="updateTerm"
            @submit="submitSearch"
        />

        <LandingCategories
            :categories="categories"
            :category-settings="categorySettings"
            :labels="labels"
            :theme="theme"
            :aria-label="labels.categories_aria"
        />

        <LandingSections
            :sections="visibleSections"
            :labels="labels"
            :search-action="searchAction"
            :cart="cart"
        />

        <LandingTrust :trust="trustBlock" />
    </div>
</template>

<script setup>
import { computed, ref } from "vue";
import LandingHero from "../components/LandingHero.vue";
import LandingCategories from "../components/LandingCategories.vue";
import LandingSections from "../components/LandingSections.vue";
import LandingTrust from "../components/LandingTrust.vue";
import useCatalogSections from "../composables/useCatalogSections.js";

const DEFAULT_SEARCH_ACTION =
    "index.php?option=com_nxpeasycart&view=category";

const props = defineProps({
    hero: {
        type: Object,
        default: () => ({}),
    },
    cta: {
        type: Object,
        default: () => ({
            label: "Shop Best Sellers",
            link: "index.php?option=com_nxpeasycart&view=category",
        }),
    },
    categories: {
        type: Array,
        default: () => [],
    },
    categorySettings: {
        type: Object,
        default: () => ({
            visible_initial: 8,
            total_count: 0,
            is_collapsible: false,
        }),
    },
    sections: {
        type: Array,
        default: () => [],
    },
    labels: {
        type: Object,
        default: () => ({}),
    },
    theme: {
        type: Object,
        default: () => ({}),
    },
    cart: {
        type: Object,
        default: () => ({}),
    },
    trust: {
        type: Object,
        default: () => ({ text: "" }),
    },
    searchAction: {
        type: String,
        default: "index.php?option=com_nxpeasycart&view=category",
    },
    searchPlaceholder: {
        type: String,
        default: "",
    },
});

const term = ref("");
const hero = computed(() => ({
    eyebrow: props.hero?.eyebrow || "",
    title: props.hero?.title || "Shop",
    subtitle: props.hero?.subtitle || "",
}));
const cta = computed(() => ({
    enabled:
        typeof props.cta?.enabled === "boolean"
            ? props.cta.enabled
            : true,
    label: props.cta?.label || "Shop Best Sellers",
    link: props.cta?.link || props.searchAction || DEFAULT_SEARCH_ACTION,
}));
const labels = computed(() => ({
    search_label: props.labels?.search_label || "Search the catalogue",
    search_button: props.labels?.search_button || "Search",
    view_all: props.labels?.view_all || "View all",
    view_product: props.labels?.view_product || "View product",
    add_to_cart: props.labels?.add_to_cart || "Add to cart",
    added: props.labels?.added || "Added to cart",
    view_cart: props.labels?.view_cart || "View cart",
    select_variant:
        props.labels?.select_variant || "Choose a variant to continue",
    out_of_stock:
        props.labels?.out_of_stock || "This product is currently out of stock.",
    categories_aria:
        props.labels?.categories_aria || "Browse categories",
    categories_show_more:
        props.labels?.categories_show_more || "Show all %s categories",
    categories_show_less:
        props.labels?.categories_show_less || "Show fewer categories",
}));
const categories = computed(() => props.categories ?? []);
const categorySettings = computed(() => ({
    visible_initial: props.categorySettings?.visible_initial ?? 8,
    total_count: props.categorySettings?.total_count ?? categories.value.length,
    is_collapsible: props.categorySettings?.is_collapsible ?? false,
}));
const theme = computed(() => props.theme ?? {});
const sectionsRef = computed(() => props.sections ?? []);
const visibleSections = useCatalogSections(sectionsRef);
const trustBlock = computed(() => {
    if (props.trust && typeof props.trust.text === "string") {
        return props.trust;
    }

    return { text: "" };
});

const searchAction = computed(
    () => props.searchAction || DEFAULT_SEARCH_ACTION
);

const searchPlaceholder = computed(
    () => props.searchPlaceholder || "Search for shoes, laptops, gifts…"
);

const cart = computed(() => props.cart ?? {});

const updateTerm = (value) => {
    term.value = value;
};

const navigateToSearch = (value) => {
    const action = searchAction.value;

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
            window.location.href = `${action}${separator}q=${encodeURIComponent(value)}`;
            return;
        }

        window.location.href = action;
    }
};

const submitSearch = () => {
    navigateToSearch(term.value.trim());
};

defineExpose({
    submitSearch,
});

// expose for template (Vue unwraps refs automatically)
</script>
