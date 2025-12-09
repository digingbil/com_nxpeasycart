<template>
    <section class="nxp-ec-admin-panel nxp-ec-admin-panel--categories">
        <header class="nxp-ec-admin-panel__header">
            <div>
                <h2 class="nxp-ec-admin-panel__title">
                    {{
                        __(
                            "COM_NXPEASYCART_MENU_CATEGORIES",
                            "Categories",
                            [],
                            "categoriesPanelTitle"
                        )
                    }}
                </h2>
                <p class="nxp-ec-admin-panel__lead">
                    {{
                        __(
                            "COM_NXPEASYCART_CATEGORIES_LEAD",
                            "Organise products with reusable categories.",
                            [],
                            "categoriesPanelLead"
                        )
                    }}
                </p>
            </div>
            <div class="nxp-ec-admin-panel__actions">
                <input
                    type="search"
                    class="nxp-ec-admin-search"
                    :placeholder="
                        __(
                            'COM_NXPEASYCART_CATEGORIES_SEARCH_PLACEHOLDER',
                            'Search categories',
                            [],
                            'categoriesSearchPlaceholder'
                        )
                    "
                    v-model="state.search"
                    @keyup.enter="emitSearch"
                    :aria-label="
                        __(
                            'COM_NXPEASYCART_CATEGORIES_SEARCH_PLACEHOLDER',
                            'Search categories',
                            [],
                            'categoriesSearchPlaceholder'
                        )
                    "
                />
                <button
                    class="nxp-ec-btn nxp-ec-btn--icon"
                    type="button"
                    @click="emitRefresh"
                    :disabled="state.loading"
                    :title="__(
                        'COM_NXPEASYCART_CATEGORIES_REFRESH',
                        'Refresh',
                        [],
                        'categoriesRefresh'
                    )"
                    :aria-label="__(
                        'COM_NXPEASYCART_CATEGORIES_REFRESH',
                        'Refresh',
                        [],
                        'categoriesRefresh'
                    )"
                >
                    <i class="fa-solid fa-rotate"></i>
                    <span class="nxp-ec-sr-only">
                        {{
                            __(
                                "COM_NXPEASYCART_CATEGORIES_REFRESH",
                                "Refresh",
                                [],
                                "categoriesRefresh"
                            )
                        }}
                    </span>
                </button>
                <button
                    class="nxp-ec-btn nxp-ec-btn--primary"
                    type="button"
                    @click="startCreate"
                >
                    <i class="fa-solid fa-plus"></i>
                    {{
                        __(
                            "COM_NXPEASYCART_CATEGORIES_ADD",
                            "Add category",
                            [],
                            "categoriesAdd"
                        )
                    }}
                </button>
            </div>
        </header>

        <div v-if="state.error" class="nxp-ec-admin-alert nxp-ec-admin-alert--error">
            {{ state.error }}
        </div>

        <div v-if="state.loading" class="nxp-ec-admin-panel__body">
            <SkeletonLoader type="table" :rows="5" :columns="5" />
        </div>

        <div v-if="!state.loading" class="nxp-ec-admin-panel__body">
            <div class="nxp-ec-admin-panel__table">
                <table class="nxp-ec-admin-table">
                    <thead>
                        <tr>
                            <th scope="col">
                                {{
                                    __(
                                        "COM_NXPEASYCART_CATEGORIES_TABLE_TITLE",
                                        "Title",
                                        [],
                                        "categoriesTableTitle"
                                    )
                                }}
                            </th>
                            <th scope="col">
                                {{
                                    __(
                                        "COM_NXPEASYCART_CATEGORIES_TABLE_SLUG",
                                        "Slug",
                                        [],
                                        "categoriesTableSlug"
                                    )
                                }}
                            </th>
                            <th scope="col">
                                {{
                                    __(
                                        "COM_NXPEASYCART_CATEGORIES_TABLE_PARENT",
                                        "Parent",
                                        [],
                                        "categoriesTableParent"
                                    )
                                }}
                            </th>
                            <th scope="col">
                                {{
                                    __(
                                        "COM_NXPEASYCART_CATEGORIES_TABLE_SORT",
                                        "Sort",
                                        [],
                                        "categoriesTableSort"
                                    )
                                }}
                            </th>
                            <th scope="col">
                                {{
                                    __(
                                        "COM_NXPEASYCART_CATEGORIES_TABLE_USAGE",
                                        "Products",
                                        [],
                                        "categoriesTableUsage"
                                    )
                                }}
                            </th>
                            <th
                                scope="col"
                                class="nxp-ec-admin-table__actions"
                            ></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!state.items.length">
                            <td colspan="6">
                                {{
                                    __(
                                        "COM_NXPEASYCART_CATEGORIES_EMPTY",
                                        "No categories created yet.",
                                        [],
                                        "categoriesEmpty"
                                    )
                                }}
                            </td>
                        </tr>
                        <tr
                            v-for="category in hierarchicalItems"
                            :key="category.id"
                            :class="{ 'is-active': draft.id === category.id }"
                        >
                            <th scope="row" class="nxp-ec-admin-table__primary">
                                <span
                                    :style="{
                                        paddingLeft: `${category.depth * 1.5}rem`,
                                    }"
                                >
                                    {{ category.indentedTitle }}
                                </span>
                            </th>
                            <td :data-label="__('COM_NXPEASYCART_CATEGORIES_TABLE_SLUG', 'Slug')">{{ category.slug || "—" }}</td>
                            <td :data-label="__('COM_NXPEASYCART_CATEGORIES_TABLE_PARENT', 'Parent')">
                                {{
                                    category.path ||
                                    __(
                                        "COM_NXPEASYCART_CATEGORIES_PARENT_NONE",
                                        "None",
                                        [],
                                        "categoriesParentNone"
                                    )
                                }}
                            </td>
                            <td :data-label="__('COM_NXPEASYCART_CATEGORIES_TABLE_SORT', 'Sort')">{{ category.sort }}</td>
                            <td :data-label="__('COM_NXPEASYCART_CATEGORIES_TABLE_USAGE', 'Products')">{{ category.usage ?? 0 }}</td>
                            <td class="nxp-ec-admin-table__actions">
                                <button
                                    class="nxp-ec-btn nxp-ec-btn--link nxp-ec-btn--icon"
                                    type="button"
                                    @click="startEdit(category)"
                                    :title="__('JEDIT', 'Edit')"
                                    :aria-label="__('JEDIT', 'Edit')"
                                >
                                    <i class="fa-solid fa-pen-to-square"></i>
                                    <span class="nxp-ec-sr-only">{{ __("JEDIT", "Edit") }}</span>
                                </button>
                                <button
                                    class="nxp-ec-btn nxp-ec-btn--link nxp-ec-btn--danger nxp-ec-btn--icon"
                                    type="button"
                                    :disabled="state.deleting"
                                    @click="confirmDelete(category)"
                                    :title="__('COM_NXPEASYCART_REMOVE', 'Remove')"
                                    :aria-label="__('COM_NXPEASYCART_REMOVE', 'Remove')"
                                >
                                    <i class="fa-solid fa-trash"></i>
                                    <span class="nxp-ec-sr-only">{{ __("COM_NXPEASYCART_REMOVE", "Remove") }}</span>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div
                    class="nxp-ec-admin-pagination"
                    v-if="state.pagination.pages > 1"
                >
                    <button
                        class="nxp-ec-btn"
                        type="button"
                        :disabled="state.pagination.current <= 1"
                        @click="emitPage(state.pagination.current - 1)"
                    >
                        ‹
                    </button>
                    <span class="nxp-ec-admin-pagination__status">
                        {{ state.pagination.current }} /
                        {{ state.pagination.pages }}
                    </span>
                    <button
                        class="nxp-ec-btn"
                        type="button"
                        :disabled="
                            state.pagination.current >= state.pagination.pages
                        "
                        @click="emitPage(state.pagination.current + 1)"
                    >
                        ›
                    </button>
                </div>
            </div>

            <div
                v-if="state.lastUpdated"
                class="nxp-ec-admin-panel__metadata"
                :title="state.lastUpdated"
            >
                {{ __("COM_NXPEASYCART_LAST_UPDATED", "Last updated") }}:
                {{ formatTimestamp(state.lastUpdated) }}
            </div>

            <div
                v-if="formOpen"
                class="nxp-ec-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="category-modal-title"
                @keydown.esc="cancelEdit"
            >
                <div class="nxp-ec-modal__backdrop" @click="cancelEdit"></div>
                <div class="nxp-ec-modal__dialog">
                    <header class="nxp-ec-modal__header">
                        <h3 id="category-modal-title" class="nxp-ec-modal__title">
                            {{
                                draft.id
                                    ? __("JEDIT", "Edit")
                                    : __(
                                          "COM_NXPEASYCART_CATEGORIES_ADD",
                                          "Add category",
                                          [],
                                          "categoriesAdd"
                                      )
                            }}
                        </h3>
                        <button
                            class="nxp-ec-link-button nxp-ec-btn--icon nxp-ec-modal__close-btn"
                            type="button"
                            @click="cancelEdit"
                            :title="__('JCLOSE', 'Close')"
                            :aria-label="__('JCLOSE', 'Close')"
                        >
                            <i class="fa-solid fa-circle-xmark"></i>
                            <span class="nxp-ec-sr-only">{{ __("JCLOSE", "Close") }}</span>
                        </button>
                    </header>

                    <form
                        class="nxp-ec-form"
                        @submit.prevent="submitForm"
                        autocomplete="off"
                    >
                    <div
                        v-if="
                            Array.isArray(state.validationErrors) &&
                            state.validationErrors.length
                        "
                        class="nxp-ec-admin-alert nxp-ec-admin-alert--error"
                    >
                        <ul>
                            <li
                                v-for="(
                                    message, index
                                ) in state.validationErrors"
                                :key="index"
                            >
                                {{ message }}
                            </li>
                        </ul>
                    </div>

                    <div class="nxp-ec-form-field">
                        <label class="nxp-ec-form-label" for="category-title">
                            {{
                                __(
                                    "COM_NXPEASYCART_CATEGORIES_FORM_TITLE",
                                    "Name",
                                    [],
                                    "categoriesFormTitle"
                                )
                            }}
                        </label>
                        <input
                            id="category-title"
                            class="nxp-ec-form-input"
                            type="text"
                            v-model.trim="draft.title"
                            required
                            maxlength="255"
                        />
                    </div>

                    <div class="nxp-ec-form-field">
                        <label class="nxp-ec-form-label" for="category-slug">
                            {{
                                __(
                                    "COM_NXPEASYCART_CATEGORIES_FORM_SLUG",
                                    "Slug",
                                    [],
                                    "categoriesFormSlug"
                                )
                            }}
                        </label>
                        <input
                            id="category-slug"
                            class="nxp-ec-form-input"
                            type="text"
                            v-model.trim="draft.slug"
                            maxlength="190"
                            @input="onSlugInput"
                        />
                    </div>

                    <div class="nxp-ec-form-field">
                        <label class="nxp-ec-form-label" for="category-parent">
                            {{
                                __(
                                    "COM_NXPEASYCART_CATEGORIES_FORM_PARENT",
                                    "Parent category",
                                    [],
                                    "categoriesFormParent"
                                )
                            }}
                        </label>
                        <select
                            id="category-parent"
                            class="nxp-ec-form-select"
                            v-model.number="draft.parent_id"
                        >
                            <option value="">
                                {{
                                    __(
                                        "COM_NXPEASYCART_CATEGORIES_PARENT_NONE",
                                        "None",
                                        [],
                                        "categoriesParentNone"
                                    )
                                }}
                            </option>
                            <option
                                v-for="option in parentOptionList"
                                :key="option.id"
                                :value="option.id"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </div>

                    <div class="nxp-ec-form-field">
                        <label class="nxp-ec-form-label" for="category-sort">
                            {{
                                __(
                                    "COM_NXPEASYCART_CATEGORIES_FORM_SORT",
                                    "Sort order",
                                    [],
                                    "categoriesFormSort"
                                )
                            }}
                        </label>
                        <input
                            id="category-sort"
                            class="nxp-ec-form-input"
                            type="number"
                            v-model.number="draft.sort"
                            min="0"
                            step="1"
                        />
                    </div>

                    <footer class="nxp-ec-modal__actions">
                        <button
                            class="nxp-ec-btn nxp-ec-btn--primary"
                            type="submit"
                            :disabled="state.saving"
                        >
                            <i class="fa-solid fa-floppy-disk"></i>
                            {{
                                __(
                                    "COM_NXPEASYCART_CATEGORIES_SAVE",
                                    "Save category",
                                    [],
                                    "categoriesSave"
                                )
                            }}
                        </button>
                        <button
                            class="nxp-ec-btn nxp-ec-btn--ghost"
                            type="button"
                            @click="cancelEdit"
                        >
                            <i class="fa-solid fa-ban"></i>
                            {{
                                __(
                                    "COM_NXPEASYCART_CATEGORIES_CANCEL",
                                    "Cancel",
                                    [],
                                    "categoriesCancel"
                                )
                            }}
                        </button>
                    </footer>
                    </form>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, reactive, ref, watch } from "vue";
