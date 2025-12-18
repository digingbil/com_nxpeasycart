import { ref } from "vue";

/**
 * Clipboard composable.
 *
 * Provides clipboard copy functionality with fallback support.
 *
 * @param {Function} translate - The translation function (__)
 * @param {number} messageTimeout - Time in ms to clear the message (default: 2000)
 * @returns {Object} Clipboard utilities
 */
export function useClipboard(translate, messageTimeout = 2000) {
    const __ = translate;
    const message = ref("");
    let clearTimer = null;

    /**
     * Clear the copy message after timeout.
     */
    const scheduleClear = () => {
        if (clearTimer) {
            clearTimeout(clearTimer);
        }

        if (message.value) {
            clearTimer = setTimeout(() => {
                message.value = "";
                clearTimer = null;
            }, messageTimeout);
        }
    };

    /**
     * Copy text to clipboard.
     *
     * @param {string} text - Text to copy
     * @returns {Promise<boolean>} True if copy succeeded
     */
    const copy = async (text) => {
        if (!text) {
            return false;
        }

        try {
            if (navigator?.clipboard?.writeText) {
                await navigator.clipboard.writeText(text);
                message.value = __(
                    "COM_NXPEASYCART_ORDERS_LINK_COPIED",
                    "Link copied"
                );
                scheduleClear();
                return true;
            }

            throw new Error("Clipboard unavailable");
        } catch (error) {
            // Fallback to temporary textarea for HTTP or blocked clipboard contexts
            try {
                const el = document.createElement("textarea");
                el.value = text;
                el.setAttribute("readonly", "");
                el.style.position = "absolute";
                el.style.left = "-9999px";
                document.body.appendChild(el);
                el.select();
                document.execCommand("copy");
                document.body.removeChild(el);
                message.value = __(
                    "COM_NXPEASYCART_ORDERS_LINK_COPIED",
                    "Link copied"
                );
                scheduleClear();
                return true;
            } catch (fallbackError) {
                message.value = __(
                    "COM_NXPEASYCART_ORDERS_LINK_COPY_FALLBACK",
                    "Copy the link below."
                );
                scheduleClear();
                return false;
            }
        }
    };

    /**
     * Clear the message immediately.
     */
    const clearMessage = () => {
        message.value = "";
        if (clearTimer) {
            clearTimeout(clearTimer);
            clearTimer = null;
        }
    };

    return {
        message,
        copy,
        clearMessage,
    };
}
