# Refactoring Plan v0.3.2

**Date**: December 17, 2025
**Target Version**: v0.3.2
**Priority**: High (Security + Maintainability)

---

## Executive Summary

This refactoring addresses three critical technical debt areas identified in the codebase:

1. **Security**: Path traversal vulnerability in digital file storage (low immediate risk, high brittleness)
2. **Maintainability**: Monolithic admin components (SettingsPanel.vue: 3,152 lines; ProductEditor.vue: 3,169 lines)
3. **Consistency**: CSRF validation patterns vary across storefront endpoints

All issues are manageable but represent compounding risks as the codebase grows. This plan prioritizes incremental, testable changes that preserve existing functionality while improving code quality.

---

## Prerequisites

Before starting this refactoring, ensure the following dependencies are in place:

### 1. Vue Testing Infrastructure (Required for Phase 3)

The project currently lacks Vue testing tools. Install them before starting component refactoring:

```bash
npm install -D vitest @vue/test-utils jsdom @vitest/coverage-v8
```

Add to `package.json` scripts:

```json
{
  "scripts": {
    "test:vue": "vitest run",
    "test:vue:watch": "vitest",
    "test:vue:coverage": "vitest run --coverage"
  }
}
```

Create `vitest.config.js` in project root:

```javascript
import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [vue()],
    test: {
        environment: 'jsdom',
        globals: true,
        include: ['tests/Unit/Vue/**/*.test.js'],
    },
});
```

### 2. ESLint Configuration (Recommended)

Add ESLint with Vue plugin to enforce `innerHTML` ban:

```bash
npm install -D eslint eslint-plugin-vue
```

Create `.eslintrc.js`:

```javascript
module.exports = {
    extends: ['plugin:vue/vue3-recommended'],
    rules: {
        'vue/no-v-html': 'error', // Ban v-html directive
    },
};
```

Add to `package.json` scripts:

```json
{
  "scripts": {
    "lint:vue": "eslint media/com_nxpeasycart/src/**/*.vue"
  }
}
```

### 3. Directory Structure Preparation

These directories will be created during implementation:

```
components/com_nxpeasycart/src/Trait/           # New (for CsrfValidation)
media/com_nxpeasycart/src/app/components/settings/       # New
media/com_nxpeasycart/src/app/components/product-editor/ # New
tests/Unit/Vue/                                          # New
tests/Unit/Administrator/Helper/                         # Exists
tests/Unit/Site/Trait/                                   # New
```

---

## Issue 1: Storage Path Normalization Security

### Current State

**File**: `administrator/components/com_nxpeasycart/src/Service/DigitalFileService.php`
**Lines**: 914-972 (`getStorageRelativePath()`, `resolveAbsolutePath()`)

**Problem**:

- Uses simplistic `str_replace(['\\', '..'], ['/', ''], $storagePath)` to strip path traversal sequences
- Concatenates sanitized input with `JPATH_ROOT` without realpath() canonicalization
- No containment check to verify final path stays within allowed boundaries
- Brittle: fails against encoded traversal (`%2e%2e`), multiple slashes, or symlinks

**Risk Assessment**:

- **Immediate threat**: Low (path is admin-controlled via Settings UI)
- **Long-term risk**: High (easy to misuse; hard to audit; maintenance burden)
- **Attack vectors**: Admin compromise, future API exposure, plugin/extension interaction

### Solution: Canonicalization + Containment Check

#### Implementation Steps

1. **Create PathSecurityHelper** (new file: `administrator/components/com_nxpeasycart/src/Helper/PathSecurityHelper.php`)

    ```php
    namespace Joomla\Component\Nxpeasycart\Administrator\Helper;

    class PathSecurityHelper
    {
        /**
         * Resolve and validate a path is contained within an allowed base.
         *
         * @param string $base Allowed base directory (e.g. JPATH_ROOT)
         * @param string $relative User-supplied relative path
         * @return string|null Absolute canonical path, or null if invalid/escaped
         */
        public static function resolveContainedPath(string $base, string $relative): ?string
        {
            // 1. Normalize base (resolve symlinks)
            $baseReal = realpath($base);
            if ($baseReal === false || !is_dir($baseReal)) {
                return null; // Base doesn't exist
            }

            // 2. Strip dangerous patterns + normalize slashes
            $relative = trim($relative);
            $relative = preg_replace('/[\\\\]+/', '/', $relative);
            $relative = ltrim($relative, '/');

            // 3. Build target path
            $target = $baseReal . DIRECTORY_SEPARATOR . $relative;

            // 4. Canonicalize (this resolves '..' and symlinks)
            //    If path doesn't exist, realpath() returns false; that's OK for new dirs
            //    so we'll validate parent instead
            if (file_exists($target)) {
                $targetReal = realpath($target);
            } else {
                // For non-existent paths, validate parent
                $parent = dirname($target);
                if (!file_exists($parent)) {
                    // Parent doesn't exist; can't safely validate
                    // (DigitalFileService::ensureDirectory will create it later)
                    // So we manually reconstruct a "would-be" real path
                    $parentReal = realpath($parent);
                    if ($parentReal === false) {
                        // Build expected parent chain
                        $parts = explode(DIRECTORY_SEPARATOR, $relative);
                        $current = $baseReal;
                        foreach ($parts as $part) {
                            if ($part === '' || $part === '.') {
                                continue;
                            }
                            if ($part === '..') {
                                return null; // Reject explicit traversal
                            }
                            $current .= DIRECTORY_SEPARATOR . $part;
                        }
                        $targetReal = $current;
                    } else {
                        $targetReal = $parentReal . DIRECTORY_SEPARATOR . basename($target);
                    }
                } else {
                    $parentReal = realpath($parent);
                    $targetReal = $parentReal . DIRECTORY_SEPARATOR . basename($target);
                }
            }

            if ($targetReal === false) {
                return null;
            }

            // 5. Containment check: ensure resolved path starts with base
            $basePrefix = rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $targetPrefix = substr($targetReal . DIRECTORY_SEPARATOR, 0, strlen($basePrefix));

            if ($targetPrefix !== $basePrefix) {
                return null; // Path escaped!
            }

            return $targetReal;
        }
    }
    ```

    **Important: URL-encoded traversal handling**

    The helper assumes input has already been URL-decoded. Controllers MUST decode user input before calling `resolveContainedPath()`:

    ```php
    // In SettingsController or DigitalFileService
    $path = urldecode($input->getString('digital_storage_path', ''));
    $resolved = PathSecurityHelper::resolveContainedPath(JPATH_ROOT, $path);
    ```

    This ensures encoded traversal attempts like `%2e%2e%2f` (which decodes to `../`) are properly rejected. Joomla's `JInput` does NOT automatically URL-decode all inputs, so explicit decoding is required for path parameters.

