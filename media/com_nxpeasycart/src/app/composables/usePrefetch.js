/**
 * Prefetch utility for adjacent admin panels
 *
 * Preloads data for likely-next panels in the background
 * to improve perceived performance when navigating tabs.
 */

import { ref } from "vue";

const prefetchQueue = ref(new Set());
const prefetchInProgress = ref(false);

const PREFETCH_DELAY = 500;

/**
 * Panel adjacency map
 * Maps each section to its likely next destinations
 */
const adjacencyMap = {
    dashboard: ["products", "orders", "settings"],
    products: ["categories", "orders"],
    categories: ["products"],
    orders: ["customers", "products"],
    customers: ["orders"],
    coupons: ["settings"],
    settings: ["payments", "tax", "shipping"],
    logs: [],
};

/**
 * Check if a resource should be prefetched
 */
function shouldPrefetch(resourceName) {
    return !prefetchQueue.value.has(resourceName);
}

/**
 * Register a prefetch task
 */
function registerPrefetch(resourceName, loader) {
    if (!shouldPrefetch(resourceName)) {
        return Promise.resolve();
    }

    prefetchQueue.value.add(resourceName);

    return new Promise((resolve) => {
        setTimeout(async () => {
            if (!prefetchInProgress.value) {
                prefetchInProgress.value = true;

                try {
                    console.log(
                        `[NXP EC Prefetch] Loading ${resourceName} in background`
                    );
                    await loader();
                    console.log(
                        `[NXP EC Prefetch] Completed ${resourceName}`
                    );
                } catch (error) {
                    console.warn(
                        `[NXP EC Prefetch] Failed to prefetch ${resourceName}:`,
                        error
                    );
                } finally {
                    prefetchInProgress.value = false;
                    resolve();
                }
            } else {
                resolve();
            }
        }, PREFETCH_DELAY);
    });
}

/**
 * Prefetch adjacent panels based on current section
 */
export function usePrefetch() {
    const prefetchAdjacent = (currentSection, loaders = {}) => {
        const adjacent = adjacencyMap[currentSection] || [];

        adjacent.forEach((sectionName) => {
            const loader = loaders[sectionName];

            if (loader && typeof loader === "function") {
                registerPrefetch(sectionName, loader);
            }
        });
    };

    const clearPrefetchQueue = () => {
        prefetchQueue.value.clear();
        prefetchInProgress.value = false;
    };

    const isPrefetching = () => prefetchInProgress.value;

    return {
        prefetchAdjacent,
        clearPrefetchQueue,
        isPrefetching,
        prefetchQueue,
    };
}

export default usePrefetch;
