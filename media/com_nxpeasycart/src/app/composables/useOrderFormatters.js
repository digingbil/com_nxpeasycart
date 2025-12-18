/**
 * Order formatting composable.
 *
 * Provides currency, date, state label, and other formatting utilities for orders.
 *
 * @param {Function} translate - The translation function (__)
 * @returns {Object} Formatting utilities
 */
export function useOrderFormatters(translate) {
    const __ = translate;

    /**
     * Format cents to currency string.
     *
     * @param {number} cents - Amount in cents
     * @param {string} currency - Currency code (e.g., "USD")
     * @returns {string} Formatted currency string
     */
    const formatCurrency = (cents, currency) => {
        const amount = (Number(cents) || 0) / 100;
        const code = (currency || "").toUpperCase() || "USD";

        try {
            return new Intl.NumberFormat(undefined, {
                style: "currency",
                currency: code,
            }).format(amount);
        } catch (error) {
            return `${code} ${amount.toFixed(2)}`;
        }
    };

    /**
     * Format ISO date string to locale string.
     *
     * @param {string} iso - ISO date string
     * @returns {string} Formatted date
     */
    const formatDate = (iso) => {
        if (!iso) {
            return "";
        }

        const date = new Date(iso);

        if (Number.isNaN(date.getTime())) {
            return iso;
        }

        return date.toLocaleString();
    };

    /**
     * Format timestamp for "last updated" display.
     *
     * @param {string} iso - ISO date string
     * @returns {string} Formatted timestamp
     */
    const formatTimestamp = (iso) => {
        if (!iso) {
            return "";
        }

        const date = new Date(iso);

        if (Number.isNaN(date.getTime())) {
            return iso;
        }

        return date.toLocaleString();
    };

    /**
     * Get translated label for order state.
     *
     * @param {string} state - Order state
     * @returns {string} Translated state label
     */
    const stateLabel = (state) => {
        if (!state) {
            return "";
        }

        const key = `COM_NXPEASYCART_ORDERS_STATE_${String(state).toUpperCase()}`;

        return __(key, state);
    };

    /**
     * Format items count label.
     *
     * @param {number} count - Number of items
     * @returns {string} Translated items label
     */
    const itemsLabel = (count) => {
        if (count === 1) {
            return __("COM_NXPEASYCART_ORDERS_BADGE_ITEM", "1 item");
        }

        return __(
            "COM_NXPEASYCART_ORDERS_BADGE_ITEMS",
            "%s items",
            [String(count)]
        );
    };

    /**
     * Format review reason for display.
     *
     * @param {string} reason - Review reason code
     * @returns {string} Translated reason
     */
    const formatReviewReason = (reason) => {
        if (!reason) {
            return __("COM_NXPEASYCART_ORDERS_REVIEW_UNKNOWN", "Unknown reason");
        }

        const reasonMap = {
            payment_amount_mismatch: __(
                "COM_NXPEASYCART_ORDERS_REVIEW_AMOUNT_MISMATCH",
                "Payment amount does not match order total. Please verify the transaction in your payment gateway dashboard."
            ),
        };

        return reasonMap[reason] || reason;
    };

    /**
     * Format download count label.
     *
     * @param {Object} download - Download object
     * @returns {string} Download count label
     */
    const downloadCountLabel = (download) => {
        const used = Number(download?.download_count ?? 0);
        const max = Number(download?.max_downloads ?? 0);

        if (max > 0) {
            const remaining = Math.max(0, max - used);
            return __(
                "COM_NXPEASYCART_ORDER_DOWNLOADS_REMAINING",
                "%d downloads remaining",
                [remaining]
            );
        }

        return __(
            "COM_NXPEASYCART_ORDER_DOWNLOADS_UNLIMITED",
            "Unlimited downloads"
        );
    };

    /**
     * Format download expiry label.
     *
     * @param {Object} download - Download object
     * @returns {string} Expiry label
     */
    const downloadExpiryLabel = (download) => {
        if (!download?.expires_at) {
            return __("JNONE", "No expiry");
        }

        return __(
            "COM_NXPEASYCART_ORDER_DOWNLOADS_EXPIRES",
            "Expires %s",
            [download.expires_at]
        );
    };

    /**
     * Convert address object to display lines.
     *
     * @param {Object} address - Address object
     * @returns {Array} Address lines for display
     */
    const addressLines = (address) => {
        if (!address || typeof address !== "object") {
            return [];
        }

        return Object.entries(address)
            .filter(([, value]) => value != null && `${value}`.trim() !== "")
            .map(([key, value]) => ({
                key,
                value: `${value}`.trim(),
            }));
    };

    return {
        formatCurrency,
        formatDate,
        formatTimestamp,
        stateLabel,
        itemsLabel,
        formatReviewReason,
        downloadCountLabel,
        downloadExpiryLabel,
        addressLines,
    };
}
