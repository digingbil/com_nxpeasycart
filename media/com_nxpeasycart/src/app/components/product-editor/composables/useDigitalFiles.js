import { reactive, ref } from "vue";

/**
 * useDigitalFiles - Digital file management composable.
 *
 * Handles digital file upload, deletion, and state management.
 *
 * @since 0.3.2
 */
export function useDigitalFiles(formDigitalFiles, apiClient, digitalEndpoints, translate) {
    const __ = translate;

    const digitalState = reactive({
        files: [],
        loading: false,
        uploading: false,
        deletingId: 0,
        error: "",
        version: "1.0",
        variantId: "",
    });

    const selectedFile = ref(null);
    const digitalFilesTabRef = ref(null);

    /**
     * Normalize digital files array.
     */
    const normaliseDigitalFiles = (files) => {
        if (!Array.isArray(files)) {
            return [];
        }

        return files
            .map((file) => ({
                id: Number.parseInt(file?.id ?? 0, 10) || 0,
                product_id: Number.parseInt(file?.product_id ?? 0, 10) || 0,
                variant_id:
                    file?.variant_id !== null && file?.variant_id !== undefined
                        ? Number.parseInt(file.variant_id, 10) || 0
                        : null,
                filename: String(file?.filename ?? "").trim(),
                storage_path: String(file?.storage_path ?? "").trim(),
                file_size: Number.parseInt(file?.file_size ?? 0, 10) || 0,
                mime_type:
                    file?.mime_type !== null && file?.mime_type !== undefined
                        ? String(file.mime_type).trim()
                        : "",
                version: String(file?.version ?? "1.0").trim() || "1.0",
                created: String(file?.created ?? "").trim(),
            }))
            .filter((file) => file.id > 0 && file.filename !== "");
    };

    /**
     * Reset the file input.
     */
    const resetFileInput = () => {
        selectedFile.value = null;

        if (digitalFilesTabRef.value?.resetFileInput) {
            digitalFilesTabRef.value.resetFileInput();
        }
    };

    /**
     * Reset digital files state from product data.
     */
    const resetDigitalFiles = (files) => {
        const digitalFiles = normaliseDigitalFiles(files ?? []);
        formDigitalFiles.value.splice(0, formDigitalFiles.value.length, ...digitalFiles);
        digitalState.files = digitalFiles.slice();
        digitalState.error = "";
        digitalState.variantId = "";
        digitalState.version = "1.0";
        digitalState.deletingId = 0;
        resetFileInput();
    };

    /**
     * Load digital files from server.
     */
    const loadDigitalFiles = async (productId) => {
        if (!productId) {
            digitalState.files = normaliseDigitalFiles(formDigitalFiles.value);
            return;
        }

        if (!digitalEndpoints.value?.list) {
            digitalState.files = normaliseDigitalFiles(formDigitalFiles.value);
            return;
        }

        digitalState.loading = true;
        digitalState.error = "";

        try {
            const files = await apiClient.fetchDigitalFiles({
                endpoint: digitalEndpoints.value.list,
                productId: productId,
            });
            const normalised = normaliseDigitalFiles(files);
            digitalState.files = normalised;
            formDigitalFiles.value.splice(0, formDigitalFiles.value.length, ...normalised);
        } catch (error) {
            digitalState.error =
                error?.message ||
                __("COM_NXPEASYCART_ERROR_DIGITAL_FILES_UNAVAILABLE", "Unable to load digital files.");
        } finally {
            digitalState.loading = false;
        }
    };

    /**
     * Handle file input change.
     */
    const handleFileChange = (file) => {
        selectedFile.value = file;
    };

    /**
     * Upload a digital file.
     */
    const uploadDigitalFile = async (productId) => {
        if (!productId) {
            digitalState.error = __(
                "COM_NXPEASYCART_ERROR_PRODUCT_ID_REQUIRED",
                "Save the product before uploading files."
            );
            return;
        }

        if (!digitalEndpoints.value?.upload) {
            digitalState.error = __(
                "COM_NXPEASYCART_ERROR_DIGITAL_FILES_UNAVAILABLE",
                "Digital upload endpoint unavailable."
            );
            return;
        }

        if (!selectedFile.value) {
            digitalState.error = __(
                "COM_NXPEASYCART_DIGITAL_FILES_UPLOAD",
                "Select a file to upload."
            );
            return;
        }

        digitalState.uploading = true;
        digitalState.error = "";

        try {
            const uploaded = await apiClient.uploadDigitalFile({
                endpoint: digitalEndpoints.value.upload,
                productId: productId,
                variantId: digitalState.variantId
                    ? Number.parseInt(digitalState.variantId, 10) || null
                    : null,
                version: digitalState.version || "1.0",
                file: selectedFile.value,
            });

            const normalised = uploaded ? normaliseDigitalFiles([uploaded]) : [];

            if (normalised.length) {
                digitalState.files.unshift(normalised[0]);
                formDigitalFiles.value.unshift(normalised[0]);
            }
        } catch (error) {
            digitalState.error =
                error?.message ||
                __("COM_NXPEASYCART_ERROR_UPLOAD_FAILED", "Upload failed.");
        } finally {
            digitalState.uploading = false;
            resetFileInput();
        }
    };

    /**
     * Delete a digital file.
     */
    const deleteDigitalFile = async (file) => {
        const id = Number.parseInt(file?.id ?? 0, 10) || 0;

        if (!id || !digitalEndpoints.value?.delete) {
            return;
        }

        digitalState.deletingId = id;
        digitalState.error = "";

        try {
            await apiClient.deleteDigitalFile({
                endpoint: digitalEndpoints.value.delete,
                fileId: id,
            });

            const remaining = digitalState.files.filter((item) => item.id !== id);
            digitalState.files = remaining;
            formDigitalFiles.value.splice(0, formDigitalFiles.value.length, ...remaining);
        } catch (error) {
            digitalState.error =
                error?.message ||
                __("COM_NXPEASYCART_ERROR_DIGITAL_FILES_UNAVAILABLE", "Unable to delete file.");
        } finally {
            digitalState.deletingId = 0;
        }
    };

    return {
        digitalState,
        selectedFile,
        digitalFilesTabRef,
        normaliseDigitalFiles,
        resetFileInput,
        resetDigitalFiles,
        loadDigitalFiles,
        handleFileChange,
        uploadDigitalFile,
        deleteDigitalFile,
    };
}
