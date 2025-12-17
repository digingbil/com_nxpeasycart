import { computed, onMounted, onBeforeUnmount } from "vue";

/**
 * useMediaPicker - Joomla media picker integration composable.
 *
 * Handles the Joomla media picker field creation and event handling
 * for both product images and variant images.
 *
 * @since 0.3.2
 */
export function useMediaPicker(formImages, formVariants, mediaModalUrlProp, translate) {
    const __ = translate;

    let mediaPickerField = null;
    let mediaPickerWrapper = null;
    let mediaPickerInput = null;
    let mediaPickerIndex = null;
    let variantImagePickerIndex = null;
    let pendingMediaValue = "";

    /**
     * Resolve an image path to a full URL.
     */
    const resolveImageUrl = (imgPath) => {
        if (!imgPath || typeof imgPath !== "string") {
            return "";
        }

        const trimmed = imgPath.trim();

        if (trimmed === "") {
            return "";
        }

        if (
            trimmed.startsWith("http://") ||
            trimmed.startsWith("https://") ||
            trimmed.startsWith("//")
        ) {
            return trimmed;
        }

        if (typeof window !== "undefined") {
            try {
                const systemPaths = window.Joomla?.getOptions?.("system.paths", {}) ?? {};
                const root = systemPaths.root || "";
                const normalised = trimmed.startsWith("/") ? trimmed : `/${trimmed}`;
                return root + normalised;
            } catch (e) {
                // Fallback
            }
        }

        return trimmed.startsWith("/") ? trimmed : `/${trimmed}`;
    };

    /**
     * Check if Joomla media picker is available.
     */
    const hasMediaModal = computed(() => {
        if (typeof window === "undefined") {
            return false;
        }

        return Boolean(window.customElements?.get("joomla-field-media"));
    });

    /**
     * Normalize a media value (strip prefixes, metadata, etc).
     */
    const normaliseMediaValue = (value) => {
        if (!value) {
            return "";
        }

        let result = String(value).trim();

        if (result === "") {
            return "";
        }

        try {
            const systemPaths = window.Joomla?.getOptions?.("system.paths", {}) ?? {};
            const rootFull = systemPaths.rootFull || window.location.origin;
            const rootRel = systemPaths.root || "/";

            if (result.startsWith(rootFull)) {
                result = result.substring(rootFull.length);
            }

            if (result.startsWith(rootRel)) {
                result = result.substring(rootRel.length);
            }
        } catch (error) {
            // ignore
        }

        const adapterMatch = result.match(
            /^(local-[a-z0-9_-]+|images|videos|audios|documents):\/\/?(.*)$/i
        );
        if (adapterMatch && adapterMatch[2]) {
            const adapter = adapterMatch[1].toLowerCase();
            let extractedPath = adapterMatch[2];

            if (adapter === "local-images" || adapter === "images") {
                if (
                    !extractedPath.startsWith("images/") &&
                    !extractedPath.startsWith("/images/")
                ) {
                    extractedPath = "images/" + extractedPath;
                }
            } else if (adapter.startsWith("local-")) {
                const folderName = adapter.replace("local-", "");
                if (
                    !extractedPath.startsWith(folderName + "/") &&
                    !extractedPath.startsWith("/" + folderName + "/")
                ) {
                    extractedPath = folderName + "/" + extractedPath;
                }
            }

            result = extractedPath;
        }

        const metadataIndex = result.indexOf("#joomlaImage://");
        if (metadataIndex !== -1) {
            result = result.substring(0, metadataIndex);
        }

        return result.trim();
    };

    /**
     * Apply an image selection to the form.
     */
    const applyImageSelection = (
        rawValue,
        { keepIndex = false, appendWhenNoIndex = false, targetIndex = null } = {}
    ) => {
        const value = normaliseMediaValue(rawValue);

        if (value === "") {
            return false;
        }

        if (!Array.isArray(formImages.value)) {
            formImages.value = [];
        }

        const indexToUse =
            targetIndex !== null && targetIndex >= 0 ? targetIndex : mediaPickerIndex;

        if (indexToUse === null || indexToUse === undefined || Number.isNaN(indexToUse)) {
            if (!appendWhenNoIndex) {
                return false;
            }

            formImages.value.push(value);

            if (!keepIndex) {
                mediaPickerIndex = null;
                pendingMediaValue = "";
            }

            return true;
        }

        if (indexToUse >= formImages.value.length) {
            formImages.value.push(value);
        } else {
            formImages.value[indexToUse] = value;
        }

        pendingMediaValue = keepIndex ? value : "";

        if (!keepIndex) {
            mediaPickerIndex = null;
            pendingMediaValue = "";
        }

        return true;
    };

    /**
     * Build the media modal URL.
     */
    const buildMediaModalUrl = () => {
        const configured = (mediaModalUrlProp.value || "").trim();

        if (configured !== "") {
            return configured;
        }

        const mediaPickerOptions = window.Joomla?.getOptions?.("media-picker", {}) ?? {};
        if (mediaPickerOptions.modalUrl) {
            return mediaPickerOptions.modalUrl;
        }

        const systemPaths = window.Joomla?.getOptions?.("system.paths", {}) ?? {};
        const root = systemPaths.rootFull || systemPaths.root || "";
        const base = root ? root.replace(/\/?$/, "/") : "";

        return (
            base +
            "index.php?option=com_media&view=media&tmpl=component&layout=modal&mediatypes=0,1,2,3&asset=com_nxpeasycart"
        );
    };

    /**
     * Ensure the media picker field is created.
     */
    const ensureMediaPickerField = async () => {
        if (mediaPickerField) {
            return mediaPickerField;
        }

        if (
            typeof window === "undefined" ||
            !window.Joomla ||
            !window.customElements
        ) {
            return null;
        }

        if (!customElements.get("joomla-field-media")) {
            try {
                await customElements.whenDefined("joomla-field-media");
            } catch (error) {
                return null;
            }
        }

        const systemPaths = window.Joomla?.getOptions?.("system.paths", {}) ?? {};
        const rootFull = systemPaths.rootFull || systemPaths.root || "";
        const mediaParams = window.Joomla?.getOptions?.("com_media", {}) ?? {};
        const rootFolder = mediaParams?.file_path || "images";
        const supported = window.Joomla?.getOptions?.("media-picker", {}) ?? {
            images: ["bmp", "gif", "jpg", "jpeg", "png", "webp", "svg", "avif"],
            audios: [],
            videos: [],
            documents: [],
        };
        const modalTitleText = __(
            "COM_NXPEASYCART_FIELD_PRODUCT_IMAGES_MODAL_TITLE",
            "Media Manager",
            [],
            "productImagesModalTitle"
        );
        const safeModalTitle = modalTitleText.replace(/"/g, "&quot;");

        mediaPickerWrapper = document.createElement("div");
        mediaPickerWrapper.className = "nxp-ec-media-picker-host";
        mediaPickerWrapper.style.display = "none";

        mediaPickerWrapper.innerHTML = `
            <joomla-field-media
                class="nxp-ec-media-field"
                types="images"
                base-path="${rootFull.replace(/"/g, "&quot;")}"
                root-folder="${rootFolder.replace(/"/g, "&quot;")}"
                url=""
                preview="none"
                input=".nxp-ec-media-field__input"
                button-select=".nxp-ec-media-field__select"
                button-clear=".nxp-ec-media-field__clear"
                modal-title="${safeModalTitle}"
                modal-width="960"
                modal-height="600"
                supported-extensions='${JSON.stringify(supported).replace(/'/g, "&apos;")}'
            >
                <div class="input-group">
                    <input type="text" class="nxp-ec-media-field__input field-media-input" />
                    <button type="button" class="nxp-ec-media-field__select button-select"></button>
                    <button type="button" class="nxp-ec-media-field__clear button-clear"></button>
                </div>
            </joomla-field-media>
        `;

        document.body.appendChild(mediaPickerWrapper);

        mediaPickerField = mediaPickerWrapper.querySelector("joomla-field-media");
        mediaPickerInput = mediaPickerWrapper.querySelector(".nxp-ec-media-field__input");

        mediaPickerField.addEventListener("change", (event) => {
            const rawValue = event.detail?.value ?? mediaPickerInput?.value ?? "";
            let value = normaliseMediaValue(rawValue);

            if (value === "" && pendingMediaValue !== "") {
                value = pendingMediaValue;
            }

            if (value === "" && window.Joomla?.selectedMediaFile) {
                const fallbackUrl = normaliseMediaValue(
                    window.Joomla.selectedMediaFile.url ?? ""
                );
                const fallbackPath = normaliseMediaValue(
                    window.Joomla.selectedMediaFile.path ?? ""
                );
                value = fallbackUrl || fallbackPath;
            }

            if (import.meta?.env?.DEV) {
                console.debug("[ProductEditor] media change", {
                    value,
                    rawValue,
                    pending: pendingMediaValue,
                    index: mediaPickerIndex,
                    detail: event.detail,
                });
            }

            applyImageSelection(value);
        });

        mediaPickerField.addEventListener("joomla-dialog:close", () => {
            if (import.meta?.env?.DEV) {
                console.debug("[ProductEditor] dialog closed, index:", mediaPickerIndex);
            }
            mediaPickerIndex = null;
            pendingMediaValue = "";
        });

        if (mediaPickerInput) {
            mediaPickerInput.addEventListener("change", () => {
                if (mediaPickerIndex === null) {
                    return;
                }

                let value = normaliseMediaValue(mediaPickerInput.value ?? "");

                if (value === "" && pendingMediaValue !== "") {
                    value = pendingMediaValue;
                }

                if (value === "" && window.Joomla?.selectedMediaFile) {
                    const fallbackUrl = normaliseMediaValue(
                        window.Joomla.selectedMediaFile.url ?? ""
                    );
                    const fallbackPath = normaliseMediaValue(
                        window.Joomla.selectedMediaFile.path ?? ""
                    );
                    value = fallbackUrl || fallbackPath;
                }

                if (import.meta?.env?.DEV) {
                    console.debug("[ProductEditor] input change", {
                        value,
                        pending: pendingMediaValue,
                        index: mediaPickerIndex,
                    });
                }

                if (value === "") {
                    return;
                }

                applyImageSelection(value);
            });
        }

        return mediaPickerField;
    };

    /**
     * Handle media file selected event.
     */
    const handleMediaFileSelected = (event) => {
        const detail = event?.detail;

        if (!detail || typeof detail !== "object") {
            return;
        }

        const path = normaliseMediaValue(detail.path ?? "");
        const url = normaliseMediaValue(detail.url ?? "");
        const resolved = url || path;

        if (import.meta?.env?.DEV) {
            console.debug("[ProductEditor] media file selected", {
                resolved,
                path,
                url,
                detail,
                currentIndex: mediaPickerIndex,
                variantIndex: variantImagePickerIndex,
            });
        }

        if (resolved === "") {
            return;
        }

        // Handle variant image selection
        if (variantImagePickerIndex !== null) {
            const variant = formVariants.value[variantImagePickerIndex];

            if (variant) {
                if (!Array.isArray(variant.images)) {
                    variant.images = [];
                }

                variant.images.push(resolved);

                if (import.meta?.env?.DEV) {
                    console.debug("[ProductEditor] Added variant image", {
                        variantIndex: variantImagePickerIndex,
                        value: resolved,
                    });
                }
            }

            variantImagePickerIndex = null;
            return;
        }

        // Handle product image selection
        if (mediaPickerIndex === null) {
            return;
        }

        pendingMediaValue = resolved;

        if (
            applyImageSelection(resolved, { keepIndex: true }) &&
            import.meta?.env?.DEV
        ) {
            console.debug("[ProductEditor] Applied image immediately", {
                index: mediaPickerIndex,
                value: resolved,
            });
        }
    };

    /**
     * Prompt for image URL (fallback).
     */
    const promptForImage = (index) => {
        if (!Array.isArray(formImages.value)) {
            formImages.value = [];
        }

        const current = formImages.value[index] ?? "";

        if (typeof window === "undefined") {
            return;
        }

        const value = window.prompt(
            __("COM_NXPEASYCART_FIELD_PRODUCT_IMAGES_PROMPT", "Image URL"),
            current
        );

        applyImageSelection(value ?? "", { targetIndex: index });
    };

    /**
     * Open the media modal for a product image.
     */
    const openMediaModal = async (index) => {
        if (!Array.isArray(formImages.value)) {
            formImages.value = [];
        }

        const picker = await ensureMediaPickerField();

        if (!picker || typeof picker.show !== "function") {
            promptForImage(index);
            return;
        }

        mediaPickerIndex = index;
        picker.setAttribute("url", buildMediaModalUrl());

        const currentValue = formImages.value[index] ?? "";

        if (typeof picker.setValue === "function") {
            picker.setValue(currentValue);
        } else if (mediaPickerInput) {
            mediaPickerInput.value = currentValue;
        }

        try {
            picker.show();
        } catch (error) {
            mediaPickerIndex = null;
            pendingMediaValue = "";
            promptForImage(index);
        }
    };

    /**
     * Open the media modal for a variant image.
     */
    const openVariantMediaModal = async (variantIndex) => {
        const variant = formVariants.value[variantIndex];

        if (!variant) {
            return;
        }

        if (!Array.isArray(variant.images)) {
            variant.images = [];
        }

        const picker = await ensureMediaPickerField();

        if (!picker || typeof picker.show !== "function") {
            const url = window.prompt(
                __("COM_NXPEASYCART_FIELD_IMAGE_URL_PROMPT", "Enter image URL:"),
                ""
            );

            if (url && url.trim() !== "") {
                variant.images.push(url.trim());
            }

            return;
        }

        variantImagePickerIndex = variantIndex;
        mediaPickerIndex = null;

        picker.setAttribute("url", buildMediaModalUrl());

        if (typeof picker.setValue === "function") {
            picker.setValue("");
        } else if (mediaPickerInput) {
            mediaPickerInput.value = "";
        }

        try {
            picker.show();
        } catch (error) {
            variantImagePickerIndex = null;

            const url = window.prompt(
                __("COM_NXPEASYCART_FIELD_IMAGE_URL_PROMPT", "Enter image URL:"),
                ""
            );

            if (url && url.trim() !== "") {
                variant.images.push(url.trim());
            }
        }
    };

    /**
     * Setup lifecycle hooks.
     */
    const setupLifecycle = () => {
        onMounted(() => {
            if (typeof document !== "undefined") {
                document.addEventListener("onMediaFileSelected", handleMediaFileSelected);
            }
        });

        onBeforeUnmount(() => {
            if (mediaPickerWrapper && mediaPickerWrapper.parentNode) {
                mediaPickerWrapper.parentNode.removeChild(mediaPickerWrapper);
            }

            mediaPickerField = null;
            mediaPickerWrapper = null;
            mediaPickerInput = null;
            mediaPickerIndex = null;
            variantImagePickerIndex = null;
            pendingMediaValue = "";

            if (typeof document !== "undefined") {
                document.removeEventListener("onMediaFileSelected", handleMediaFileSelected);
            }
        });
    };

    return {
        resolveImageUrl,
        hasMediaModal,
        normaliseMediaValue,
        openMediaModal,
        openVariantMediaModal,
        setupLifecycle,
    };
}