2. **Refactor DigitalFileService methods**

    Replace `getStorageRelativePath()` + `resolveAbsolutePath()` with:

    ```php
    private function getStorageAbsolutePath(?string $configured = null): ?string
    {
        if ($configured === null) {
            $configured = (string) $this->settings->get('digital_storage_path', self::DEFAULT_STORAGE);
        }

        $configured = trim($configured);
        if ($configured === '') {
            $configured = self::DEFAULT_STORAGE;
        }

        // Strip JPATH_ROOT prefix if present (for backwards compat)
        if (str_starts_with($configured, JPATH_ROOT)) {
            $configured = ltrim(substr($configured, strlen(JPATH_ROOT)), '/\\');
        }

        $resolved = PathSecurityHelper::resolveContainedPath(JPATH_ROOT, $configured);

        if ($resolved === null) {
            // Log security event
            Log::add(
                sprintf('Rejected storage path (traversal attempt?): %s', $configured),
                Log::WARNING,
                'com_nxpeasycart.security'
            );
            // Fallback to default
            return PathSecurityHelper::resolveContainedPath(JPATH_ROOT, self::DEFAULT_STORAGE);
        }

        return $resolved;
    }
    ```

    Update all callsites:
    - `upload()`: line ~157 → `$storagePath = $this->getStorageAbsolutePath();`
    - `listFiles()`: line ~250 → same
    - `deleteFile()`: line ~350 → same
    - `ensureDirectory()`: line ~958 → receives absolute path

