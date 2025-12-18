<template>
    <section
        v-if="categories.length"
        :class="sectionClasses"
        :aria-label="ariaLabel"
    >
        <a
            v-for="(category, index) in categories"
            :key="category.id || category.slug || category.title"
            :class="getCategoryClasses(index)"
            :href="category.link"
        >
            <span class="nxp-ec-landing__category-title">
                {{ category.title }}
            </span>
        </a>
        <button
            v-if="isCollapsible"
            type="button"
            :class="toggleButtonClasses"
            :aria-expanded="isExpanded ? 'true' : 'false'"
            @click="toggleExpanded"
        >
            <span class="nxp-ec-landing__categories-toggle-text">
                {{ toggleLabel }}
            </span>
            <span class="nxp-ec-landing__categories-toggle-icon" aria-hidden="true">
                {{ isExpanded ? '▲' : '▼' }}
            </span>
        </button>
    </section>
</template>

<script setup>
import { ref, computed } from "vue";

const props = defineProps({
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
    labels: {
        type: Object,
        default: () => ({
            categories_show_more: "Show all %s categories",
            categories_show_less: "Show fewer categories",
        }),
    },
    theme: {
        type: Object,
        default: () => ({}),
    },
    ariaLabel: {
        type: String,
        default: "",
    },
});

const isExpanded = ref(false);

const visibleInitial = computed(
    () => props.categorySettings?.visible_initial ?? 8
);
const totalCount = computed(
    () => props.categorySettings?.total_count ?? props.categories.length
);
const isCollapsible = computed(
    () => props.categorySettings?.is_collapsible ?? false
);

// Get template-specific classes
const categoryTileClass = computed(
    () => props.theme?.category_tile_class || ""
);

const sectionClasses = computed(() => [
    "nxp-ec-landing__categories",
    {
        "nxp-ec-landing__categories--collapsible": isCollapsible.value,
        "nxp-ec-landing__categories--expanded": isExpanded.value,
    },
]);

const getCategoryClasses = (index) => [
    "nxp-ec-landing__category",
    categoryTileClass.value,
    {
        "nxp-ec-landing__category--hidden":
            isCollapsible.value && !isExpanded.value && index >= visibleInitial.value,
    },
];

const toggleButtonClasses = computed(() => [
    "nxp-ec-landing__categories-toggle",
]);

const showMoreLabel = computed(() => {
    const template = props.labels?.categories_show_more || "Show all %s categories";
    return template.replace("%s", String(totalCount.value));
});

const showLessLabel = computed(
    () => props.labels?.categories_show_less || "Show fewer categories"
);

const toggleLabel = computed(() =>
    isExpanded.value ? showLessLabel.value : showMoreLabel.value
);

const toggleExpanded = () => {
    isExpanded.value = !isExpanded.value;
};
</script>
