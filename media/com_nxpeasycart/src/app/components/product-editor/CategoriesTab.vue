<template>
    <section class="nxp-ec-editor-panel">
        <div class="nxp-ec-form-field">
            <label class="nxp-ec-form-label" for="product-category-select">
                {{
                    __(
                        "COM_NXPEASYCART_PRODUCT_CATEGORIES_LABEL",
                        "Categories"
                    )
                }}
            </label>
            <select
                id="product-category-select"
                class="nxp-ec-form-select"
                multiple
                :value="selectedIds"
                @change="handleSelectionChange"
            >
                <option
                    v-for="option in categoryOptions"
                    :key="option.id"
                    :value="option.id"
                >
                    {{ getCategoryDisplayName(option) }}
                </option>
            </select>
            <div class="nxp-ec-chip-input nxp-ec-chip-input--selected">
                <span
                    v-for="(category, index) in categories"
                    :key="`selected-category-${index}`"
                    class="nxp-ec-chip"
                >
                    {{ category.title }}
                    <button
                        type="button"
                        class="nxp-ec-chip__remove"
                        @click="$emit('remove-category', index)"
                        :aria-label="
                            __(
                                'COM_NXPEASYCART_REMOVE_CATEGORY',
                                'Remove category'
                            )
                        "
                    >
                        &times;
                    </button>
                </span>
            </div>
            <div class="nxp-ec-chip-input nxp-ec-chip-input--new">
                <input
                    id="product-category-input"
                    type="text"
                    class="nxp-ec-chip-input__field"
                    :value="newCategoryDraft"
                    @input="$emit('update:newCategoryDraft', $event.target.value)"
                    @keydown.enter.prevent="$emit('add-category')"
                    :placeholder="
                        __(
                            'COM_NXPEASYCART_PRODUCT_CATEGORIES_ADD_PLACEHOLDER',
                            'New category name'
                        )
                    "
                />
                <button
                    type="button"
                    class="nxp-ec-btn"
                    @click="$emit('add-category')"
                >
                    {{
                        __(
                            "COM_NXPEASYCART_PRODUCT_CATEGORIES_ADD",
                            "Add category"
                        )
                    }}
                </button>
            </div>
            <p class="nxp-ec-form-help">
                {{
                    __(
                        "COM_NXPEASYCART_PRODUCT_CATEGORIES_HELP",
                        "Select a category or create a new one"
                    )
                }}
            </p>
        </div>
    </section>
</template>

<script setup>
import { computed } from "vue";

/**
 * CategoriesTab - Product category assignment.
 *
 * Handles multi-select category assignment and inline creation
 * of new categories.
 *
 * @since 0.3.2
 */

const props = defineProps({
    /**
     * Array of selected categories.
     */
    categories: {
        type: Array,
        default: () => [],
    },
    /**
     * Available category options.
     */
    categoryOptions: {
        type: Array,
        default: () => [],
    },
    /**
     * New category name being drafted.
     */
    newCategoryDraft: {
        type: String,
        default: "",
    },
    /**
     * Translation function from parent.
     */
    translate: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits([
    "add-category",
    "remove-category",
    "update-selection",
    "update:newCategoryDraft",
]);

const __ = props.translate;

/**
 * Get the IDs of currently selected categories.
 */
const selectedIds = computed(() => {
    return props.categories
        .filter((cat) => cat && cat.id > 0)
        .map((cat) => cat.id);
});

/**
 * Handle selection change in the multi-select.
 */
const handleSelectionChange = (event) => {
    const selected = Array.from(event.target.selectedOptions).map((opt) =>
        Number.parseInt(opt.value, 10)
    );
    emit("update-selection", selected);
};

/**
 * Calculate the depth of a category in the hierarchy.
 */
const getCategoryDepth = (categoryId, visited = new Set()) => {
    if (!categoryId || visited.has(categoryId)) {
        return 0;
    }

    visited.add(categoryId);

    const category = props.categoryOptions.find((cat) => cat.id === categoryId);
    if (!category || !category.parent_id) {
        return 0;
    }

    return 1 + getCategoryDepth(category.parent_id, visited);
};

/**
 * Get the display name for a category with visual indentation.
 */
const getCategoryDisplayName = (category) => {
    if (!category) {
        return "";
    }

    const depth = getCategoryDepth(category.id);

    if (depth === 0) {
        return category.title;
    }

    // Use non-breaking spaces and unicode box characters for visual hierarchy
    const indent = "\u00A0\u00A0".repeat(depth) + "\u2514\u2500 ";
    return indent + category.title;
};
</script>

<style scoped>
#product-category-select {
    font-family: ui-monospace, "SF Mono", "Cascadia Code", "Roboto Mono",
        "Ubuntu Mono", "Menlo", "Consolas", "Monaco", "Liberation Mono",
        monospace;
    white-space: pre;
    min-height: 200px;
}

@media (max-width: 480px) {
    #product-category-select {
        min-height: 150px;
    }
}
</style>