3. **Add unit tests** (new file: `tests/Unit/Helper/PathSecurityHelperTest.php`)

    Test cases:
    - ✅ Valid relative path → returns canonicalized absolute path
    - ✅ Traversal with `..` → returns null
    - ✅ Encoded traversal (`%2e%2e`) → returns null (after URL decode in controller)
    - ✅ Absolute path inside JPATH_ROOT → returns canonicalized path
    - ✅ Absolute path outside JPATH_ROOT → returns null
    - ✅ Symlink pointing outside → returns null
    - ✅ Multiple slashes (`//` or `\`) → normalized and accepted if valid
    - ✅ Non-existent path (for new directory creation) → returns expected path if parent valid

4. **Update settings validation** (`SettingsController.php` / `SettingsDraft` composable)

    When saving `digital_storage_path`:

    ```php
    $path = $input->getString('digital_storage_path', '');
    $resolved = PathSecurityHelper::resolveContainedPath(JPATH_ROOT, $path);
    if ($resolved === null) {
        return $this->respond([
            'errors' => ['digital_storage_path' => 'Invalid path (traversal detected)']
        ], 400);
    }
    // Save relative path for portability
    $relative = trim(str_replace(JPATH_ROOT, '', $resolved), '/\\');
    $settings['digital_storage_path'] = $relative;
    ```

5. **Audit log integration**

    Add audit trail when path validation fails (in DigitalFileService):

    ```php
    $audit = $container->get(AuditService::class);
    $audit->log('digital_files', 0, 'path_rejected', [
        'attempted_path' => $configured,
        'user_id' => $user->id,
    ]);
    ```

#### Testing Checklist

- [ ] PHPUnit tests for PathSecurityHelper pass
- [ ] Upload digital file with default path succeeds
- [ ] Upload with custom valid path (e.g., `custom/downloads`) succeeds
- [ ] Settings UI rejects `../../../etc/passwd` with user-friendly error
- [ ] Settings UI rejects `/var/www/outside` with error
- [ ] Symlink attack: create symlink `media/test-link -> /tmp`, verify rejection
- [ ] Existing digital files remain downloadable after upgrade

#### Deployment Notes

- **Backwards compatible**: existing valid paths continue to work
- **Rollback safe**: if PathSecurityHelper has bugs, old code path can be re-enabled via feature flag
- **Migration**: no data migration required (paths stored as relative strings)

---

## Issue 2: Monolithic Admin Components

### Current State

**Files**:

1. `media/com_nxpeasycart/src/app/components/SettingsPanel.vue` (3,152 lines)
2. `media/com_nxpeasycart/src/app/components/ProductEditor.vue` (3,169 lines)

**Problems**:

- **Cognitive overload**: single file handles 5+ responsibilities (tabs, drafts, validation, API calls, UI state)
- **Testing difficulty**: cannot test individual tabs/features in isolation
- **Merge conflicts**: high-traffic file means frequent Git conflicts
- **Performance**: entire component tree mounts even if user only views one tab
- **Maintenance risk**: "too big to safely change" → fear-driven development

### Solution: Extract by Responsibility

#### Existing Composables (Important Context)

The project already has several composables that handle settings-related state:

| Composable | Lines | Purpose |
|------------|-------|---------|
| `useSettings.js` | 237 | General settings fetch/save with caching |
| `usePayments.js` | 117 | Payment gateway config fetch/save |
| `useTaxRates.js` | — | Tax rate CRUD |
| `useShippingRules.js` | — | Shipping rule CRUD |

**Recommended strategy**: **Extend existing composables** rather than creating parallel "draft" versions. This avoids:
- Duplicate API integration code
- Confusion about which composable to use
- Maintaining two codebases for the same functionality

**How to extend**:

1. Add `isDirty` computed property (compare current vs. original values)
2. Add `resetDraft()` method to reload from server
3. Add `originalValues` ref to track what was loaded
4. Existing `save()` method already handles persistence

Example extension pattern for `useSettings.js`:

```javascript
// Add to existing useSettings.js
const originalValues = ref(null);

const isDirty = computed(() => {
    if (!originalValues.value) return false;
    return JSON.stringify(state.values) !== JSON.stringify(originalValues.value);
});

const refresh = async (forceRefresh = false) => {
    // ... existing fetch logic ...
    originalValues.value = JSON.parse(JSON.stringify(state.values)); // Deep clone
};

const resetDraft = () => {
    if (originalValues.value) {
        state.values = JSON.parse(JSON.stringify(originalValues.value));
    }
};
```

#### Phase 1: SettingsPanel.vue Refactoring

**Goal**: Split into tab-based subcomponents that consume existing composables

##### Step 1.1: Extend Existing Composables

Rather than creating new composables from scratch, **extend the existing ones** with draft tracking:

| Composable | Action | Notes |
|------------|--------|-------|
| `useSettings.js` | **Extend** | Add `isDirty`, `resetDraft()`, `originalValues` |
| `usePayments.js` | **Extend** | Add `isDirty`, `resetDraft()`, `originalValues` |
| `useDigitalSettings.js` | **Create new** | Digital product config (file types, storage, limits) |
| `useVisualSettings.js` | **Create new** | Color/template overrides (subset of settings) |
| `useSecuritySettings.js` | **Create new** | Rate limiting config (subset of settings) |

The new composables (`useDigitalSettings`, `useVisualSettings`, `useSecuritySettings`) are **thin wrappers** that:
- Extract specific fields from `useSettings()` state
- Provide tab-specific validation
- Delegate save operations to the parent `useSettings().save()`

Example: `useDigitalSettings.js` (new file):

```javascript
import { computed } from 'vue';

/**
 * Thin wrapper for digital product settings.
 * Extracts digital-specific fields from the parent settings composable.
 */
export function useDigitalSettings(settingsState) {
    // Extract digital fields from parent state
    const digitalFields = computed(() => ({
        digital_storage_path: settingsState.values.digital_storage_path ?? '',
        digital_download_limit: settingsState.values.digital_download_limit ?? 3,
        digital_link_expiry_hours: settingsState.values.digital_link_expiry_hours ?? 72,
        digital_allowed_extensions: settingsState.values.digital_allowed_extensions ?? [],
    }));

    // Validation specific to digital settings
    function validate() {
        const errors = {};
        if (!digitalFields.value.digital_storage_path) {
            errors.digital_storage_path = 'Storage path is required';
        }
        return Object.keys(errors).length === 0 ? null : errors;
    }

    return {
        fields: digitalFields,
        validate,
    };
}
```

##### Step 1.2: Create Tab Subcomponents (New Directory: `media/com_nxpeasycart/src/app/components/settings/`)

1. **`GeneralTab.vue`** (~300 lines)
    - Store name, email, currency, locale
    - Uses `useSettingsDraft()`
    - Emits `@save`, `@reset`

2. **`DigitalTab.vue`** (~500 lines)
    - File type checkboxes (current content from SettingsPanel ~line 800-1500)
    - Storage path input
    - Download limits/expiry
    - Uses `useDigitalSettings()`

3. **`PaymentsTab.vue`** (~600 lines)
    - Stripe/PayPal credential inputs + test mode toggles
    - Webhook URL display + copy buttons
    - Uses `usePaymentSettings()`

4. **`VisualTab.vue`** (~400 lines)
    - Color pickers for primary/text/surface/border/muted
    - Template detection display
    - Live preview (if feasible)
    - Uses `useVisualSettings()`

5. **`SecurityTab.vue`** (~250 lines)
    - Rate limit config
    - HTTPS enforcement toggle
    - Uses `useSecuritySettings()`

##### Step 1.3: Refactor SettingsPanel.vue (Target: ~250 lines)

```vue
<template>
    <section class="nxp-ec-admin-panel nxp-ec-admin-panel--settings">
        <header class="nxp-ec-admin-panel__header">
            <div>
                <h2 class="nxp-ec-admin-panel__title">
                    {{ __("COM_NXPEASYCART_MENU_SETTINGS", "Settings") }}
                </h2>
                <p class="nxp-ec-admin-panel__lead">
                    {{ __("COM_NXPEASYCART_SETTINGS_LEAD", "Configure...") }}
                </p>
            </div>
        </header>

        <nav class="nxp-ec-settings-tabs">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                type="button"
                class="nxp-ec-settings-tab"
                :class="{ 'is-active': activeTab === tab.id }"
                @click="activeTab = tab.id"
            >
                {{ tab.label }}
            </button>
        </nav>

        <div class="nxp-ec-settings-content">
            <GeneralTab v-if="activeTab === 'general'" @save="handleSave" />
            <DigitalTab
                v-else-if="activeTab === 'digital'"
                @save="handleSave"
            />
            <PaymentsTab
                v-else-if="activeTab === 'payments'"
                @save="handleSave"
            />
            <VisualTab v-else-if="activeTab === 'visual'" @save="handleSave" />
            <SecurityTab
                v-else-if="activeTab === 'security'"
                @save="handleSave"
            />
        </div>
    </section>
</template>

<script>
import { ref, computed, defineAsyncComponent } from "vue";

// Lazy-load tabs to improve initial mount time
// Only the active tab is loaded; others load on first visit
const GeneralTab = defineAsyncComponent(() => import("./settings/GeneralTab.vue"));
const DigitalTab = defineAsyncComponent(() => import("./settings/DigitalTab.vue"));
const PaymentsTab = defineAsyncComponent(() => import("./settings/PaymentsTab.vue"));
const VisualTab = defineAsyncComponent(() => import("./settings/VisualTab.vue"));
const SecurityTab = defineAsyncComponent(() => import("./settings/SecurityTab.vue"));
import { useTranslations } from "../composables/useTranslations.js";

export default {
    name: "SettingsPanel",
    components: {
        GeneralTab,
        DigitalTab,
        PaymentsTab,
        VisualTab,
        SecurityTab,
    },
    setup() {
        const { __ } = useTranslations();
        const activeTab = ref("general");

        const tabs = computed(() => [
            {
                id: "general",
                label: __("COM_NXPEASYCART_SETTINGS_TAB_GENERAL", "General"),
            },
            {
                id: "digital",
                label: __("COM_NXPEASYCART_SETTINGS_DIGITAL_TITLE", "Digital"),
            },
            {
                id: "payments",
                label: __("COM_NXPEASYCART_SETTINGS_TAB_PAYMENTS", "Payments"),
            },
            {
                id: "visual",
                label: __("COM_NXPEASYCART_SETTINGS_TAB_VISUAL", "Visual"),
            },
            {
                id: "security",
                label: __("COM_NXPEASYCART_SETTINGS_TAB_SECURITY", "Security"),
            },
        ]);

        function handleSave() {
            // Delegate to active tab's composable
            // Or emit event to parent App.vue for toast notification
        }

        return {
            activeTab,
            tabs,
            handleSave,
            __,
        };
    },
};
</script>
```

##### Step 1.4: Migration Checklist

- [ ] Extract `useSettingsDraft()` composable
- [ ] Create GeneralTab.vue + move general settings fields
- [ ] Create DigitalTab.vue + move digital product settings (lines ~800-1500)
- [ ] Create PaymentsTab.vue + move Stripe/PayPal forms (lines ~1500-2100)
- [ ] Create VisualTab.vue + move color pickers (lines ~2100-2500)
- [ ] Create SecurityTab.vue + move security config (lines ~2500-2800)
- [ ] Update SettingsPanel.vue to coordinate tabs
- [ ] Test each tab saves/loads correctly
- [ ] Test tab switching preserves unsaved draft data
- [ ] Test validation errors display in correct tab
- [ ] Test keyboard navigation (tab key, arrow keys)
- [ ] Verify no regressions in existing functionality

#### Phase 2: ProductEditor.vue Refactoring

**Goal**: Extract per-tab subcomponents + dedicated composables for media/variants

##### Step 2.1: Create Composables

1. **`useProductDraft.js`** — Product form state (title, slug, description, active, type)
2. **`useVariantsManager.js`** — Variant CRUD (add/edit/delete variants, SKU validation)
3. **`useProductImages.js`** — Image management (upload, reorder, delete)
4. **`useProductCategories.js`** — Category selection + inline creation
5. **`useJoomlaMediaPicker.js`** — Joomla media manager bridge (eliminate innerHTML)

##### Step 2.2: Create Tab Subcomponents (New Directory: `media/com_nxpeasycart/src/app/components/product-editor/`)

1. **`GeneralTab.vue`** (~400 lines)
    - Title, slug, description, status, product type
    - Uses `useProductDraft()`

2. **`VariantsTab.vue`** (~800 lines)
    - Variant table (SKU, price, stock, sale price, digital flag)
    - Add/edit/delete variant modals
    - Uses `useVariantsManager()`

3. **`ImagesTab.vue`** (~500 lines)
    - Product-level + per-variant image galleries
    - Uses `useProductImages()` + `useJoomlaMediaPicker()`
    - **Critical**: Remove DOM manipulation/innerHTML (lines 2520-2625)

4. **`CategoriesTab.vue`** (~300 lines)
    - Category multi-select + inline "Create New" input
    - Uses `useProductCategories()`

5. **`DigitalFilesTab.vue`** (~400 lines)
    - Digital file uploads (only visible when product type = Digital)
    - File list with delete buttons
    - Uses existing digital files API

##### Step 2.3: Extract Joomla Media Picker Bridge

**Current Issue** (ProductEditor.vue lines 2520-2625):

- Uses `innerHTML` to inject hidden `<joomla-field-media>` elements into DOM
- Brittle, hard to test, violates Vue reactivity model

**New Approach**: `useJoomlaMediaPicker.js` composable

```javascript
import { ref, onMounted, onUnmounted } from "vue";

export function useJoomlaMediaPicker() {
    const pickerElement = ref(null);

    function createPicker(fieldId, options = {}) {
        // Create element programmatically (no innerHTML)
        const field = document.createElement("joomla-field-media");
        field.setAttribute("id", fieldId);
        field.setAttribute("name", options.name || fieldId);
        field.setAttribute("type", "image");
        field.setAttribute("directory", options.directory || "com_nxpeasycart");

        // Style to hide (not innerHTML)
        field.style.display = "none";

        // Attach event listener for selection
        field.addEventListener("change", (e) => {
            options.onSelect?.(e.detail || e.target.value);
        });

        // Mount to body
        document.body.appendChild(field);
        pickerElement.value = field;

        return field;
    }

    function openPicker() {
        if (!pickerElement.value) return;

        // Trigger Joomla's media picker open
        const button = pickerElement.value.querySelector("button");
        button?.click();
    }

    function destroyPicker() {
        if (pickerElement.value) {
            pickerElement.value.remove();
            pickerElement.value = null;
        }
    }

    onUnmounted(() => {
        destroyPicker();
    });

    return {
        createPicker,
        openPicker,
        destroyPicker,
    };
}
```

Usage in `ImagesTab.vue`:

```vue
<script>
import { useJoomlaMediaPicker } from "../../composables/useJoomlaMediaPicker.js";

export default {
    setup() {
        const { createPicker, openPicker } = useJoomlaMediaPicker();

        function selectImage() {
            const picker = createPicker("product-image-picker", {
                onSelect: (url) => {
                    // Add image to product
                    images.value.push({ url, alt: "" });
                },
            });
            openPicker();
        }

        return { selectImage };
    },
};
</script>
```

##### Step 2.4: Refactor ProductEditor.vue (Target: ~300 lines)

Similar structure to SettingsPanel.vue refactor:

- Tabs nav
- Conditional tab rendering (`<GeneralTab v-if="activeTab === 'general'">`)
- Form submission coordinator
- Modal shell (open/close, save/cancel buttons)

##### Step 2.5: Migration Checklist

- [ ] Extract composables (useProductDraft, useVariantsManager, etc.)
- [ ] Create GeneralTab.vue
- [ ] Create VariantsTab.vue (test variant add/edit/delete)
- [ ] Create ImagesTab.vue + useJoomlaMediaPicker (test media selection)
- [ ] Create CategoriesTab.vue (test category multi-select + creation)
- [ ] Create DigitalFilesTab.vue (test file upload/delete)
- [ ] Update ProductEditor.vue to coordinate tabs
- [ ] Remove all `innerHTML` usage (verify with ESLint rule)
- [ ] Test product create/edit flow end-to-end
- [ ] Test validation errors in each tab
- [ ] Test switching tabs with unsaved changes (confirm prompt?)
- [ ] Verify image picker works on all Joomla templates
- [ ] Verify no regressions in existing functionality

#### Phase 3: Testing & Documentation

##### Unit Tests (New: `tests/Unit/Vue/`)

Use Vitest + Vue Test Utils:

1. **Composables**:
    - `useSettingsDraft.test.js` → test load/save/reset
    - `useProductDraft.test.js` → test validation, dirty state
    - `useJoomlaMediaPicker.test.js` → mock DOM, test picker lifecycle

2. **Components**:
    - `GeneralTab.test.js` (Settings) → test field bindings, save emit
    - `VariantsTab.test.js` (Product) → test variant add/delete

##### Integration Tests

- Playwright: full product editor flow (open, fill tabs, save, verify DB)
- Playwright: settings flow (change currency, save, reload page, verify persistence)

##### Documentation

Create `docs/admin-component-architecture.md`:

- Explain tab-based component structure
- Document composable responsibilities
- Provide examples for adding new tabs
- Explain Joomla media picker integration

---

## Issue 3: CSRF Validation Inconsistency

### Current State

**Inconsistent patterns across storefront controllers**:

1. **CartController.php**:
    - `add()` (line 53): `Session::checkToken('post')` only
    - `update()` (line 181): `Session::checkToken('post')` only
    - `remove()` (line 264): `Session::checkToken('post')` only
    - `applyCoupon()` (line 675): `Session::checkToken('request')` (supports headers!)
    - `removeCoupon()` (line 841): `Session::checkToken('request')`

2. **PaymentController.php**:
    - `hasValidToken()` (lines 612-637): Custom "header OR post OR request" cascade

**Problems**:

- **Confusing**: no clear pattern for when to use `post` vs `request` vs custom helper
- **Maintenance burden**: logic duplicated across controllers
- **Bug risk**: easy to forget correct validation when adding new endpoints
- **Developer experience**: newcomers don't know which pattern to use

### Solution: Standardize CSRF Validation

#### Implementation Steps

##### Step 1: Create Shared CSRF Trait

**New file**: `components/com_nxpeasycart/src/Trait/CsrfValidation.php`

```php
<?php
namespace Joomla\Component\Nxpeasycart\Site\Trait;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Session\Session;

/**
 * Standardized CSRF validation for storefront endpoints.
 *
 * Accepts tokens via:
 * - X-CSRF-Token header (for JSON API calls from Vue islands)
 * - POST form field (for traditional form submissions)
 * - Query string (only if explicitly enabled for GET-safe operations)
 *
 * @since 0.3.2
 */
trait CsrfValidation
{
    /**
     * Verify CSRF token from request.
     *
     * @param bool $allowQuery Whether to accept token in query string (default: false)
     * @return bool True if valid token found
     * @since 0.3.2
     */
    protected function hasValidCsrfToken(bool $allowQuery = false): bool
    {
        $input = Factory::getApplication()->getInput();
        $sessionToken = Session::getFormToken();

        // 1. Check X-CSRF-Token header (preferred for JSON API)
        $headerToken = (string) $input->server->getString('HTTP_X_CSRF_TOKEN', '');
        if ($headerToken !== '' && hash_equals($sessionToken, $headerToken)) {
            return true;
        }

        // 2. Check POST body token (standard Joomla forms)
        if (Session::checkToken('post')) {
            return true;
        }

        // 3. Check query string (only if explicitly allowed)
        //    Use case: legacy redirect flows, but avoid for state-changing ops
        if ($allowQuery && Session::checkToken('get')) {
            return true;
        }

        return false;
    }

    /**
     * Verify CSRF token and send JSON error if invalid.
     *
     * @param bool $allowQuery Whether to accept token in query string
     * @return void Exits with 403 JSON response if token invalid
     * @since 0.3.2
     */
    protected function requireCsrfToken(bool $allowQuery = false): void
    {
        if (!$this->hasValidCsrfToken($allowQuery)) {
            $this->sendJsonError('Invalid CSRF token', 403);
        }
    }

    /**
     * Send JSON error response and exit.
     *
     * @param string $message Error message
     * @param int $code HTTP status code (default: 400)
     * @return void Never returns
     * @since 0.3.2
     */
    private function sendJsonError(string $message, int $code = 400): void
    {
        $app = Factory::getApplication();

        if (\function_exists('http_response_code')) {
            http_response_code($code);
        }

        $app->setHeader('Content-Type', 'application/json', true);
        $app->setHeader('Status', (string) $code, true);

        echo new \Joomla\CMS\Response\JsonResponse(null, $message, true);
        $app->close();
    }
}
```

##### Step 2: Refactor CartController.php

Replace all `Session::checkToken()` calls with trait method:

```php
namespace Joomla\Component\Nxpeasycart\Site\Controller;

use Joomla\Component\Nxpeasycart\Site\Trait\CsrfValidation;

class CartController extends BaseController
{
    use CsrfValidation;

    public function add(): void
    {
        $this->requireCsrfToken(); // Replaces lines 53-56

        $this->enforceRateLimit();
        // ... rest of method
    }

    public function update(): void
    {
        $this->requireCsrfToken(); // Replaces lines 181-184
        // ... rest of method
    }

    public function remove(): void
    {
        $this->requireCsrfToken(); // Replaces lines 264-267
        // ... rest of method
    }

    public function applyCoupon(): void
    {
        $this->requireCsrfToken(); // Replaces lines 675-678
        // ... rest of method
    }

    public function removeCoupon(): void
    {
        $this->requireCsrfToken(); // Replaces lines 841-844
        // ... rest of method
    }
}
```

##### Step 3: Refactor PaymentController.php

Remove custom `hasValidToken()` method (lines 612-637), replace with trait:

```php
namespace Joomla\Component\Nxpeasycart\Site\Controller;

use Joomla\Component\Nxpeasycart\Site\Trait\CsrfValidation;

class PaymentController extends BaseController
{
    use CsrfValidation;

    public function createSession(): void
    {
        $this->requireCsrfToken(); // Replaces hasValidToken() call

        // ... rest of method
    }

    // Remove old hasValidToken() method entirely
}
```

##### Step 4: Update Other Controllers

Apply trait to any controller with state-changing endpoints:

- `CheckoutController.php`
- `DownloadController.php` (if it has state-changing ops)
- Any future storefront controllers

##### Step 5: Documentation

Update `docs/security-audit-fixes.md` with new section:

````markdown
## CSRF Validation Standard (v0.3.2)

All storefront endpoints that modify state (POST/PUT/DELETE) MUST use the `CsrfValidation` trait:

```php
use Joomla\Component\Nxpeasycart\Site\Trait\CsrfValidation;

class MyController extends BaseController
{
    use CsrfValidation;

    public function myAction(): void
    {
        $this->requireCsrfToken(); // Validates token or exits with 403
        // ... safe to proceed
    }
}
```
````

**Token Acceptance**:

- ✅ `X-CSRF-Token` HTTP header (for JSON API calls)
- ✅ POST form field `{token}=1` (for traditional forms)
- ⚠️ Query string `?{token}=1` (disabled by default; enable with `requireCsrfToken(true)` only for read-safe operations or email/redirect flows where POST isn't feasible)

**When to Allow Query String Tokens**:
- ✅ Email links (order status, download links) where user clicks from external client
- ✅ Payment gateway redirects (return URLs after external checkout)
- ✅ Webhook verification URLs (if webhook provider only supports GET)
- ❌ Cart operations (add/update/remove) - these should always use POST + header/body token
- ❌ Checkout submission - must use POST with header or body token
- ❌ Any operation that modifies critical state (orders, payments, user data)

**Testing**:

- Unit test: `tests/Unit/Trait/CsrfValidationTest.php`
- Playwright: verify checkout flow with/without valid token

```

##### Step 6: Testing Checklist

- [ ] Create PHPUnit test for CsrfValidation trait (mock requests with headers/POST)
- [ ] Test cart add/update/remove with valid token → success
- [ ] Test cart add with missing token → 403 error
- [ ] Test cart add with invalid token → 403 error
- [ ] Test checkout flow with X-CSRF-Token header → success
- [ ] Test coupon apply/remove with valid token → success
- [ ] Verify all existing Playwright tests still pass
- [ ] Test on multiple Joomla templates (Cassiopeia, Helix, T4)

#### Migration Checklist

- [ ] Create CsrfValidation trait
- [ ] Add PHPUnit test for trait
- [ ] Refactor CartController (5 methods)
- [ ] Refactor PaymentController (remove custom hasValidToken)
- [ ] Refactor CheckoutController (if applicable)
- [ ] Update security documentation
- [ ] Run full test suite (PHPUnit + Playwright)
- [ ] Manual smoke test: add to cart, apply coupon, checkout

---

## Rollout Strategy

### Phase Ordering

**Priority order** (can be done independently):
1. **CSRF Validation** (1-2 days) — Quick win, reduces immediate confusion
2. **Path Security** (2-3 days) — Security fix, moderate complexity
3. **SettingsPanel Refactor** (5-7 days) — Largest impact on maintainability
4. **ProductEditor Refactor** (5-7 days) — Similar to SettingsPanel, builds on patterns

**Total estimate**: 13-19 days (split across multiple sprints)

### Risk Mitigation

1. **Feature Flags**:
   - Add `config` option: `use_legacy_path_validation` (default: `false`)
   - Add `config` option: `use_legacy_csrf_validation` (default: `false`)
   - Allows instant rollback if issues found in production

2. **Incremental Rollout**:
   - Deploy CSRF changes first (lowest risk, easiest to test)
   - Deploy path security (testable in isolation)
   - Deploy component refactors last (requires more QA)

3. **Testing Gates**:
   - No PR merge without PHPUnit tests passing
   - No release without Playwright E2E tests passing
   - Manual QA checklist for each refactor (see checklists above)

### Monitoring

Post-deployment monitoring:
- Watch Joomla error logs for new path validation warnings
- Monitor checkout success rate (CSRF changes could break flows)
- Track admin panel load times (component refactor might affect perf)

---

## Success Metrics

### Security
- ✅ Zero path traversal vulnerabilities (verified by security audit)
- ✅ Consistent CSRF validation across 100% of state-changing endpoints

### Maintainability
- ✅ Average component size < 500 lines (down from 3,000+)
- ✅ 80%+ code coverage on new composables/traits
- ✅ Zero `innerHTML` usage in Vue components (enforce with ESLint)

### Developer Experience
- ✅ New contributors can understand component structure in < 30 minutes
- ✅ Adding new settings tab requires < 100 lines of code
- ✅ CSRF validation is copy-paste from docs (no custom logic needed)

---

## Appendix: File Changes Summary

### New Files

**PHP (Security)**
- `administrator/components/com_nxpeasycart/src/Helper/PathSecurityHelper.php`
- `components/com_nxpeasycart/src/Trait/CsrfValidation.php`

**Vue Composables (Thin Wrappers)**
- `media/com_nxpeasycart/src/app/composables/useDigitalSettings.js` — Extracts digital fields from useSettings
- `media/com_nxpeasycart/src/app/composables/useVisualSettings.js` — Extracts visual fields from useSettings
- `media/com_nxpeasycart/src/app/composables/useSecuritySettings.js` — Extracts security fields from useSettings
- `media/com_nxpeasycart/src/app/composables/useProductDraft.js` — Product form state management
- `media/com_nxpeasycart/src/app/composables/useVariantsManager.js` — Variant CRUD operations
- `media/com_nxpeasycart/src/app/composables/useProductImages.js` — Image management
- `media/com_nxpeasycart/src/app/composables/useProductCategories.js` — Category selection
- `media/com_nxpeasycart/src/app/composables/useJoomlaMediaPicker.js` — Joomla media manager bridge

**Vue Components (Settings Tabs)**
- `media/com_nxpeasycart/src/app/components/settings/GeneralTab.vue`
- `media/com_nxpeasycart/src/app/components/settings/DigitalTab.vue`
- `media/com_nxpeasycart/src/app/components/settings/PaymentsTab.vue`
- `media/com_nxpeasycart/src/app/components/settings/VisualTab.vue`
- `media/com_nxpeasycart/src/app/components/settings/SecurityTab.vue`

**Vue Components (Product Editor Tabs)**
- `media/com_nxpeasycart/src/app/components/product-editor/GeneralTab.vue`
- `media/com_nxpeasycart/src/app/components/product-editor/VariantsTab.vue`
- `media/com_nxpeasycart/src/app/components/product-editor/ImagesTab.vue`
- `media/com_nxpeasycart/src/app/components/product-editor/CategoriesTab.vue`
- `media/com_nxpeasycart/src/app/components/product-editor/DigitalFilesTab.vue`

**Tests**
- `tests/Unit/Administrator/Helper/PathSecurityHelperTest.php`
- `tests/Unit/Site/Trait/CsrfValidationTest.php`
- `tests/Unit/Vue/useDigitalSettings.test.js`
- `tests/Unit/Vue/useJoomlaMediaPicker.test.js`

**Config Files**
- `vitest.config.js` — Vue test configuration
- `.eslintrc.js` — ESLint with Vue plugin

**Documentation**
- `docs/admin-component-architecture.md`

### Modified Files

**PHP (Extend/Refactor)**
- `administrator/components/com_nxpeasycart/src/Service/DigitalFileService.php` — Refactor path methods to use PathSecurityHelper
- `components/com_nxpeasycart/src/Controller/CartController.php` — Apply CsrfValidation trait
- `components/com_nxpeasycart/src/Controller/PaymentController.php` — Apply trait, remove custom hasValidToken()
- `components/com_nxpeasycart/src/Controller/CheckoutController.php` — Apply CsrfValidation trait (if applicable)

**Vue (Extend Existing Composables)**
- `media/com_nxpeasycart/src/app/composables/useSettings.js` — Add `isDirty`, `resetDraft()`, `originalValues`
- `media/com_nxpeasycart/src/app/composables/usePayments.js` — Add `isDirty`, `resetDraft()`, `originalValues`

**Vue (Refactor to Coordinator)**
- `media/com_nxpeasycart/src/app/components/SettingsPanel.vue` — Reduce to ~250 lines (coordinator)
- `media/com_nxpeasycart/src/app/components/ProductEditor.vue` — Reduce to ~300 lines (coordinator)

**Documentation**
- `docs/security-audit-fixes.md` — Add CSRF validation standard section

**Config (Add Scripts)**
- `package.json` — Add test:vue, lint:vue scripts

### Lines of Code Impact
- **Before**: 6,321 lines in 2 files (SettingsPanel + ProductEditor)
- **After**: ~550 lines in 2 coordinators + ~3,500 lines in 12 subcomponents
- **Net reduction**: ~2,300 lines (36% reduction via deduplication + removal of boilerplate)

---

## Questions & Decisions

### Open Questions (Resolved)

1. **Should we add ESLint rule to ban `innerHTML` in Vue components?**

   **Answer: Yes.** Add `'vue/no-v-html': 'error'` to ESLint config. This enforces the security requirement at build time rather than relying on code review. See Prerequisites section for setup instructions.

2. **Do we want feature flags for gradual rollout, or deploy all changes at once?**

   **Answer: Feature flags for security changes, batch deploy for refactors.**

   - **Security changes** (CSRF, Path validation): Use feature flags (`use_legacy_csrf_validation`, `use_legacy_path_validation`) to enable instant rollback if issues arise in production.
   - **Component refactors** (SettingsPanel, ProductEditor): Deploy as a batch since they're internal changes that don't affect external APIs. If issues occur, revert the commit.

   This balances safety (security changes need quick rollback) with simplicity (refactors don't need config toggles).

3. **Should PathSecurityHelper support multiple allowed bases (e.g., JPATH_ROOT + custom upload dir)?**

   **Answer: No, keep it simple for MVP.** Single base (JPATH_ROOT) covers all current use cases. If future requirements need multiple bases, refactor to accept an array:

   ```php
   public static function resolveContainedPath(array $allowedBases, string $relative): ?string
   ```

   For now, YAGNI (You Ain't Gonna Need It) applies.

### Decisions Made
- ✅ Use trait (not abstract base class) for CSRF validation → keeps controllers lightweight
- ✅ Split composables by responsibility (not by tab) → enables reuse across components
- ✅ Keep tab coordination in parent component → tabs remain "dumb" presentational components
- ✅ Remove `innerHTML` entirely → use programmatic DOM APIs in composables
- ✅ **Extend existing composables** (`useSettings.js`, `usePayments.js`) rather than creating parallel draft versions → avoids duplicate code
- ✅ **Lazy-load tab components** with `defineAsyncComponent()` → improves initial mount time
- ✅ **Add ESLint** with `vue/no-v-html` rule → enforces security at build time

---

## Revision History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2025-12-17 | Initial plan |
| 1.1 | 2025-12-17 | Added Prerequisites section (Vitest, ESLint), clarified composable extension strategy, added URL-decode handling note, resolved open questions, added lazy-loading optimization |
| 1.2 | 2025-12-17 | **Implementation Progress**: Issue 3 (CSRF) complete, Issue 2 (Components) partial |

---

## Implementation Progress

### Issue 3: CSRF Validation ✅ COMPLETE (2025-12-17)

All CSRF refactoring has been implemented and tested:

- Created `components/com_nxpeasycart/src/Trait/CsrfValidation.php`
- Refactored `CartController.php` (5 methods)
- Refactored `PaymentController.php` (removed duplicate hasValidToken())
- Created PHPUnit tests (17 tests, 33 assertions)
- Updated `docs/security-audit-fixes.md`

### Issue 2: Monolithic Admin Components 🔄 IN PROGRESS

#### Completed:
- ✅ Installed Vitest + Vue Test Utils (package.json updated)
- ✅ Created `vitest.config.js` configuration
- ✅ Extended `useSettings.js` with `isDirty`, `resetDraft()`, `originalValues`
- ✅ Extended `usePayments.js` with `isDirty`, `resetDraft()`, `originalValues`
- ✅ Created `media/com_nxpeasycart/src/app/components/settings/` directory
- ✅ Created `GeneralTab.vue` (~550 lines extracted)
- ✅ Created `DigitalTab.vue` (~310 lines extracted)
- ✅ Created basic Vue tests in `tests/Unit/Vue/`
- ✅ Admin bundle builds successfully

#### Remaining:
- ⏳ Extract `SecurityTab.vue` (~264 lines)
- ⏳ Extract `PaymentsTab.vue` (~699 lines)
- ⏳ Extract `VisualTab.vue` (~335 lines)
- ⏳ Refactor `SettingsPanel.vue` to use extracted tabs
- ⏳ ProductEditor.vue refactoring (Phase 2)

### Issue 1: Path Security ⏳ PENDING

Not yet started. Blocked until Issue 2 is complete (as planned).

---

**End of Refactoring Plan v0.3.2**
