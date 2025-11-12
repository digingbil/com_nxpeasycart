<template>
    <header class="nxp-ec-landing__hero">
        <div class="nxp-ec-landing__hero-copy">
            <p v-if="hero.eyebrow" class="nxp-ec-landing__eyebrow">
                {{ hero.eyebrow }}
            </p>
            <h1 class="nxp-ec-landing__title">{{ hero.title }}</h1>
            <p
                v-if="hero.subtitle"
                class="nxp-ec-landing__subtitle"
            >
                {{ hero.subtitle }}
            </p>
            <div class="nxp-ec-landing__actions">
                <a class="nxp-ec-btn nxp-ec-btn--primary" :href="cta.link">
                    {{ cta.label }}
                </a>
            </div>
        </div>
        <form class="nxp-ec-landing__search" @submit.prevent="emitSubmit">
            <label class="sr-only" for="nxp-ec-landing-search-input">
                {{ labels.search_label }}
            </label>
            <input
                id="nxp-ec-landing-search-input"
                type="search"
                :value="term"
                @input="emitUpdate($event.target.value)"
                :placeholder="searchPlaceholder"
            />
            <button type="submit" class="nxp-ec-btn nxp-ec-btn--ghost">
                {{ labels.search_button }}
            </button>
        </form>
    </header>
</template>

<script setup>
const props = defineProps({
    hero: {
        type: Object,
        default: () => ({}),
    },
    cta: {
        type: Object,
        default: () => ({
            label: "Shop Now",
            link: "#",
        }),
    },
    labels: {
        type: Object,
        default: () => ({
            search_label: "Search the catalogue",
            search_button: "Search",
        }),
    },
    term: {
        type: String,
        default: "",
    },
    searchPlaceholder: {
        type: String,
        default: "",
    },
});

const emit = defineEmits(["update:term", "submit"]);

const emitUpdate = (value) => {
    emit("update:term", value);
};

const emitSubmit = () => {
    emit("submit");
};
</script>
