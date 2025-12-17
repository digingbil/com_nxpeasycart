import { computed, ref } from "vue";

/**
 * useCategories - Category management composable.
 *
 * Handles category normalization, hierarchical sorting, selection,
 * and add/remove operations.
 *
 * @since 0.3.2
 */
export function useCategories(formCategories, categoryOptionsProp) {
    const newCategoryDraft = ref("");

    /**
     * Normalize category input to a consistent shape.
     */
    const normaliseCategoryInput = (input) => {
        if (input == null) {
            return null;
        }

        if (typeof input === "string") {
            const title = input.trim();

            if (title === "") {
                return null;
            }

            return {
                id: 0,
                title,
                slug: "",
            };
        }

        if (typeof input === "object") {
            const id = Number.parseInt(input.id ?? input.value ?? 0, 10) || 0;
            const titleSource =
                input.title ??
                input.name ??
                input.text ??
                input.label ??
                input.slug ??
                "";
            const title = String(titleSource ?? "").trim();
            const slug = String(input.slug ?? "").trim();
            const parentId =
                input.parent_id !== null && input.parent_id !== undefined
                    ? Number.parseInt(input.parent_id, 10) || null
                    : null;

            if (id <= 0 && title === "") {
                return null;
            }

            return {
                id: id > 0 ? id : 0,
                title: title !== "" ? title : slug,
                slug,
                parent_id: parentId,
            };
        }

        return null;
    };

    /**
     * Computed list of category options sorted hierarchically.
     */
    const categoryOptionsList = computed(() => {
        const provided = Array.isArray(categoryOptionsProp.value)
            ? categoryOptionsProp.value
            : [];
        const normalised = provided
            .map(normaliseCategoryInput)
            .filter(
                (category) => category && category.id > 0 && category.title !== ""
            );

        const map = new Map();

        normalised.forEach((category) => {
            map.set(category.id, category);
        });

        formCategories.value.forEach((category) => {
            if (category.id > 0 && category.title && !map.has(category.id)) {
                map.set(category.id, {
                    id: category.id,
                    title: category.title,
                    slug: category.slug ?? "",
                    parent_id:
                        category.parent_id !== null && category.parent_id !== undefined
                            ? category.parent_id
                            : null,
                });
            }
        });

        const categories = Array.from(map.values());

        const buildTree = (parentId = null) => {
            return categories
                .filter((cat) => cat.parent_id === parentId)
                .sort((a, b) => a.title.localeCompare(b.title))
                .flatMap((cat) => [cat, ...buildTree(cat.id)]);
        };

        return buildTree();
    });

    /**
     * Map of category ID to title/slug for quick lookup.
     */
    const categoryOptionsMap = computed(() => {
        const map = new Map();

        categoryOptionsList.value.forEach((category) => {
            map.set(category.id, {
                title: category.title,
                slug: category.slug ?? "",
            });
        });

        return map;
    });

    /**
     * Reset categories from an array of raw category data.
     */
    const resetCategories = (categories) => {
        const incoming = Array.isArray(categories) ? categories : [];

        const normalised = incoming
            .map(normaliseCategoryInput)
            .filter(
                (category) => category && (category.id > 0 || category.title !== "")
            );

        const seen = new Set();
        const unique = [];

        normalised.forEach((category) => {
            const key =
                category.id > 0
                    ? `id:${category.id}`
                    : `title:${category.title.toLowerCase()}`;

            if (!seen.has(key)) {
                seen.add(key);
                unique.push(category);
            }
        });

        formCategories.value.splice(0, formCategories.value.length, ...unique);
    };

    /**
     * Handle category selection update from multi-select.
     */
    const handleCategorySelectionUpdate = (selectedIds) => {
        const list = Array.isArray(selectedIds) ? selectedIds : [];
        const uniqueIds = Array.from(
            new Set(
                list
                    .map((entry) => Number.parseInt(entry ?? 0, 10) || 0)
                    .filter((id) => id > 0)
            )
        );

        const newCategories = formCategories.value.filter(
            (category) => !(category.id > 0)
        );

        const existing = uniqueIds
            .map((id) => {
                const option = categoryOptionsMap.value.get(id);
                const current = formCategories.value.find(
                    (category) => category.id === id
                );

                const title = option?.title || current?.title || "";
                const slug = option?.slug || current?.slug || "";

                if (title === "") {
                    return null;
                }

                return {
                    id,
                    title,
                    slug,
                };
            })
            .filter((category) => category !== null);

        formCategories.value.splice(
            0,
            formCategories.value.length,
            ...existing,
            ...newCategories
        );
    };

    /**
     * Add a new category from the draft input.
     */
    const addCategory = () => {
        const value = newCategoryDraft.value.trim();

        if (!value) {
            return;
        }

        const lowerValue = value.toLowerCase();
        const categories = formCategories.value.slice();

        const exists = categories.some(
            (category) => category.title.toLowerCase() === lowerValue
        );

        if (!exists) {
            categories.push({
                id: 0,
                title: value,
                slug: "",
            });

            formCategories.value.splice(0, formCategories.value.length, ...categories);
        }

        newCategoryDraft.value = "";
    };

    /**
     * Remove a category by index.
     */
    const removeCategory = (index) => {
        if (index < 0 || index >= formCategories.value.length) {
            return;
        }

        const categories = formCategories.value.slice();
        categories.splice(index, 1);
        formCategories.value.splice(0, formCategories.value.length, ...categories);
    };

    /**
     * Build payload categories for submission.
     */
    const buildPayloadCategories = () => {
        return formCategories.value
            .map((category) => {
                const id = Number.parseInt(category?.id ?? 0, 10) || 0;
                const title = String(category?.title ?? "").trim();
                const slug = String(category?.slug ?? "").trim();

                if (id > 0) {
                    return { id, title, slug };
                }

                if (title === "") {
                    return null;
                }

                return { id: 0, title, slug: "" };
            })
            .filter((category) => category !== null);
    };

    return {
        newCategoryDraft,
        categoryOptionsList,
        categoryOptionsMap,
        resetCategories,
        handleCategorySelectionUpdate,
        addCategory,
        removeCategory,
        buildPayloadCategories,
    };
}