import SkeletonLoader from "./SkeletonLoader.vue";

const props = defineProps({
    state: {
        type: Object,
        required: true,
    },
    translate: {
        type: Function,
        required: true,
    },
    loadOptions: {
        type: Function,
        default: null,
    },
});

const emit = defineEmits(["refresh", "search", "page", "save", "delete"]);

const __ = props.translate;

const formOpen = ref(false);
const slugEdited = ref(false);
const parentOptions = ref([]);

const buildHierarchy = (sourceItems) => {
    const itemsArray = Array.isArray(sourceItems) ? sourceItems : [];

    const normalized = itemsArray
        .map((item) => ({
            ...item,
            id: Number.parseInt(item?.id ?? 0, 10) || 0,
            parent_id:
                item?.parent_id != null
                    ? Number.parseInt(item.parent_id, 10) || 0
                    : 0,
            sort: Number.parseInt(item?.sort ?? 0, 10) || 0,
            title: String(item?.title ?? "").trim(),
            slug: String(item?.slug ?? "").trim(),
            usage: item?.usage ?? 0,
        }))
        .filter((item) => item.id > 0 || item.title !== "");

    const idSet = new Set(normalized.map((item) => item.id));

    normalized.forEach((item) => {
        if (!idSet.has(item.parent_id)) {
            item.parent_id = 0;
        }
    });

    const byParent = new Map();
    const index = new Map();

    normalized.forEach((item) => {
        index.set(item.id, item);
        const parent = item.parent_id || 0;

        if (!byParent.has(parent)) {
            byParent.set(parent, []);
        }

        byParent.get(parent).push(item);
    });

    const sortChildren = (children) =>
        children.sort(
            (a, b) =>
                (a.sort ?? 0) - (b.sort ?? 0) ||
                a.title.localeCompare(b.title, undefined, {
                    sensitivity: "base",
                })
        );

    byParent.forEach(sortChildren);

    const visited = new Set();
    const result = [];

    const visitItem = (item, depth, chain) => {
        if (!item) {
            return;
        }

        if (visited.has(item.id)) {
            return;
        }

        if (chain.includes(item.id)) {
            return;
        }

        const ancestors = [...chain];

        const parentTitles = ancestors
            .map((id) => index.get(id)?.title)
            .filter(Boolean);

        const node = {
            ...item,
            depth,
            path: parentTitles.join(" / "),
            ancestors,
            indentedTitle:
                depth > 0 ? `${"— ".repeat(depth)}${item.title}` : item.title,
        };

        result.push(node);
        visited.add(item.id);

        const children = byParent.get(item.id) || [];
        children.forEach((child) =>
            visitItem(child, depth + 1, [...chain, item.id])
        );
    };

    const roots = normalized.filter(
        (item) => item.parent_id === 0 || !idSet.has(item.parent_id)
    );

    sortChildren(roots);

    roots.forEach((root) => visitItem(root, 0, []));

    normalized.forEach((item) => {
        if (!visited.has(item.id)) {
            visitItem(item, 0, []);
        }
    });

    return result;
};

