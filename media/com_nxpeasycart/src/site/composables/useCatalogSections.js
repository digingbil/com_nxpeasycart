import { computed, unref } from "vue";

const DEFAULT_MAX_ITEMS = 12;

export default function useCatalogSections(source, limit = DEFAULT_MAX_ITEMS) {
    return computed(() => {
        const sections = normaliseSections(unref(source));

        return sections.map((section) => ({
            key: section.key || section.title || `section-${section.__index}`,
            title: section.title || "",
            items: (section.items || []).slice(0, limit),
        }));
    });
}

function normaliseSections(value) {
    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .filter(
            (section) =>
                section &&
                typeof section === "object" &&
                Array.isArray(section.items) &&
                section.items.length
        )
        .map((section, index) => ({
            ...section,
            __index: index,
        }));
}
