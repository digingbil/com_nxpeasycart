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

        if (!response.ok) {
            throw new Error(`Request failed with status ${response.status}`);
        }

        const payload = await response.json();

        if (payload.success === false) {
            throw new Error(payload.message || 'Unknown API error');
        }

        return payload;
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
}

export const createApiClient = (config) => new ApiClient(config);

export default ApiClient;
