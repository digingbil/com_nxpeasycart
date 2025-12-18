import { computed } from "vue";

/**
 * Order lock/checkout composable.
 *
 * Provides utilities for checking order lock state and labels.
 *
 * @param {Function} translate - The translation function (__)
 * @param {import('vue').ComputedRef<number>} currentUserId - Current user ID computed ref
 * @returns {Object} Lock utilities
 */
export function useOrderLock(translate, currentUserId) {
    const __ = translate;

    /**
     * Check if an order is locked by another user.
     *
     * @param {Object} order - Order object
     * @returns {boolean} True if locked by another user
     */
    const isLocked = (order) =>
        order?.checked_out &&
        Number(order.checked_out) !== 0 &&
        Number(order.checked_out) !== currentUserId.value;

    /**
     * Get lock label for an order.
     *
     * @param {Object} order - Order object
     * @returns {string} Lock label
     */
    const lockLabel = (order) => {
        const name = order?.checked_out_user?.name ?? "";
        const displayName =
            order?.checked_out === currentUserId.value
                ? __("JGLOBAL_YOU", "You")
                : name || __("COM_NXPEASYCART_ERROR_ORDER_CHECKED_OUT_GENERIC", "another user");

        return __(
            "COM_NXPEASYCART_CHECKED_OUT_BY",
            "Checked out by %s",
            [displayName]
        );
    };

    /**
     * Create a computed for checking if active order is locked.
     *
     * @param {import('vue').ComputedRef<Object>} activeOrder - Active order computed ref
     * @returns {import('vue').ComputedRef<boolean>} Whether active order is locked
     */
    const createActiveOrderLocked = (activeOrder) =>
        computed(() => isLocked(activeOrder.value));

    return {
        isLocked,
        lockLabel,
        createActiveOrderLocked,
    };
}