const hierarchicalItems = computed(() =>
    buildHierarchy(props.state?.items ?? [])
);

const defaultDraft = () => ({
    id: 0,
    title: "",
    slug: "",
    parent_id: null,
    sort: 0,
});

const draft = reactive(defaultDraft());

const parentOptionList = computed(() => {
    const baseOptions =
        (Array.isArray(parentOptions.value) && parentOptions.value.length
            ? parentOptions.value
            : props.state?.items) ?? [];

    return buildHierarchy(baseOptions)
        .filter(
            (option) =>
                option.id > 0 &&
                option.id !== draft.id &&
                !(
                    Array.isArray(option.ancestors) &&
                    option.ancestors.includes(draft.id)
                )
        )
        .map((option) => ({
            id: option.id,
            title: option.title,
            label:
                option.depth > 0
                    ? `${"— ".repeat(option.depth)}${option.title}`
                    : option.title,
        }));
});

const resetDraft = () => {
    Object.assign(draft, defaultDraft());
    slugEdited.value = false;
};

const slugify = (value) => {
    if (!value) {
        return "";
    }

    return value
        .toString()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "")
        .substring(0, 190);
};

const onSlugInput = () => {
    slugEdited.value = true;
    draft.slug = slugify(draft.slug);
};

watch(
    () => draft.title,
    (title) => {
        if (!slugEdited.value) {
            draft.slug = slugify(title);
        }
    }
);

