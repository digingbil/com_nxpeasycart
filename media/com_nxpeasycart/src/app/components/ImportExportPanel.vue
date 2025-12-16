<template>
    <section class="nxp-ec-admin-panel nxp-ec-admin-panel--import-export">
        <header class="nxp-ec-admin-panel__header">
            <div>
                <h2 class="nxp-ec-admin-panel__title">
                    {{
                        __(
                            "COM_NXPEASYCART_IMPORT_EXPORT_TITLE",
                            "Import / Export",
                            [],
                            "importExportTitle"
                        )
                    }}
                </h2>
                <p class="nxp-ec-admin-panel__lead">
                    {{
                        __(
                            "COM_NXPEASYCART_IMPORT_EXPORT_LEAD",
                            "Migrate products from other platforms or backup your catalogue.",
                            [],
                            "importExportLead"
                        )
                    }}
                </p>
            </div>
            <div class="nxp-ec-admin-panel__actions">
                <button
                    class="nxp-ec-btn nxp-ec-btn--icon"
                    type="button"
                    @click="refresh"
                    :disabled="state.loading"
                    :title="__('COM_NXPEASYCART_REFRESH', 'Refresh', [], 'refresh')"
                    :aria-label="__('COM_NXPEASYCART_REFRESH', 'Refresh', [], 'refresh')"
                >
                    <i class="fa-solid fa-rotate"></i>
                    <span class="nxp-ec-sr-only">
                        {{ __("COM_NXPEASYCART_REFRESH", "Refresh", [], "refresh") }}
                    </span>
                </button>
            </div>
        </header>

        <nav class="nxp-ec-tabs" role="tablist">
            <button
                role="tab"
                :aria-selected="activeTab === 'import'"
                :class="['nxp-ec-tabs__tab', { 'is-active': activeTab === 'import' }]"
                @click="activeTab = 'import'"
            >
                <i class="fa-solid fa-file-import" aria-hidden="true"></i>
                {{ __("COM_NXPEASYCART_IMPORT", "Import", [], "import") }}
            </button>
            <button
                role="tab"
                :aria-selected="activeTab === 'export'"
                :class="['nxp-ec-tabs__tab', { 'is-active': activeTab === 'export' }]"
                @click="activeTab = 'export'"
            >
                <i class="fa-solid fa-file-export" aria-hidden="true"></i>
                {{ __("COM_NXPEASYCART_EXPORT", "Export", [], "export") }}
            </button>
        </nav>

        <div
            v-if="state.error"
            class="nxp-ec-admin-alert nxp-ec-admin-alert--error"
        >
            {{ state.error }}
        </div>

        <div v-if="state.loading" class="nxp-ec-admin-panel__loading">
            {{ __("COM_NXPEASYCART_LOADING", "Loading...", [], "loading") }}
        </div>

        <!-- Import Tab -->
        <div
            v-if="!state.loading && activeTab === 'import'"
            class="nxp-ec-admin-panel__body"
            role="tabpanel"
        >
            <!-- Backup notice -->
            <div v-if="!state.importFileId && !state.importJobId" class="nxp-ec-admin-alert nxp-ec-admin-alert--info nxp-ec-import-backup-notice">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <div>
                    <strong>{{ __("COM_NXPEASYCART_IMPORT_BACKUP_TITLE", "Backup recommended", [], "importBackupTitle") }}</strong>
                    <p>{{ __("COM_NXPEASYCART_IMPORT_BACKUP_TEXT", "Before importing, we recommend exporting your current catalogue as a backup. Switch to the Export tab to create a backup first.", [], "importBackupText") }}</p>
                    <button
                        class="nxp-ec-btn nxp-ec-btn--sm"
                        type="button"
                        @click="activeTab = 'export'"
                    >
                        <i class="fa-solid fa-file-export" aria-hidden="true"></i>
                        {{ __("COM_NXPEASYCART_IMPORT_GO_TO_EXPORT", "Go to Export", [], "importGoToExport") }}
                    </button>
                </div>
            </div>

            <!-- Step 1: Upload -->
            <div v-if="!state.importFileId" class="nxp-ec-import-upload">
                <div class="nxp-ec-import-upload__dropzone" @dragover.prevent @drop.prevent="onFileDrop">
                    <i class="fa-solid fa-cloud-arrow-up nxp-ec-import-upload__icon" aria-hidden="true"></i>
                    <p class="nxp-ec-import-upload__title">
                        {{ __("COM_NXPEASYCART_IMPORT_DROP_FILE", "Drop CSV file here", [], "importDropFile") }}
                    </p>
                    <p class="nxp-ec-import-upload__subtitle">
                        {{ __("COM_NXPEASYCART_IMPORT_OR", "or", [], "importOr") }}
                    </p>
                    <label class="nxp-ec-btn nxp-ec-btn--primary">
                        <i class="fa-solid fa-folder-open" aria-hidden="true"></i>
                        {{ __("COM_NXPEASYCART_IMPORT_BROWSE", "Browse files", [], "importBrowse") }}
                        <input
                            type="file"
                            accept=".csv,text/csv,application/csv"
                            class="nxp-ec-sr-only"
                            @change="onFileSelect"
                            :disabled="state.importUploading"
                        />
                    </label>
                    <p class="nxp-ec-import-upload__help">
                        {{ __("COM_NXPEASYCART_IMPORT_FILE_HELP", "Supported: CSV files up to 10MB", [], "importFileHelp") }}
                    </p>
                </div>

            </div>

            <!-- Step 2: Configure -->
            <div v-else-if="state.importFileId && !state.importJobId" class="nxp-ec-import-configure">
                <div class="nxp-ec-import-file-info">
                    <div class="nxp-ec-import-file-info__icon">
                        <i class="fa-solid fa-file-csv" aria-hidden="true"></i>
                    </div>
                    <div class="nxp-ec-import-file-info__details">
                        <strong>{{ state.importFilename }}</strong>
                        <span>{{ formatFileSize(state.importFileSize) }}</span>
                        <span>{{ state.importRowCount }} {{ __("COM_NXPEASYCART_IMPORT_ROWS", "rows", [], "importRows") }}</span>
                    </div>
                    <button
                        class="nxp-ec-btn nxp-ec-btn--link nxp-ec-btn--icon"
                        type="button"
                        @click="resetImport"
                        :title="__('COM_NXPEASYCART_IMPORT_CHANGE_FILE', 'Change file', [], 'importChangeFile')"
                    >
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="nxp-ec-form-field">
                    <label class="nxp-ec-form-label" for="import-platform">
                        {{ __("COM_NXPEASYCART_IMPORT_PLATFORM", "Source platform", [], "importPlatform") }}
                    </label>
                    <select
                        id="import-platform"
                        class="nxp-ec-form-select"
                        v-model="state.importSelectedPlatform"
                    >
                        <option value="">
                            {{ __("COM_NXPEASYCART_IMPORT_SELECT_PLATFORM", "Select platform...", [], "importSelectPlatform") }}
                        </option>
                        <option
                            v-for="platform in state.platforms"
                            :key="platform.id"
                            :value="platform.id"
                        >
                            {{ platform.name }}
                            {{ platform.id === state.importDetectedPlatform ? '(detected)' : '' }}
                        </option>
                    </select>
                    <p v-if="state.importDetectedPlatform" class="nxp-ec-form-help">
                        <i class="fa-solid fa-check-circle" aria-hidden="true"></i>
                        {{ __("COM_NXPEASYCART_IMPORT_DETECTED", "Auto-detected from CSV headers", [], "importDetected") }}
                    </p>
                </div>

                <!-- Preview -->
                <div v-if="state.importPreview.length" class="nxp-ec-import-preview">
                    <h4>{{ __("COM_NXPEASYCART_IMPORT_PREVIEW", "Preview", [], "importPreview") }}</h4>
                    <div class="nxp-ec-import-preview__table-wrapper">
                        <table class="nxp-ec-admin-table nxp-ec-admin-table--sm">
                            <thead>
                                <tr>
                                    <th v-for="header in state.importHeaders" :key="header" scope="col">
                                        {{ header }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, idx) in state.importPreview.slice(0, 3)" :key="idx">
                                    <td v-for="header in state.importHeaders" :key="header">
                                        {{ truncate(row[header], 30) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="nxp-ec-import-actions">
                    <button
                        class="nxp-ec-btn"
                        type="button"
                        @click="resetImport"
                    >
                        {{ __("COM_NXPEASYCART_CANCEL", "Cancel", [], "cancel") }}
                    </button>
                    <button
                        class="nxp-ec-btn nxp-ec-btn--primary"
                        type="button"
                        @click="startImport"
                        :disabled="!state.importSelectedPlatform || state.importStarting"
                    >
                        <i class="fa-solid fa-play" aria-hidden="true"></i>
                        {{
                            state.importStarting
                                ? __("COM_NXPEASYCART_IMPORT_STARTING", "Starting...", [], "importStarting")
                                : __("COM_NXPEASYCART_IMPORT_START", "Start import", [], "importStart")
                        }}
                    </button>
                </div>
            </div>

            <!-- Step 3: Progress -->
            <div v-else-if="state.importJobId && state.importProgress" class="nxp-ec-import-progress">
                <div class="nxp-ec-progress-card">
                    <div class="nxp-ec-progress-card__header">
                        <h4>
                            <i
                                :class="progressStatusIcon(state.importProgress.status)"
                                aria-hidden="true"
                            ></i>
                            {{ progressStatusLabel(state.importProgress.status) }}
                        </h4>
                    </div>

                    <div class="nxp-ec-progress-bar">
                        <div
                            class="nxp-ec-progress-bar__fill"
                            :style="{ width: state.importProgress.percent + '%' }"
                            :class="progressBarClass(state.importProgress.status)"
                        ></div>
                    </div>

                    <div class="nxp-ec-progress-card__stats">
                        <div class="nxp-ec-progress-stat">
                            <span class="nxp-ec-progress-stat__value">{{ state.importProgress.processed_rows }}</span>
                            <span class="nxp-ec-progress-stat__label">
                                / {{ state.importProgress.total_rows }} {{ __("COM_NXPEASYCART_IMPORT_ROWS", "rows", [], "importRows") }}
                            </span>
                        </div>
                        <div class="nxp-ec-progress-stat nxp-ec-progress-stat--success">
                            <span class="nxp-ec-progress-stat__value">{{ state.importProgress.imported_products }}</span>
                            <span class="nxp-ec-progress-stat__label">{{ __("COM_NXPEASYCART_IMPORT_PRODUCTS", "products", [], "importProducts") }}</span>
                        </div>
                        <div v-if="state.importProgress.imported_variants" class="nxp-ec-progress-stat nxp-ec-progress-stat--info">
                            <span class="nxp-ec-progress-stat__value">{{ state.importProgress.imported_variants }}</span>
                            <span class="nxp-ec-progress-stat__label">{{ __("COM_NXPEASYCART_IMPORT_VARIANTS", "variants", [], "importVariants") }}</span>
                        </div>
                        <div v-if="state.importProgress.skipped_rows" class="nxp-ec-progress-stat nxp-ec-progress-stat--warning">
                            <span class="nxp-ec-progress-stat__value">{{ state.importProgress.skipped_rows }}</span>
                            <span class="nxp-ec-progress-stat__label">{{ __("COM_NXPEASYCART_IMPORT_SKIPPED", "skipped", [], "importSkipped") }}</span>
                        </div>
                        <div v-if="state.importProgress.errors?.length" class="nxp-ec-progress-stat nxp-ec-progress-stat--error">
                            <span class="nxp-ec-progress-stat__value">{{ state.importProgress.errors.length }}</span>
                            <span class="nxp-ec-progress-stat__label">{{ __("COM_NXPEASYCART_IMPORT_ERRORS", "errors", [], "importErrors") }}</span>
                        </div>
                    </div>

                    <!-- Error details -->
                    <div v-if="state.importProgress.errors?.length" class="nxp-ec-progress-errors">
                        <details>
                            <summary>{{ __("COM_NXPEASYCART_IMPORT_VIEW_ERRORS", "View errors", [], "importViewErrors") }}</summary>
                            <ul>
                                <li v-for="(error, idx) in state.importProgress.errors.slice(0, 10)" :key="idx">
                                    {{ error }}
                                </li>
                            </ul>
                        </details>
                    </div>

                    <div class="nxp-ec-progress-card__actions">
                        <button
                            v-if="isJobRunning(state.importProgress.status)"
                            class="nxp-ec-btn nxp-ec-btn--danger"
                            type="button"
                            @click="cancelImport"
                        >
                            <i class="fa-solid fa-stop" aria-hidden="true"></i>
                            {{ __("COM_NXPEASYCART_IMPORT_CANCEL", "Cancel import", [], "importCancel") }}
                        </button>
                        <button
                            v-else
                            class="nxp-ec-btn nxp-ec-btn--primary"
                            type="button"
                            @click="resetImport"
                        >
                            <i class="fa-solid fa-plus" aria-hidden="true"></i>
                            {{ __("COM_NXPEASYCART_IMPORT_NEW", "New import", [], "importNew") }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Export Tab -->
        <div
            v-if="!state.loading && activeTab === 'export'"
            class="nxp-ec-admin-panel__body"
            role="tabpanel"
        >
            <!-- Export configuration -->
            <div v-if="!state.exportJobId" class="nxp-ec-export-configure">
                <div class="nxp-ec-form-field">
                    <label class="nxp-ec-form-label" for="export-platform">
                        {{ __("COM_NXPEASYCART_EXPORT_FORMAT", "Export format", [], "exportFormat") }}
                    </label>
                    <select
                        id="export-platform"
                        class="nxp-ec-form-select"
                        v-model="state.exportPlatform"
                    >
                        <option
                            v-for="platform in state.platforms"
                            :key="platform.id"
                            :value="platform.id"
                        >
                            {{ platform.name }}
                        </option>
                    </select>
                    <p class="nxp-ec-form-help">
                        {{ __("COM_NXPEASYCART_EXPORT_FORMAT_HELP", "Choose Native format for backups or another platform format for migration.", [], "exportFormatHelp") }}
                    </p>
                </div>

                <div class="nxp-ec-export-actions">
                    <button
                        class="nxp-ec-btn nxp-ec-btn--primary"
                        type="button"
                        @click="startExport"
                        :disabled="state.exportStarting"
                    >
                        <i class="fa-solid fa-file-export" aria-hidden="true"></i>
                        {{
                            state.exportStarting
                                ? __("COM_NXPEASYCART_EXPORT_STARTING", "Starting...", [], "exportStarting")
                                : __("COM_NXPEASYCART_EXPORT_START", "Start export", [], "exportStart")
                        }}
                    </button>
                </div>
            </div>

            <!-- Export progress -->
            <div v-else-if="state.exportProgress" class="nxp-ec-export-progress">
                <div class="nxp-ec-progress-card">
                    <div class="nxp-ec-progress-card__header">
                        <h4>
                            <i
                                :class="progressStatusIcon(state.exportProgress.status)"
                                aria-hidden="true"
                            ></i>
                            {{ progressStatusLabel(state.exportProgress.status) }}
                        </h4>
                    </div>

                    <div class="nxp-ec-progress-bar">
                        <div
                            class="nxp-ec-progress-bar__fill"
                            :style="{ width: state.exportProgress.percent + '%' }"
                            :class="progressBarClass(state.exportProgress.status)"
                        ></div>
                    </div>

                    <div class="nxp-ec-progress-card__stats">
                        <div class="nxp-ec-progress-stat">
                            <span class="nxp-ec-progress-stat__value">{{ state.exportProgress.processed_rows }}</span>
                            <span class="nxp-ec-progress-stat__label">
                                / {{ state.exportProgress.total_rows }} {{ __("COM_NXPEASYCART_EXPORT_PRODUCTS", "products", [], "exportProducts") }}
                            </span>
                        </div>
                    </div>

                    <div class="nxp-ec-progress-card__actions">
                        <button
                            v-if="isJobRunning(state.exportProgress.status)"
                            class="nxp-ec-btn nxp-ec-btn--danger"
                            type="button"
                            @click="cancelExport"
                        >
                            <i class="fa-solid fa-stop" aria-hidden="true"></i>
                            {{ __("COM_NXPEASYCART_EXPORT_CANCEL", "Cancel export", [], "exportCancel") }}
                        </button>
                        <template v-else>
                            <button
                                v-if="state.exportProgress.status === 'completed'"
                                class="nxp-ec-btn nxp-ec-btn--primary"
                                type="button"
                                @click="downloadExport"
                            >
                                <i class="fa-solid fa-download" aria-hidden="true"></i>
                                {{ __("COM_NXPEASYCART_EXPORT_DOWNLOAD", "Download CSV", [], "exportDownload") }}
                            </button>
                            <button
                                class="nxp-ec-btn"
                                type="button"
                                @click="resetExport"
                            >
                                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                                {{ __("COM_NXPEASYCART_EXPORT_NEW", "New export", [], "exportNew") }}
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { ref } from "vue";

const props = defineProps({
    state: {
        type: Object,
        required: true,
    },
    translate: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits([
    "refresh",
    "upload-file",
    "start-import",
    "cancel-import",
    "reset-import",
    "start-export",
    "cancel-export",
    "download-export",
    "reset-export",
]);

const __ = props.translate;

const activeTab = ref("import");

const refresh = () => emit("refresh");

const onFileSelect = (event) => {
    const file = event.target.files?.[0];
    if (file) {
        emit("upload-file", file);
    }
};

const onFileDrop = (event) => {
    const file = event.dataTransfer?.files?.[0];
    if (file) {
        emit("upload-file", file);
    }
};

const startImport = () => emit("start-import");
const cancelImport = () => emit("cancel-import");
const resetImport = () => emit("reset-import");

const startExport = () => emit("start-export");
const cancelExport = () => emit("cancel-export");
const downloadExport = () => emit("download-export");
const resetExport = () => emit("reset-export");

const formatFileSize = (bytes) => {
    if (!bytes) return "0 B";
    const units = ["B", "KB", "MB", "GB"];
    let i = 0;
    while (bytes >= 1024 && i < units.length - 1) {
        bytes /= 1024;
        i++;
    }
    return `${bytes.toFixed(i > 0 ? 1 : 0)} ${units[i]}`;
};

const truncate = (str, length) => {
    if (!str) return "";
    if (str.length <= length) return str;
    return str.substring(0, length) + "...";
};

const isJobRunning = (status) => {
    return ["pending", "processing", "running"].includes(status);
};

const progressStatusIcon = (status) => {
    switch (status) {
        case "completed":
            return "fa-solid fa-check-circle nxp-ec-text-success";
        case "failed":
            return "fa-solid fa-times-circle nxp-ec-text-danger";
        case "cancelled":
            return "fa-solid fa-ban nxp-ec-text-warning";
        default:
            return "fa-solid fa-spinner fa-spin";
    }
};

const progressStatusLabel = (status) => {
    switch (status) {
        case "completed":
            return __("COM_NXPEASYCART_STATUS_COMPLETED", "Completed", [], "statusCompleted");
        case "failed":
            return __("COM_NXPEASYCART_STATUS_FAILED", "Failed", [], "statusFailed");
        case "cancelled":
            return __("COM_NXPEASYCART_STATUS_CANCELLED", "Cancelled", [], "statusCancelled");
        case "processing":
        case "running":
            return __("COM_NXPEASYCART_STATUS_PROCESSING", "Processing...", [], "statusProcessing");
        default:
            return __("COM_NXPEASYCART_STATUS_PENDING", "Pending...", [], "statusPending");
    }
};

const progressBarClass = (status) => {
    switch (status) {
        case "completed":
            return "nxp-ec-progress-bar__fill--success";
        case "failed":
            return "nxp-ec-progress-bar__fill--danger";
        case "cancelled":
            return "nxp-ec-progress-bar__fill--warning";
        default:
            return "";
    }
};
</script>

<style scoped>
.nxp-ec-tabs {
    display: flex;
    gap: 0;
    border-bottom: 1px solid var(--nxp-ec-border, #dee2e6);
    margin-bottom: 1.5rem;
}

.nxp-ec-tabs__tab {
    padding: 0.75rem 1.5rem;
    border: none;
    background: transparent;
    cursor: pointer;
    font-size: 0.9375rem;
    color: var(--nxp-ec-text-muted, #6c757d);
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
    transition: color 0.2s, border-color 0.2s;
}

.nxp-ec-tabs__tab:hover {
    color: var(--nxp-ec-text, #212529);
}

.nxp-ec-tabs__tab.is-active {
    color: var(--nxp-ec-primary, #0d6efd);
    border-bottom-color: var(--nxp-ec-primary, #0d6efd);
}

.nxp-ec-tabs__tab i {
    margin-right: 0.5rem;
}

/* Upload dropzone */
.nxp-ec-import-upload__dropzone {
    border: 2px dashed var(--nxp-ec-border, #dee2e6);
    border-radius: 0.5rem;
    padding: 3rem 2rem;
    text-align: center;
    background: var(--nxp-ec-bg-light, #f8f9fa);
    transition: border-color 0.2s, background-color 0.2s;
}

.nxp-ec-import-upload__dropzone:hover {
    border-color: var(--nxp-ec-primary, #0d6efd);
    background: var(--nxp-ec-bg, #fff);
}

.nxp-ec-import-upload__icon {
    font-size: 3rem;
    color: var(--nxp-ec-text-muted, #6c757d);
    margin-bottom: 1rem;
}

.nxp-ec-import-upload__title {
    font-size: 1.125rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.nxp-ec-import-upload__subtitle {
    color: var(--nxp-ec-text-muted, #6c757d);
    margin-bottom: 1rem;
}

.nxp-ec-import-upload__help {
    font-size: 0.875rem;
    color: var(--nxp-ec-text-muted, #6c757d);
    margin-top: 1rem;
}

/* Samples */
.nxp-ec-import-samples {
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--nxp-ec-border, #dee2e6);
}

.nxp-ec-import-samples h4 {
    font-size: 0.875rem;
    font-weight: 600;
    margin-bottom: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.025em;
    color: var(--nxp-ec-text-muted, #6c757d);
}

.nxp-ec-import-samples__list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem 1.5rem;
}

.nxp-ec-import-samples__list li {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.nxp-ec-import-samples__size {
    font-size: 0.8125rem;
    color: var(--nxp-ec-text-muted, #6c757d);
}

/* File info */
.nxp-ec-import-file-info {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--nxp-ec-bg-light, #f8f9fa);
    border-radius: 0.5rem;
    margin-bottom: 1.5rem;
}

.nxp-ec-import-file-info__icon {
    font-size: 2rem;
    color: var(--nxp-ec-primary, #0d6efd);
}

.nxp-ec-import-file-info__details {
    flex: 1;
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem 1rem;
}

.nxp-ec-import-file-info__details strong {
    width: 100%;
}

.nxp-ec-import-file-info__details span {
    font-size: 0.875rem;
    color: var(--nxp-ec-text-muted, #6c757d);
}

/* Preview */
.nxp-ec-import-preview {
    margin: 1.5rem 0;
}

.nxp-ec-import-preview h4 {
    font-size: 0.875rem;
    font-weight: 600;
    margin-bottom: 0.75rem;
}

.nxp-ec-import-preview__table-wrapper {
    overflow-x: auto;
    border: 1px solid var(--nxp-ec-border, #dee2e6);
    border-radius: 0.375rem;
}

.nxp-ec-admin-table--sm {
    font-size: 0.8125rem;
}

.nxp-ec-admin-table--sm th,
.nxp-ec-admin-table--sm td {
    padding: 0.5rem;
    white-space: nowrap;
}

/* Actions */
.nxp-ec-import-actions,
.nxp-ec-export-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--nxp-ec-border, #dee2e6);
}

/* Progress */
.nxp-ec-progress-card {
    background: var(--nxp-ec-bg-light, #f8f9fa);
    border-radius: 0.5rem;
    padding: 1.5rem;
}

.nxp-ec-progress-card__header {
    margin-bottom: 1rem;
}

.nxp-ec-progress-card__header h4 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
    font-size: 1.125rem;
}

.nxp-ec-progress-bar {
    height: 0.75rem;
    background: var(--nxp-ec-border, #dee2e6);
    border-radius: 0.375rem;
    overflow: hidden;
    margin-bottom: 1rem;
}

.nxp-ec-progress-bar__fill {
    height: 100%;
    background: var(--nxp-ec-primary, #0d6efd);
    transition: width 0.3s ease;
}

.nxp-ec-progress-bar__fill--success {
    background: var(--nxp-ec-success, #198754);
}

.nxp-ec-progress-bar__fill--danger {
    background: var(--nxp-ec-danger, #dc3545);
}

.nxp-ec-progress-bar__fill--warning {
    background: var(--nxp-ec-warning, #ffc107);
}

.nxp-ec-progress-card__stats {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem 2rem;
    margin-bottom: 1rem;
}

.nxp-ec-progress-stat {
    display: flex;
    align-items: baseline;
    gap: 0.25rem;
}

.nxp-ec-progress-stat__value {
    font-size: 1.5rem;
    font-weight: 700;
}

.nxp-ec-progress-stat__label {
    font-size: 0.875rem;
    color: var(--nxp-ec-text-muted, #6c757d);
}

.nxp-ec-progress-stat--success .nxp-ec-progress-stat__value {
    color: var(--nxp-ec-success, #198754);
}

.nxp-ec-progress-stat--warning .nxp-ec-progress-stat__value {
    color: var(--nxp-ec-warning, #ffc107);
}

.nxp-ec-progress-stat--error .nxp-ec-progress-stat__value {
    color: var(--nxp-ec-danger, #dc3545);
}

.nxp-ec-progress-errors {
    margin-bottom: 1rem;
    padding: 0.75rem;
    background: var(--nxp-ec-bg, #fff);
    border: 1px solid var(--nxp-ec-border, #dee2e6);
    border-radius: 0.375rem;
}

.nxp-ec-progress-errors summary {
    cursor: pointer;
    font-weight: 500;
}

.nxp-ec-progress-errors ul {
    margin: 0.75rem 0 0;
    padding-left: 1.25rem;
    font-size: 0.875rem;
    color: var(--nxp-ec-danger, #dc3545);
}

.nxp-ec-progress-card__actions {
    display: flex;
    gap: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--nxp-ec-border, #dee2e6);
}

/* Export configure */
.nxp-ec-export-configure {
    max-width: 32rem;
}

/* Text utilities */
.nxp-ec-text-success {
    color: var(--nxp-ec-success, #198754);
}

.nxp-ec-text-danger {
    color: var(--nxp-ec-danger, #dc3545);
}

.nxp-ec-text-warning {
    color: var(--nxp-ec-warning, #ffc107);
}

/* Backup notice */
.nxp-ec-import-backup-notice {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
    margin-bottom: 1.5rem;
}

.nxp-ec-import-backup-notice > i {
    font-size: 1.25rem;
    flex-shrink: 0;
    margin-top: 0.125rem;
}

.nxp-ec-import-backup-notice p {
    margin: 0.25rem 0 0.75rem;
    font-size: 0.875rem;
}

.nxp-ec-admin-alert--info {
    background: var(--nxp-ec-info-bg, #cff4fc);
    border: 1px solid var(--nxp-ec-info-border, #9eeaf9);
    color: var(--nxp-ec-info-text, #055160);
}
</style>
