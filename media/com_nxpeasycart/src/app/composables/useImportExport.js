import { onMounted, reactive, ref, onUnmounted } from "vue";
import { createApiClient } from "../../api.js";

/**
 * Build endpoint URL with task parameter.
 */
const deriveEndpoint = (baseEndpoint, task) => {
    if (!baseEndpoint) {
        return "";
    }

    const origin =
        typeof window !== "undefined"
            ? window.location.origin
            : "http://localhost";
    const url = new URL(baseEndpoint, origin);
    url.searchParams.set("task", task);

    return `${url.pathname}?${url.searchParams.toString()}`;
};

/**
 * Vue composable for managing product import/export operations.
 *
 * @param {Object} options
 * @param {Object} options.endpoints - API endpoints configuration
 * @param {string} options.token - CSRF token
 * @param {boolean} options.autoload - Auto-load platforms on mount
 * @returns {Object} Composable state and methods
 */
export function useImportExport({ endpoints = {}, token = "", autoload = true }) {
    const api = createApiClient({ token });

    const baseEndpoint = endpoints?.base ?? "";
    const importPlatformsEndpoint = endpoints?.importPlatforms ?? deriveEndpoint(baseEndpoint, "api.import.platforms");
    const importUploadEndpoint = endpoints?.importUpload ?? deriveEndpoint(baseEndpoint, "api.import.upload");
    const importDetectEndpoint = endpoints?.importDetect ?? deriveEndpoint(baseEndpoint, "api.import.detect");
    const importStartEndpoint = endpoints?.importStart ?? deriveEndpoint(baseEndpoint, "api.import.start");
    const importProgressEndpoint = endpoints?.importProgress ?? deriveEndpoint(baseEndpoint, "api.import.progress");
    const importCancelEndpoint = endpoints?.importCancel ?? deriveEndpoint(baseEndpoint, "api.import.cancel");
    const importJobsEndpoint = endpoints?.importJobs ?? deriveEndpoint(baseEndpoint, "api.import.jobs");
    const importSamplesEndpoint = endpoints?.importSamples ?? deriveEndpoint(baseEndpoint, "api.import.samples");
    const exportPlatformsEndpoint = endpoints?.exportPlatforms ?? deriveEndpoint(baseEndpoint, "api.export.platforms");
    const exportStartEndpoint = endpoints?.exportStart ?? deriveEndpoint(baseEndpoint, "api.export.start");
    const exportProgressEndpoint = endpoints?.exportProgress ?? deriveEndpoint(baseEndpoint, "api.export.progress");
    const exportDownloadEndpoint = endpoints?.exportDownload ?? deriveEndpoint(baseEndpoint, "api.export.download");
    const exportCancelEndpoint = endpoints?.exportCancel ?? deriveEndpoint(baseEndpoint, "api.export.cancel");
    const exportJobsEndpoint = endpoints?.exportJobs ?? deriveEndpoint(baseEndpoint, "api.export.jobs");

    const state = reactive({
        // General state
        loading: false,
        error: "",
        platforms: [],

        // Import state
        importFile: null,
        importFileId: "",
        importFilename: "",
        importFileSize: 0,
        importRowCount: 0,
        importHeaders: [],
        importPreview: [],
        importDetectedPlatform: null,
        importSelectedPlatform: "",
        importMapping: {},
        importUploading: false,
        importStarting: false,
        importJobId: null,
        importProgress: null,
        importPolling: false,

        // Export state
        exportPlatform: "native",
        exportStarting: false,
        exportJobId: null,
        exportProgress: null,
        exportPolling: false,

        // Job history
        importJobs: [],
        exportJobs: [],
        jobsPagination: {
            total: 0,
            limit: 10,
            pages: 0,
            current: 1,
        },

        // Sample files
        samples: [],
    });

    const pollIntervalRef = ref(null);

    /**
     * Clear any active polling interval.
     */
    const stopPolling = () => {
        if (pollIntervalRef.value) {
            clearInterval(pollIntervalRef.value);
            pollIntervalRef.value = null;
        }

        state.importPolling = false;
        state.exportPolling = false;
    };

    /**
     * Load available platforms.
     */
    const loadPlatforms = async () => {
        if (!importPlatformsEndpoint) {
            return;
        }

        state.loading = true;
        state.error = "";

        try {
            const payload = await api.get(importPlatformsEndpoint);
            state.platforms = payload.data?.platforms ?? [];
        } catch (error) {
            state.error = error?.message ?? "Failed to load platforms";
        } finally {
            state.loading = false;
        }
    };

    /**
     * Load sample CSV files info.
     */
    const loadSamples = async () => {
        if (!importSamplesEndpoint) {
            return;
        }

        try {
            const payload = await api.get(importSamplesEndpoint);
            state.samples = payload.data?.samples ?? [];
        } catch (error) {
            // Samples are optional, don't show error
            state.samples = [];
        }
    };

    /**
     * Upload a CSV file for import.
     *
     * @param {File} file - The CSV file to upload
     */
    const uploadFile = async (file) => {
        if (!importUploadEndpoint || !file) {
            return null;
        }

        state.importUploading = true;
        state.error = "";
        state.importFile = file;

        try {
            const formData = new FormData();
            formData.append("file", file);

            const payload = await api.request(importUploadEndpoint, {
                method: "POST",
                body: formData,
            });

            const data = payload.data ?? {};

            state.importFileId = data.file_id ?? "";
            state.importFilename = data.filename ?? file.name;
            state.importFileSize = data.size ?? file.size;
            state.importRowCount = data.row_count ?? 0;
            state.importHeaders = data.headers ?? [];
            state.importPreview = data.preview ?? [];
            state.importDetectedPlatform = data.detected_platform ?? null;
            state.importSelectedPlatform = data.detected_platform ?? "";

            return data;
        } catch (error) {
            state.error = error?.message ?? "Failed to upload file";
            return null;
        } finally {
            state.importUploading = false;
        }
    };

    /**
     * Detect platform from CSV headers.
     *
     * @param {string[]} headers - CSV column headers
     */
    const detectPlatform = async (headers) => {
        if (!importDetectEndpoint || !headers?.length) {
            return null;
        }

        try {
            const payload = await api.post(importDetectEndpoint, { headers });
            const data = payload.data ?? {};

            state.importDetectedPlatform = data.platform ?? null;
            state.importMapping = data.mapping ?? {};

            return data;
        } catch (error) {
            state.error = error?.message ?? "Failed to detect platform";
            return null;
        }
    };

    /**
     * Start an import job.
     *
     * @param {Object} options - Import options
     */
    const startImport = async (options = {}) => {
        if (!importStartEndpoint || !state.importFileId) {
            state.error = "No file uploaded";
            return null;
        }

        if (!state.importSelectedPlatform) {
            state.error = "Please select a platform";
            return null;
        }

        state.importStarting = true;
        state.error = "";

        try {
            const payload = await api.post(importStartEndpoint, {
                file_id: state.importFileId,
                platform: state.importSelectedPlatform,
                options: {
                    ...options,
                    mapping: state.importMapping,
                },
            });

            const data = payload.data ?? {};

            state.importJobId = data.job_id ?? null;
            state.importProgress = {
                status: data.status ?? "pending",
                total_rows: data.total_rows ?? 0,
                processed_rows: 0,
                imported_products: 0,
                imported_variants: 0,
                imported_categories: 0,
                skipped_rows: 0,
                errors: [],
                warnings: [],
                percent: 0,
            };

            // Start polling for progress
            startImportPolling();

            return data;
        } catch (error) {
            state.error = error?.message ?? "Failed to start import";
            return null;
        } finally {
            state.importStarting = false;
        }
    };

    /**
     * Poll for import job progress.
     */
    const pollImportProgress = async () => {
        if (!importProgressEndpoint || !state.importJobId) {
            return;
        }

        try {
            const url = `${importProgressEndpoint}&job_id=${state.importJobId}`;
            const payload = await api.get(url);
            const data = payload.data ?? {};

            state.importProgress = {
                status: data.status ?? "unknown",
                total_rows: data.total_rows ?? 0,
                processed_rows: data.processed_rows ?? 0,
                imported_products: data.imported_products ?? 0,
                imported_variants: data.imported_variants ?? 0,
                imported_categories: data.imported_categories ?? 0,
                skipped_rows: data.skipped_rows ?? 0,
                errors: data.errors ?? [],
                warnings: data.warnings ?? [],
                percent: data.progress_percent ?? (data.total_rows > 0
                    ? Math.round((data.processed_rows / data.total_rows) * 100)
                    : 0),
            };

            // Stop polling when job is complete or failed
            if (["completed", "failed", "cancelled"].includes(data.status)) {
                stopPolling();
            }
        } catch (error) {
            state.error = error?.message ?? "Failed to get progress";
            stopPolling();
        }
    };

    /**
     * Start polling for import progress.
     */
    const startImportPolling = () => {
        stopPolling();
        state.importPolling = true;
        pollImportProgress();
        pollIntervalRef.value = setInterval(pollImportProgress, 2000);
    };

    /**
     * Cancel an import job.
     */
    const cancelImport = async () => {
        if (!importCancelEndpoint || !state.importJobId) {
            return;
        }

        try {
            await api.post(importCancelEndpoint, { job_id: state.importJobId });
            stopPolling();
            state.importProgress = {
                ...state.importProgress,
                status: "cancelled",
            };
        } catch (error) {
            state.error = error?.message ?? "Failed to cancel import";
        }
    };

    /**
     * Reset import state for a new import.
     */
    const resetImport = () => {
        stopPolling();
        state.importFile = null;
        state.importFileId = "";
        state.importFilename = "";
        state.importFileSize = 0;
        state.importRowCount = 0;
        state.importHeaders = [];
        state.importPreview = [];
        state.importDetectedPlatform = null;
        state.importSelectedPlatform = "";
        state.importMapping = {};
        state.importJobId = null;
        state.importProgress = null;
        state.error = "";
    };

    /**
     * Start an export job.
     *
     * @param {Object} options - Export options
     */
    const startExport = async (options = {}) => {
        if (!exportStartEndpoint) {
            return null;
        }

        state.exportStarting = true;
        state.error = "";

        try {
            const payload = await api.post(exportStartEndpoint, {
                platform: state.exportPlatform,
                options,
            });

            const data = payload.data ?? {};

            state.exportJobId = data.job_id ?? null;
            state.exportProgress = {
                status: data.status ?? "pending",
                total_rows: data.total_rows ?? 0,
                processed_rows: 0,
                percent: 0,
            };

            // Start polling for progress
            startExportPolling();

            return data;
        } catch (error) {
            state.error = error?.message ?? "Failed to start export";
            return null;
        } finally {
            state.exportStarting = false;
        }
    };

    /**
     * Poll for export job progress.
     */
    const pollExportProgress = async () => {
        if (!exportProgressEndpoint || !state.exportJobId) {
            return;
        }

        try {
            const url = `${exportProgressEndpoint}&job_id=${state.exportJobId}`;
            const payload = await api.get(url);
            const data = payload.data ?? {};

            state.exportProgress = {
                status: data.status ?? "unknown",
                total_rows: data.total_rows ?? 0,
                processed_rows: data.processed_rows ?? 0,
                percent: data.total_rows > 0
                    ? Math.round((data.processed_rows / data.total_rows) * 100)
                    : 0,
                file_path: data.file_path ?? "",
            };

            // Stop polling when job is complete or failed
            if (["completed", "failed", "cancelled"].includes(data.status)) {
                stopPolling();
            }
        } catch (error) {
            state.error = error?.message ?? "Failed to get progress";
            stopPolling();
        }
    };

    /**
     * Start polling for export progress.
     */
    const startExportPolling = () => {
        stopPolling();
        state.exportPolling = true;
        pollExportProgress();
        pollIntervalRef.value = setInterval(pollExportProgress, 2000);
    };

    /**
     * Cancel an export job.
     */
    const cancelExport = async () => {
        if (!exportCancelEndpoint || !state.exportJobId) {
            return;
        }

        try {
            await api.post(exportCancelEndpoint, { job_id: state.exportJobId });
            stopPolling();
            state.exportProgress = {
                ...state.exportProgress,
                status: "cancelled",
            };
        } catch (error) {
            state.error = error?.message ?? "Failed to cancel export";
        }
    };

    /**
     * Download export file.
     */
    const downloadExport = () => {
        if (!exportDownloadEndpoint || !state.exportJobId) {
            return;
        }

        // Trigger download by opening URL
        const url = `${exportDownloadEndpoint}&job_id=${state.exportJobId}`;

        if (typeof window !== "undefined") {
            window.location.href = url;
        }
    };

    /**
     * Reset export state for a new export.
     */
    const resetExport = () => {
        stopPolling();
        state.exportPlatform = "native";
        state.exportJobId = null;
        state.exportProgress = null;
        state.error = "";
    };

    /**
     * Load import job history.
     */
    const loadImportJobs = async () => {
        if (!importJobsEndpoint) {
            return;
        }

        try {
            const url = `${importJobsEndpoint}&type=import&limit=${state.jobsPagination.limit}`;
            const payload = await api.get(url);
            const data = payload.data ?? {};

            state.importJobs = data.items ?? [];
        } catch (error) {
            // Job history is optional
            state.importJobs = [];
        }
    };

    /**
     * Load export job history.
     */
    const loadExportJobs = async () => {
        if (!exportJobsEndpoint) {
            return;
        }

        try {
            const url = `${exportJobsEndpoint}&type=export&limit=${state.jobsPagination.limit}`;
            const payload = await api.get(url);
            const data = payload.data ?? {};

            state.exportJobs = data.items ?? [];
        } catch (error) {
            // Job history is optional
            state.exportJobs = [];
        }
    };

    /**
     * Refresh all data.
     */
    const refresh = async () => {
        await Promise.all([
            loadPlatforms(),
            loadSamples(),
            loadImportJobs(),
            loadExportJobs(),
        ]);
    };

    onMounted(() => {
        if (autoload) {
            refresh();
        }
    });

    onUnmounted(() => {
        stopPolling();
    });

    return {
        state,
        loadPlatforms,
        loadSamples,
        uploadFile,
        detectPlatform,
        startImport,
        cancelImport,
        resetImport,
        startExport,
        cancelExport,
        downloadExport,
        resetExport,
        loadImportJobs,
        loadExportJobs,
        refresh,
        stopPolling,
    };
}

export default useImportExport;