const fetchParentOptions = async (excludeId = 0) => {
    if (typeof props.loadOptions !== "function") {
        parentOptions.value = Array.isArray(props.state.items)
            ? [...props.state.items]
            : [];
        return;
    }

    try {
        const options = await props.loadOptions();
        parentOptions.value = Array.isArray(options) ? [...options] : [];
    } catch (error) {
        parentOptions.value = [];
    }

    parentOptions.value = parentOptions.value.filter((option) => {
        const id = Number.parseInt(option?.id ?? option?.value ?? 0, 10) || 0;
        return id !== excludeId;
    });
};

const startCreate = async () => {
    resetDraft();
    await fetchParentOptions();
    formOpen.value = true;
};

const startEdit = async (category) => {
    if (!category) {
        return;
    }

    draft.id = Number.parseInt(category.id ?? 0, 10) || 0;
    draft.title = String(category.title ?? "").trim();
    draft.slug = String(category.slug ?? "").trim();
    draft.parent_id =
        category.parent_id != null
            ? Number.parseInt(category.parent_id, 10) || null
            : null;
    draft.sort = Number.parseInt(category.sort ?? 0, 10) || 0;
    slugEdited.value = Boolean(draft.slug);

    await fetchParentOptions(draft.id);

    formOpen.value = true;
};

