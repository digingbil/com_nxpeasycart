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

        return {
            items: body.data ?? [],
            pagination: body.pagination ?? { total: 0, limit, pages: 0, current: 0 },
        };
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
        const url = this.appendId(endpoint, id);
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
    appendId(endpoint, id) {
        const origin = typeof window !== 'undefined' ? window.location.origin : 'http://localhost';
        const url = new URL(endpoint, origin);
        url.searchParams.set('id', String(id));

        return `${url.pathname}?${url.searchParams.toString()}`;
    }
}

export const createApiClient = (config) => new ApiClient(config);

export default ApiClient;
