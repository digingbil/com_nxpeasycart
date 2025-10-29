import { createApp, onMounted, reactive, ref } from 'vue';
import './admin-main.css';
import { fetchProducts } from './api.js';

const mount = document.getElementById('nxp-admin-app');

if (!mount) {
    console.warn('[NXP Easy Cart] Admin mount point not found.');
} else {
    const csrfToken = mount.getAttribute('data-csrf-token') ?? '';
    const productsEndpoint = mount.getAttribute('data-products-endpoint') ?? '';

    const dataset = mount.dataset;

    const translations = {
        appTitle: dataset.appTitle || 'NXP Easy Cart',
        appLead: dataset.appLead || 'Manage your storefront from one place.',
        productsPanelTitle: dataset.productsPanelTitle || 'Products',
        productsPanelLead: dataset.productsPanelLead || 'Manage products from a single dashboard.',
        refresh: dataset.productsRefresh || 'Refresh',
        searchPlaceholder: dataset.productsSearchPlaceholder || 'Search products',
        loading: dataset.productsLoading || 'Loading products…',
        empty: dataset.productsEmpty || 'No products found.',
        statusActive: dataset.statusActive || 'Active',
        statusInactive: dataset.statusInactive || 'Inactive',
    };

    const App = {
        name: 'NxpEasyCartAdmin',
        setup() {
            const state = reactive({
                loading: false,
                error: '',
                items: [],
                pagination: {
                    total: 0,
                    limit: 20,
                    pages: 0,
                    current: 1,
                },
                search: '',
            });

            const abortRef = ref(null);

            const loadProducts = async () => {
                if (!productsEndpoint) {
                    state.error = 'Products endpoint unavailable.';
                    return;
                }

                state.loading = true;
                state.error = '';

                if (abortRef.value) {
                    abortRef.value.abort();
                }

                abortRef.value = new AbortController();

                try {
                    const { items, pagination } = await fetchProducts({
                        endpoint: productsEndpoint,
                        token: csrfToken,
                        signal: abortRef.value.signal,
                        limit: state.pagination.limit,
                        start: Math.max(0, (state.pagination.current - 1) * state.pagination.limit),
                        search: state.search.trim(),
                    });

                    state.items = items;
                    state.pagination = {
                        ...state.pagination,
                        ...pagination,
                        current: pagination.current && pagination.current > 0 ? pagination.current : 1,
                    };
                } catch (error) {
                    state.error = error.message ?? 'Unknown error';
                } finally {
                    state.loading = false;
                    abortRef.value = null;
                }
            };

            const onRefresh = () => {
                state.pagination.current = 1;
                loadProducts();
            };

            const onSearch = () => {
                state.pagination.current = 1;
                loadProducts();
            };

            onMounted(() => {
                loadProducts();
            });

            return {
                state,
                onRefresh,
                onSearch,
                translations,
            };
        },
        template: `
            <section class="nxp-admin-app__shell">
                <header class="nxp-admin-app__header">
                    <h1 class="nxp-admin-app__title">{{ translations.appTitle }}</h1>
                    <p class="nxp-admin-app__lead">{{ translations.appLead }}</p>
                </header>

                <section class="nxp-admin-panel">
                    <header class="nxp-admin-panel__header">
                        <div>
                            <h2 class="nxp-admin-panel__title">{{ translations.productsPanelTitle }}</h2>
                            <p class="nxp-admin-panel__lead">{{ translations.productsPanelLead }}</p>
                        </div>
                        <div class="nxp-admin-panel__actions">
                            <input
                                type="search"
                                class="nxp-admin-search"
                                :placeholder="translations.searchPlaceholder"
                                v-model="state.search"
                                @keyup.enter="onSearch"
                                aria-label="Search products"
                            />
                            <button class="nxp-btn" type="button" @click="onRefresh" :disabled="state.loading">
                                {{ translations.refresh }}
                            </button>
                        </div>
                    </header>

                    <div v-if="state.error" class="nxp-admin-alert nxp-admin-alert--error">
                        {{ state.error }}
                    </div>

                    <div v-else-if="state.loading" class="nxp-admin-panel__loading">
                        {{ translations.loading }}
                    </div>

                    <table v-else class="nxp-admin-table" aria-describedby="nxp-products-caption">
                        <caption id="nxp-products-caption" class="visually-hidden">Products</caption>
                        <thead>
                            <tr>
                                <th scope="col">ID</th>
                                <th scope="col">Title</th>
                                <th scope="col">Slug</th>
                                <th scope="col">Status</th>
                                <th scope="col">Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!state.items.length">
                                <td colspan="5" class="nxp-admin-table__empty">{{ translations.empty }}</td>
                            </tr>
                            <tr v-for="item in state.items" :key="item.id">
                                <td>{{ item.id }}</td>
                                <td>{{ item.title }}</td>
                                <td>{{ item.slug }}</td>
                                <td>
                                    <span :class="['nxp-status', item.active ? 'nxp-status--active' : 'nxp-status--inactive']">
                                        {{ item.active ? translations.statusActive : translations.statusInactive }}
                                    </span>
                                </td>
                                <td>{{ item.created }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </section>
        `,
    };

    createApp(App).mount(mount);
}
