import { createApp } from "vue";
import LandingApp from "./LandingApp.vue";
import parsePayload from "../utils/parsePayload.js";

const DEFAULT_SEARCH_ACTION =
    "index.php?option=com_nxpeasycart&view=category";
const DEFAULT_PLACEHOLDER = "Search for shoes, laptops, gifts…";

export function mountLandingIsland(el) {
    const payload = parsePayload(el.dataset.nxpLanding, {});
    const hero = payload.hero || {};
    const search = payload.search || {};
    const labelsPayload = payload.labels || {};
    const trust = payload.trust || {};
    const sections = Array.isArray(payload.sections)
        ? payload.sections
        : [];
    const categories = Array.isArray(payload.categories)
        ? payload.categories
        : [];
    const categorySettings = payload.categorySettings || {
        visible_initial: 8,
        total_count: categories.length,
        is_collapsible: false,
    };
    const theme = payload.theme || {};

    const searchAction = search.action || DEFAULT_SEARCH_ACTION;
    const heroData = {
        eyebrow: hero.eyebrow || "",
        title: hero.title || "Shop",
        subtitle: hero.subtitle || "",
    };
    const cart = payload.cart || {};
    const cta = {
        enabled:
            typeof hero?.cta?.enabled === "boolean"
                ? hero.cta.enabled
                : Boolean(
                      typeof hero?.cta?.enabled !== "undefined"
                          ? hero?.cta?.enabled
                          : true
                  ),
        label: hero?.cta?.label || "Shop Best Sellers",
        link: hero?.cta?.link || searchAction,
    };

    const labels = {
        search_label:
            labelsPayload.search_label || "Search the catalogue",
        search_button: labelsPayload.search_button || "Search",
        view_all: labelsPayload.view_all || "View all",
        view_product: labelsPayload.view_product || "View product",
        add_to_cart: labelsPayload.add_to_cart || "Add to cart",
        added: labelsPayload.added || "Added to cart",
        out_of_stock:
            labelsPayload.out_of_stock ||
            "This product is currently out of stock.",
        categories_aria:
            labelsPayload.categories_aria || "Browse categories",
        categories_show_more:
            labelsPayload.categories_show_more || "Show all %s categories",
        categories_show_less:
            labelsPayload.categories_show_less || "Show fewer categories",
    };

    el.innerHTML = "";

    createApp(LandingApp, {
        hero: heroData,
        cta,
        categories,
        categorySettings,
        sections,
        labels,
        theme,
        trust: typeof trust.text === "string" ? trust : { text: "" },
        searchAction,
        searchPlaceholder:
            search.placeholder || DEFAULT_PLACEHOLDER,
        cart,
    }).mount(el);
}
