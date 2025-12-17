/**
 * useProductImages - Product image management composable.
 *
 * Handles basic image operations: add, remove, move, update, reset.
 *
 * @since 0.3.2
 */
export function useProductImages(formImages) {
    /**
     * Ensure images is an array.
     */
    const ensureImagesArray = () => {
        if (!Array.isArray(formImages.value)) {
            formImages.value = [];
        }
    };

    /**
     * Reset images from an array.
     */
    const resetImages = (images) => {
        formImages.value.splice(0, formImages.value.length, ...images);
    };

    /**
     * Add an empty image slot.
     */
    const addImage = () => {
        ensureImagesArray();
        formImages.value.push("");
    };

    /**
     * Remove an image by index.
     */
    const removeImage = (index) => {
        ensureImagesArray();
        formImages.value.splice(index, 1);
    };

    /**
     * Move an image up or down.
     */
    const moveImage = (index, offset) => {
        ensureImagesArray();

        const current = formImages.value[index];

        if (current === undefined) {
            return;
        }

        const nextIndex = index + offset;

        if (nextIndex < 0 || nextIndex >= formImages.value.length) {
            return;
        }

        formImages.value.splice(index, 1);
        formImages.value.splice(nextIndex, 0, current);
    };

    /**
     * Update an image at a specific index.
     */
    const updateImage = (index, value) => {
        ensureImagesArray();

        if (index >= 0 && index < formImages.value.length) {
            formImages.value[index] = value;
        }
    };

    /**
     * Build payload images for submission (deduplicated, trimmed).
     */
    const buildPayloadImages = () => {
        ensureImagesArray();

        return Array.from(
            new Set(
                formImages.value
                    .map((image) => String(image ?? "").trim())
                    .filter((image) => image !== "")
            )
        );
    };

    return {
        ensureImagesArray,
        resetImages,
        addImage,
        removeImage,
        moveImage,
        updateImage,
        buildPayloadImages,
    };
}
