/**
 * Accessibility utilities for admin panels
 *
 * Provides helpers for keyboard navigation, focus management,
 * and ARIA-compliant modal handling.
 */

import { onMounted, onUnmounted, ref } from "vue";

/**
 * Creates a keyboard handler for Escape key to close modals
 *
 * @param {Function} closeHandler - Function to call when Escape is pressed
 * @param {Function|import('vue').Ref} isOpenCheck - Function or ref to check if modal is open
 * @returns {Object} - Setup and teardown functions
 */
export function useEscapeKey(closeHandler, isOpenCheck) {
    const handleKeydown = (event) => {
        if (event.key === "Escape" || event.key === "Esc") {
            const isOpen =
                typeof isOpenCheck === "function"
                    ? isOpenCheck()
                    : isOpenCheck?.value;

            if (isOpen) {
                event.preventDefault();
                closeHandler();
            }
        }
    };

    onMounted(() => {
        document.addEventListener("keydown", handleKeydown);
    });

    onUnmounted(() => {
        document.removeEventListener("keydown", handleKeydown);
    });

    return {
        handleKeydown,
    };
}

/**
 * Creates a focus trap within a container element
 *
 * @param {import('vue').Ref<HTMLElement|null>} containerRef - Ref to the container element
 * @returns {Object} - Focus trap controls
 */
export function useFocusTrap(containerRef) {
    const previousActiveElement = ref(null);

    const getFocusableElements = () => {
        if (!containerRef.value) {
            return [];
        }

        return Array.from(
            containerRef.value.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            )
        ).filter(
            (el) =>
                !el.disabled &&
                !el.hasAttribute("aria-hidden") &&
                el.offsetParent !== null
        );
    };

    const trapFocus = (event) => {
        if (!containerRef.value) {
            return;
        }

        const focusableElements = getFocusableElements();

        if (focusableElements.length === 0) {
            return;
        }

        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];

        if (event.key === "Tab") {
            if (event.shiftKey && document.activeElement === firstElement) {
                event.preventDefault();
                lastElement.focus();
            } else if (
                !event.shiftKey &&
                document.activeElement === lastElement
            ) {
                event.preventDefault();
                firstElement.focus();
            }
        }
    };

    const activate = () => {
        previousActiveElement.value = document.activeElement;

        const focusableElements = getFocusableElements();

        if (focusableElements.length > 0) {
            // Focus the first element after a short delay to ensure DOM is ready
            setTimeout(() => {
                focusableElements[0].focus();
            }, 50);
        }

        document.addEventListener("keydown", trapFocus);
    };

    const deactivate = () => {
        document.removeEventListener("keydown", trapFocus);

        if (previousActiveElement.value && previousActiveElement.value.focus) {
            previousActiveElement.value.focus();
        }
    };

    onUnmounted(() => {
        document.removeEventListener("keydown", trapFocus);
    });

    return {
        activate,
        deactivate,
        getFocusableElements,
    };
}

export default {
    useEscapeKey,
    useFocusTrap,
};
