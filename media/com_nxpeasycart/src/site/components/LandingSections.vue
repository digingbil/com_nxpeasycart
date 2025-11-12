<template>
    <section
        v-for="section in sections"
        :key="section.key"
        class="nxp-ec-landing__section"
    >
        <header class="nxp-ec-landing__section-header">
            <h2 class="nxp-ec-landing__section-title">
                {{ section.title }}
            </h2>
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
                <figure
                    v-if="item.images && item.images.length"
                    class="nxp-ec-landing__card-media"
                >
                    <img :src="item.images[0]" :alt="item.title" loading="lazy" />
                </figure>
                <div class="nxp-ec-landing__card-body">
                    <h3 class="nxp-ec-landing__card-title">
                        <a :href="item.link">{{ item.title }}</a>
                    </h3>
                    <p
                        v-if="item.short_desc"
                        class="nxp-ec-landing__card-intro"
                    >
                        {{ item.short_desc }}
                    </p>
                    <p
                        v-if="item.price_label"
                        class="nxp-ec-landing__card-price"
                    >
                        {{ item.price_label }}
                    </p>
                    <a class="nxp-ec-btn nxp-ec-btn--ghost" :href="item.link">
                        {{ labels.view_product }}
                    </a>
                </div>
            </article>
        </div>
    </section>
</template>

<script setup>
defineProps({
    sections: {
        type: Array,
        default: () => [],
    },
    labels: {
        type: Object,
        default: () => ({
            view_all: "View all",
            view_product: "View product",
        }),
    },
    searchAction: {
        type: String,
        default: "",
    },
});
</script>
