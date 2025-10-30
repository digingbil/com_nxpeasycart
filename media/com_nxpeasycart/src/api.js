class ApiClient {
    /**
     * @param {Object} config
     * @param {string} config.token CSRF token required by Joomla
     */
    constructor({ token = '' } = {}) {
        this.token = token;
    }

    /**
     * Perform a request with default Joomla headers and error handling.
     *
     * @param {string} url Fully resolved request URL
     * @param {RequestInit} options Fetch configuration
     * @returns {Promise<any>}
     */
    async request(url, options = {}) {
        const config = {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-Token': this.token,
            },
            credentials: 'same-origin',
            ...options,
        };

        config.headers = {
            ...config.headers,
            ...(options.headers || {}),
        };

        const response = await fetch(url, config);
        let payload = null;

        const raw = await response.text();

        if (raw) {
            try {
                payload = JSON.parse(raw);
            } catch (error) {
                // Ignore JSON parse errors; handled below
            }
        }

        if (!response.ok) {
            const error = new Error(payload?.message || `Request failed with status ${response.status}`);
            error.code = response.status;
            error.details = payload?.data || payload?.errors || null;

            throw error;
        }

        if (payload?.success === false) {
            const error = new Error(payload.message || 'Unknown API error');
            error.details = payload.data || payload.errors || null;

            throw error;
        }

        return payload ?? {};
    }

    /**
     * GET request helper.
     */
    get(url, options = {}) {
        return this.request(url, { ...options, method: 'GET' });
    }

    /**
     * POST request helper.
     */
    post(url, body, options = {}) {
        const headers = {
            'Content-Type': 'application/json',
            ...(options.headers || {}),
        };

        return this.request(url, {
            ...options,
            method: 'POST',
            headers,
            body: JSON.stringify(body),
        });
    }

    /**
     * PUT request helper.
     */
    put(url, body, options = {}) {
        const headers = {
            'Content-Type': 'application/json',
            ...(options.headers || {}),
        };

        return this.request(url, {
            ...options,
            method: 'PUT',
            headers,
            body: JSON.stringify(body),
        });
    }

    /**
     * DELETE request helper.
     */
    delete(url, options = {}) {
        return this.request(url, { ...options, method: 'DELETE' });
    }

    /**
     * Domain-specific helper: fetch paginated products.
     */
    async fetchProducts({ endpoint, limit = 20, start = 0, search = '', signal }) {
        const params = new URLSearchParams({
            limit: String(limit),
            start: String(start),
        });

        if (search) {
            params.set('search', search);
        }

        const url = `${endpoint}&${params.toString()}`;

        const payload = await this.get(url, { signal });
        const body = payload.data ?? {};
        const items = body.items ?? body.data ?? [];
        const pagination = body.pagination ?? { total: 0, limit, pages: 0, current: 0 };

        return {
            items,
            pagination,
        };
    }

    /**
     * Fetch orders with pagination and filters.
     */
    async fetchOrders({ endpoint, limit = 20, start = 0, search = '', state = '', signal }) {
        const url = this.mergeParams(endpoint, {
            limit,
            start,
            search: search || undefined,
            state: state || undefined,
        });

        const payload = await this.get(url, { signal });
        const body = payload.data ?? {};
        const items = body.items ?? body.data ?? [];
        const pagination = body.pagination ?? { total: 0, limit, pages: 0, current: 0 };

        return {
            items,
            pagination,
        };
    }

    /**
     * Retrieve a single order by id or number.
     */
    async fetchOrder({ endpoint, id = null, orderNumber = '' }) {
        const url = this.mergeParams(endpoint, {
            id: id || undefined,
            order_no: orderNumber || undefined,
        });

        const payload = await this.get(url);

        return payload.data?.order ?? null;
    }

    /**
     * Transition an order to a new state.
     */
    async transitionOrder({ endpoint, id, state }) {
        const url = this.mergeParams(endpoint, { id });
        const payload = await this.post(url, { state });

        return payload.data?.order ?? null;
    }

    /**
     * Create a product.
     */
    async createProduct({ endpoint, data }) {
        const payload = await this.post(endpoint, data);

        return payload.data?.item ?? null;
    }

    /**
     * Update a product.
     */
    async updateProduct({ endpoint, id, data }) {
        const url = this.mergeParams(endpoint, { id });
        const payload = await this.put(url, data);

        return payload.data?.item ?? null;
    }

    /**
     * Delete products.
     */
    async deleteProducts({ endpoint, ids }) {
        const payload = await this.delete(endpoint, {
            body: JSON.stringify({ ids }),
            headers: {
                'Content-Type': 'application/json',
            },
        });

        return payload.data?.deleted ?? [];
    }

    /**
     * Append an id query parameter to endpoint.
     */
    mergeParams(endpoint, params = {}) {
        const search = new URLSearchParams();

        Object.entries(params).forEach(([key, value]) => {
            if (value === undefined || value === null || value === '') {
                return;
            }

            search.set(key, String(value));
        });

        if (!search.toString()) {
            return endpoint;
        }

        return `${endpoint}${endpoint.includes('?') ? '&' : '?'}${search.toString()}`;
    }
}

export const createApiClient = (config) => new ApiClient(config);

export default ApiClient;
