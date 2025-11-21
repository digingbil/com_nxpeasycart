const islandLoaders = {
    product: () => import("./site/islands/product.js").then((m) => m.default),
    "cart-button": () =>
        import("./site/islands/product.js").then((m) => m.default),
    category: () => import("./site/islands/category.js").then((m) => m.default),
    cart: () => import("./site/islands/cart.js").then((m) => m.default),
    "cart-summary": () =>
        import("./site/islands/cartSummary.js").then((m) => m.default),
    checkout: () => import("./site/islands/checkout.js").then((m) => m.default),
    landing: () =>
        import("./site/landing/index.js").then((m) => m.mountLandingIsland),
};

const cache = {};

const getMount = (key) => {
    if (cache[key]) {
        return cache[key];
    }

    const loader = islandLoaders[key];

    if (!loader) {
        return null;
    }

    cache[key] = loader();

    return cache[key];
};

const runMount = (el, key) => {
    if (el.dataset.nxpMounted === "1") {
        return;
    }

    const mountPromise = getMount(key);

    if (!mountPromise) {
        return;
    }

    mountPromise
        .then((mount) => {
            if (typeof mount === "function") {
                mount(el);
                el.dataset.nxpMounted = "1";
            }
        })
        .catch(() => {
            // Swallow mount errors to avoid breaking other islands.
        });
};

const bootIslands = () => {
    const observerSupported =
        typeof window !== "undefined" && "IntersectionObserver" in window;
    const observer = observerSupported
        ? new IntersectionObserver(
              (entries) => {
                  entries.forEach((entry) => {
                      if (entry.isIntersecting) {
                          const target = entry.target;
                          observer.unobserve(target);
                          runMount(target, target.dataset.nxpIsland);
                      }
                  });
              },
              { rootMargin: "200px 0px", threshold: 0.01 }
          )
        : null;

    document.querySelectorAll("[data-nxp-island]").forEach((el) => {
        const key = el.dataset.nxpIsland;

        if (!key) {
            return;
        }

        if (observer) {
            observer.observe(el);
            return;
        }

        runMount(el, key);
    });
};

const mountOnInteraction = (event) => {
    // If the island isn't mounted yet, mount it and replay the click so the first tap still works.
    const target =
        typeof event.target?.closest === "function"
            ? event.target.closest("[data-nxp-island]")
            : null;

    if (!target) {
        return;
    }

    const key = target.dataset.nxpIsland;

    if (!key) {
        return;
    }

    const alreadyMounted = target.dataset.nxpMounted === "1";
    runMount(target, key);

    if (!alreadyMounted) {
        // Re-dispatch a click on the newly mounted element so the initial tap triggers handlers.
        queueMicrotask(() => {
            target.dispatchEvent(
                new MouseEvent("click", {
                    bubbles: true,
                    cancelable: true,
                    view: window,
                })
            );
        });
    }
};

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bootIslands);
} else {
    bootIslands();
}

document.addEventListener("click", mountOnInteraction, true);
