export default function parsePayload(value, fallback = {}) {
    if (!value) {
        return fallback;
    }

    try {
        return JSON.parse(value);
    } catch (error) {
        console.warn("[NXP Easy Cart] Failed to parse island payload", error);
        return fallback;
    }
}
