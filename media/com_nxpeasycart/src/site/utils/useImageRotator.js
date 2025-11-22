import { onBeforeUnmount, reactive } from "vue";

const hasImages = (images) =>
    Array.isArray(images) && images.filter((src) => typeof src === "string" && src.trim() !== "").length > 1;
const ROTATION_INTERVAL = 1000;

export function useImageRotator(keyFor) {
    const state = reactive({});
    const timers = {};

    const normaliseImages = (raw = []) =>
        Array.isArray(raw)
            ? raw
                  .filter((src) => typeof src === "string" && src.trim() !== "")
                  .map((src) => src.trim())
            : [];

    const ensureState = (key) => {
        if (!state[key]) {
            state[key] = { index: 0 };
        }

        return state[key];
    };

    const clearTimer = (key) => {
        if (timers[key]) {
            window.clearInterval(timers[key]);
            delete timers[key];
        }
    };

    const activeImage = (item) => {
        const key = keyFor(item);
        const images = normaliseImages(item?.images || []);

        if (images.length === 0) {
            return "";
        }

        const current = ensureState(key);
        const index =
            typeof current.index === "number" && current.index < images.length
                ? current.index
                : 0;

        return images[index] || images[0];
    };

    const startCycle = (item) => {
        const key = keyFor(item);
        const images = normaliseImages(item?.images || []);

        if (!hasImages(images)) {
            return;
        }

        clearTimer(key);

        timers[key] = window.setInterval(() => {
            const current = ensureState(key);
            current.index = (Number(current.index || 0) + 1) % images.length;
        }, ROTATION_INTERVAL);
    };

    const stopCycle = (item, reset = true) => {
        const key = keyFor(item);
        clearTimer(key);

        if (reset) {
            ensureState(key).index = 0;
        }
    };

    onBeforeUnmount(() => {
        Object.keys(timers).forEach(clearTimer);
    });

    return {
        activeImage,
        startCycle,
        stopCycle,
    };
}

export default useImageRotator;
