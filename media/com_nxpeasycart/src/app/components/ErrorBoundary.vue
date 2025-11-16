<template>
    <div>
        <div v-if="error" class="nxp-ec-admin-error">
            <h2 class="nxp-ec-admin-error__title">
                {{ fallbackTitle }}
            </h2>
            <p class="nxp-ec-admin-error__body">
                {{ fallbackMessage }}
            </p>
            <button class="nxp-ec-btn nxp-ec-btn--primary" type="button" @click="reload">
                {{ reloadLabel }}
            </button>
        </div>
        <slot v-else />
    </div>
</template>

<script>
export default {
    name: "ErrorBoundary",
    data() {
        return {
            error: null,
        };
    },
    computed: {
        fallbackTitle() {
            return (
                this.$t?.("COM_NXPEASYCART_ERROR_TITLE") ||
                "Something went wrong"
            );
        },
        fallbackMessage() {
            return (
                this.$t?.("COM_NXPEASYCART_ERROR_GENERIC") ||
                "Please reload the page and try again."
            );
        },
        reloadLabel() {
            return this.$t?.("COM_NXPEASYCART_RELOAD") || "Reload";
        },
    },
    errorCaptured(err) {
        this.error = err;
        return false;
    },
    methods: {
        reload() {
            if (typeof window !== "undefined") {
                window.location.reload();
            }
        },
    },
};
</script>
