<template>
    <section
        v-if="categories.length"
        class="nxp-ec-landing__categories"
        :aria-label="ariaLabel"
    >
        <a
            v-for="category in categories"
            :key="category.id || category.slug || category.title"
            class="nxp-ec-landing__category"
            :href="category.link"
        >
            <span class="nxp-ec-landing__category-title">
                {{ category.title }}
            </span>
        </a>
    </section>
</template>

<script setup>
defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
    ariaLabel: {
        type: String,
        default: "",
    },
});
</script>
