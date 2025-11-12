<template>
    <aside
        v-if="trust.text"
        class="nxp-ec-landing__trust"
    >
        <p class="nxp-ec-landing__trust-text">{{ trust.text }}</p>
    </aside>
</template>

<script setup>
defineProps({
    trust: {
        type: Object,
        default: () => ({
            text: "",
        }),
    },
});
</script>
