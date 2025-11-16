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
                <a
                    v-if="item.images && item.images.length"
                    class="nxp-ec-landing__card-media"
                    :href="item.link"
                    :aria-label="`${labels.view_product}: ${item.title}`"
                >
                    <img :src="item.images[0]" :alt="item.title" loading="lazy" />
                </a>
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
                    <div class="nxp-ec-landing__card-actions">
                        <a class="nxp-ec-btn nxp-ec-btn--ghost" :href="item.link">
                            {{ labels.view_product }}
                        </a>
                        <button
                            v-if="canQuickAdd(item)"
                            type="button"
                            class="nxp-ec-btn nxp-ec-btn--icon"
                            :aria-label="`${labels.add_to_cart}: ${item.title}`"
                            :disabled="quickState[itemKey(item)]?.loading"
                            @click="quickAdd(item)"
                        >
                            <span aria-hidden="true">+</span>
                            <span class="nxp-ec-sr-only">{{ labels.add_to_cart }}</span>
                        </button>
                    </div>
                </div>
            </article>
        </div>
    </section>
</template>

<script setup>
import { reactive } from "vue";

const props = defineProps({
    sections: {
        type: Array,
        default: () => [],
    },
    labels: {
        type: Object,
        default: () => ({
            view_all: "View all",
            view_product: "View product",
            add_to_cart: "Add to cart",
        }),
    },
    searchAction: {
        type: String,
        default: "",
    },
    cart: {
        type: Object,
        default: () => ({}),
    },
});

const quickState = reactive({});

const itemKey = (item) =>
    item.id || item.slug || item.title || Math.random().toString(36);

const canQuickAdd = (item) => {
    if (!props.cart?.endpoints?.add) {
        return false;
    }

    if (!item || !item.primary_variant_id) {
        return false;
    }

    return true;
};

const ensureState = (key) => {
    if (!quickState[key]) {
        quickState[key] = { loading: false };
    }

    return quickState[key];
};

const quickAdd = async (item) => {
    const key = itemKey(item);
    const state = ensureState(key);

    if (!canQuickAdd(item)) {
        window.location.href = item.link || props.searchAction;
        return;
    }

    state.loading = true;

    try {
        const formData = new FormData();

        if (props.cart.token) {
            formData.append(props.cart.token, "1");
        }

        formData.append("product_id", String(item.id || ""));
        formData.append("variant_id", String(item.primary_variant_id));
        formData.append("qty", "1");

        let json = null;

        const response = await fetch(props.cart.endpoints.add, {
            method: "POST",
            body: formData,
            headers: {
                Accept: "application/json",
            },
        });

        try {
            json = await response.json();
        } catch (error) {
            // Non-JSON payloads fall through.
        }

        if (!response.ok || !json || json.success === false) {
            throw new Error(
                (json && json.message) || props.labels.add_to_cart
            );
        }

        const cart = json.data?.cart || null;

        if (cart) {
            window.dispatchEvent(
                new CustomEvent("nxp-cart:updated", { detail: cart })
            );
        }
    } catch (error) {
        // Surface basic error for debugging; keep it non-intrusive.
        console.error(error);
    } finally {
        state.loading = false;
    }
};
</script>