const cancelEdit = () => {
    formOpen.value = false;
    resetDraft();
};

const emitRefresh = () => {
    emit("refresh");
};

const emitSearch = () => {
    emit("search");
};

const emitPage = (page) => {
    emit("page", page);
};

const submitForm = () => {
    const payload = {
        id: draft.id || undefined,
        title: draft.title.trim(),
        slug: draft.slug.trim(),
        parent_id: draft.parent_id || null,
        sort: Number.isFinite(draft.sort) ? draft.sort : 0,
    };

    emit("save", payload);
};

const confirmDelete = (category) => {
    if (!category || !category.id) {
        return;
    }

    const message = __(
        "COM_NXPEASYCART_CATEGORIES_DELETE_CONFIRM",
        "Remove selected categories?",
        [],
        "categoriesDeleteConfirm"
    );

    if (typeof window !== "undefined" && !window.confirm(message)) {
        return;
    }

    emit("delete", [category.id]);
};

const formatTimestamp = (timestamp) => {
    if (!timestamp) {
        return "";
    }

    try {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = now - date;
        const seconds = Math.floor(diff / 1000);
        const minutes = Math.floor(seconds / 60);
        const hours = Math.floor(minutes / 60);

        if (seconds < 60) {
            return __("COM_NXPEASYCART_TIME_SECONDS_AGO", "just now");
        } else if (minutes < 60) {
            return __(
                "COM_NXPEASYCART_TIME_MINUTES_AGO",
                "%s minutes ago",
                [minutes]
            );
        } else if (hours < 24) {
            return __(
                "COM_NXPEASYCART_TIME_HOURS_AGO",
                "%s hours ago",
                [hours]
            );
        } else {
            return date.toLocaleString();
        }
    } catch (error) {
        return timestamp;
    }
};

watch(
    () => props.state?.saving,
    (saving, previous) => {
        if (previous && !saving) {
            const hasErrors =
                (props.state?.validationErrors ?? []).length > 0 ||
                props.state?.error;

            if (!hasErrors) {
                formOpen.value = false;
                resetDraft();
            }
        }
    }
);
</script>

<style scoped>
.nxp-ec-modal__close-btn {
    width: auto;
    height: auto;
    padding: 0;
}

.nxp-ec-modal__close-btn i {
    font-size: 1.25rem;
}

.nxp-ec-modal__close-btn:hover {
    text-decoration: none;
    opacity: 0.7;
}
</style>
