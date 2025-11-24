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
                <div
                    class="nxp-ec-landing__card-media"
                    @mouseenter="startCycle(item)"
                    @mouseleave="stopCycle(item)"
                    @focusin="startCycle(item)"
                    @focusout="stopCycle(item)"
                >
                    <a
                        v-if="item.images && item.images.length"
                        class="nxp-ec-landing__card-link"
                        :href="item.link"
                        :aria-label="`${labels.view_product}: ${item.title}`"
                    >
                        <transition name="nxp-ec-fade" mode="out-in">
                            <img
                                :key="activeImage(item)"
                                :src="activeImage(item)"
                                :alt="item.title"
                                loading="lazy"
                            />
                        </transition>
                    </a>
                    <button
                        type="button"
                        class="nxp-ec-quick-add"
                        :aria-label="`${labels.add_to_cart}: ${item.title}`"
                        :class="{
                            'is-disabled': item.out_of_stock || quickState[itemKey(item)]?.loading
                        }"
                        :disabled="item.out_of_stock || quickState[itemKey(item)]?.loading"
                        @click="quickAdd(item)"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="24"
                            height="24"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="nxp-ec-quick-add__icon"
                            aria-hidden="true"
                        >
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path
                                d="M4 19a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"
                            />
                            <path d="M12.5 17h-6.5v-14h-2" />
                            <path
                                d="M6 5l14 1l-.86 6.017m-2.64 .983h-10.5"
                            />
                            <path d="M16 19h6" />
                            <path d="M19 16v6" />
                        </svg>
                        <span class="nxp-ec-sr-only">{{ labels.add_to_cart }}</span>
                    </button>
                </div>
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
                    </div>
                    <p
                        v-if="quickState[itemKey(item)]?.message"
                        class="nxp-ec-product-card__hint nxp-ec-product-card__hint--alert"
                    >
                        {{ quickState[itemKey(item)]?.message }}
                    </p>
                    <p
                        v-else-if="item.out_of_stock"
                        class="nxp-ec-product-card__hint nxp-ec-product-card__hint--alert"
                    >
                        {{ labels.out_of_stock }}
                    </p>
                </div>
            </article>
        </div>
    </section>
</template>

<script setup>
import { reactive } from "vue";
import { useImageRotator } from "../utils/useImageRotator.js";

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
            added: "Added to cart",
            error_generic: "We couldn't add this item. Please try again.",
            select_variant: "Choose a variant to continue",
            out_of_stock: "This product is currently out of stock.",
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

const itemKey = (item) =>
    item.id || item.slug || item.title || Math.random().toString(36);

const quickState = reactive({});
const { activeImage, startCycle, stopCycle } = useImageRotator(itemKey);

const hasSingleVariant = (item) => {
    const count = Number.parseInt(item?.variant_count, 10);

    if (Number.isFinite(count)) {
        return count === 1;
    }

    if (item && item.primary_variant_id) {
        return true;
    }

    return false;
};

const shouldQuickAdd = (item) =>
    !!(
        props.cart?.endpoints?.add &&
        item?.primary_variant_id &&
        hasSingleVariant(item) &&
        !item?.out_of_stock
    );

const ensureState = (key) => {
    if (!quickState[key]) {
        quickState[key] = { loading: false, message: "" };
    }

    return quickState[key];
};

const quickAdd = async (item) => {
    const key = itemKey(item);
    const state = ensureState(key);
    state.message = "";

    if (item?.out_of_stock) {
        state.message = props.labels?.out_of_stock || "";
        return;
    }

    if (!props.cart?.endpoints?.add) {
        window.location.href = item.link || props.searchAction;
        return;
    }

    if (!shouldQuickAdd(item)) {
        state.message = props.labels?.select_variant || "";
        window.location.href = item.link || props.searchAction;
        return;
    }

    try {
        state.loading = true;
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

        state.message =
            json.message ||
            props.labels?.added ||
            props.labels?.add_to_cart;
    } catch (error) {
        // Surface basic error for debugging; keep it non-intrusive.
        console.error(error);
        state.message =
            (error && error.message) ||
            props.labels?.error_generic ||
            props.labels?.add_to_cart;
    } finally {
        state.loading = false;
    }
};
</script>
