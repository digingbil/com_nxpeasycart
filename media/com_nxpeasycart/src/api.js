class ApiClient {
    /**
     * @param {Object} config
     * @param {string} config.token CSRF token required by Joomla
     */
    constructor({ token = "" } = {}) {
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
            method: "GET",
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                Accept: "application/json",
            },
            credentials: "same-origin",
            cache: "no-store",
            ...options,
        };

        config.headers = {
            ...config.headers,
            ...(options.headers || {}),
        };

        const method = String(config.method || "GET").toUpperCase();
        let requestUrl = url;

        if (this.token) {
            config.headers["X-CSRF-Token"] = this.token;

            if (method !== "GET") {
                requestUrl = this.ensureTokenOnUrl(requestUrl);
            }
        }

        const response = await fetch(requestUrl, config);
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
            const error = new Error(
                payload?.message ||
                    `Request failed with status ${response.status}`
            );
            error.code = response.status;
            error.details = payload?.data || payload?.errors || null;

            throw error;
        }

        if (payload?.success === false) {
            const error = new Error(payload.message || "Unknown API error");
            error.details = payload.data || payload.errors || null;

            throw error;
        }

        return payload ?? {};
    }

    /**
     * Ensure the CSRF token is attached to the request URL.
     */
    ensureTokenOnUrl(url) {
        if (!this.token) {
            return url;
        }

        const tokenParam = encodeURIComponent(this.token);

        try {
            const origin =
                typeof window !== "undefined" && window.location
                    ? window.location.origin
                    : undefined;
            const parsed = new URL(url, origin);

            if (!parsed.searchParams.has(this.token)) {
                parsed.searchParams.set(this.token, "1");
            }

            return parsed.toString();
        } catch (error) {
            if (url.includes(`${tokenParam}=`)) {
                return url;
            }

            const separator = url.includes("?") ? "&" : "?";

            return `${url}${separator}${tokenParam}=1`;
        }
    }

    /**
     * GET request helper.
     */
    get(url, options = {}) {
        return this.request(url, { ...options, method: "GET" });
    }

    /**
     * POST request helper.
     */
    post(url, body, options = {}) {
        const headers = {
            "Content-Type": "application/json",
            ...(options.headers || {}),
        };

        return this.request(url, {
            ...options,
            method: "POST",
            headers,
            body: JSON.stringify(body),
        });
    }

    /**
     * PUT request helper.
     */
    put(url, body, options = {}) {
        const headers = {
            "Content-Type": "application/json",
            ...(options.headers || {}),
        };

        return this.request(url, {
            ...options,
            method: "PUT",
            headers,
            body: JSON.stringify(body),
        });
    }

    /**
     * DELETE request helper.
     */
    delete(url, options = {}) {
        return this.request(url, { ...options, method: "DELETE" });
    }

    /**
     * Domain-specific helper: fetch paginated products.
     */
    async fetchProducts({
        endpoint,
        limit = 20,
        start = 0,
        search = "",
        signal,
    }) {
        const params = new URLSearchParams({
            limit: String(limit),
            start: String(start),
        });

        if (search) {
            params.set("search", search);
        }

        const url = `${endpoint}&${params.toString()}`;

        const payload = await this.get(url, { signal });
        const body = payload.data ?? {};
        const items = body.items ?? body.data ?? [];
        const pagination = body.pagination ?? {
            total: 0,
            limit,
            pages: 0,
            current: 0,
        };

        return {
            items,
            pagination,
        };
    }

    async fetchCategories({
        endpoint,
        limit = 20,
        start = 0,
        search = "",
        signal,
    }) {
        const url = this.mergeParams(endpoint, {
            limit,
            start,
            search: search || undefined,
        });

        const payload = await this.get(url, { signal });
        const body = payload.data ?? {};

        return {
            items: body.items ?? [],
            pagination: body.pagination ?? {
                total: 0,
                limit,
                pages: 0,
                current: 0,
            },
        };
    }

    /**
     * Fetch orders with pagination and filters.
     */
    async fetchOrders({
        endpoint,
        limit = 20,
        start = 0,
        search = "",
        state = "",
        signal,
    }) {
        const url = this.mergeParams(endpoint, {
            limit,
            start,
            search: search || undefined,
            state: state || undefined,
        });

        const payload = await this.get(url, { signal });
        const body = payload.data ?? {};
        const items = body.items ?? body.data ?? [];
        const pagination = body.pagination ?? {
            total: 0,
            limit,
            pages: 0,
            current: 0,
        };

        return {
            items,
            pagination,
        };
    }

    async fetchCustomers({
        endpoint,
        limit = 20,
        start = 0,
        search = "",
        signal,
    }) {
        const url = this.mergeParams(endpoint, {
            limit,
            start,
            search: search || undefined,
        });

        const payload = await this.get(url, { signal });
        const body = payload.data ?? {};
        const items = body.items ?? [];
        const pagination = body.pagination ?? {
            total: 0,
            limit,
            pages: 0,
            current: 0,
        };

        return {
            items,
            pagination,
        };
    }

    async fetchCustomer({ endpoint, email }) {
        const url = this.mergeParams(endpoint, { email });
        const payload = await this.get(url);

        return payload.data?.customer ?? null;
    }

    async fetchCoupons({
        endpoint,
        limit = 20,
        start = 0,
        search = "",
        signal,
    }) {
        const url = this.mergeParams(endpoint, {
            limit,
            start,
            search: search || undefined,
        });

        const payload = await this.get(url, { signal });
        const body = payload.data ?? {};

        return {
            items: body.items ?? [],
            pagination: body.pagination ?? {
                total: 0,
                limit,
                pages: 0,
                current: 0,
            },
        };
    }

    async createCoupon({ endpoint, data }) {
        const payload = await this.post(endpoint, data);

        return payload.data?.coupon ?? null;
    }

    async updateCoupon({ endpoint, id, data }) {
        const url = this.mergeParams(endpoint, { id });
        const payload = await this.put(url, data);

        return payload.data?.coupon ?? null;
    }

    async deleteCoupons({ endpoint, ids }) {
        const payload = await this.delete(endpoint, {
            body: JSON.stringify({ ids }),
            headers: {
                "Content-Type": "application/json",
            },
        });

        return payload.data?.deleted ?? 0;
    }

    async fetchTaxRates({
        endpoint,
        limit = 20,
        start = 0,
        search = "",
        signal,
    }) {
        const url = this.mergeParams(endpoint, {
            limit,
            start,
            search: search || undefined,
        });

        const payload = await this.get(url, { signal });
        const body = payload.data ?? {};

        return {
            items: body.items ?? [],
            pagination: body.pagination ?? {
                total: 0,
                limit,
                pages: 0,
                current: 0,
            },
        };
    }

    async createTaxRate({ endpoint, data }) {
        const payload = await this.post(endpoint, data);

        return payload.data?.rate ?? null;
    }

    async updateTaxRate({ endpoint, id, data }) {
        const url = this.mergeParams(endpoint, { id });
        const payload = await this.put(url, data);

        return payload.data?.rate ?? null;
    }

    async deleteTaxRates({ endpoint, ids }) {
        const payload = await this.delete(endpoint, {
            body: JSON.stringify({ ids }),
            headers: {
                "Content-Type": "application/json",
            },
        });

        return payload.data?.deleted ?? 0;
    }

    async fetchShippingRules({
        endpoint,
        limit = 20,
        start = 0,
        search = "",
        signal,
    }) {
        const url = this.mergeParams(endpoint, {
            limit,
            start,
            search: search || undefined,
        });

        const payload = await this.get(url, { signal });
        const body = payload.data ?? {};

        return {
            items: body.items ?? [],
            pagination: body.pagination ?? {
                total: 0,
                limit,
                pages: 0,
                current: 0,
            },
        };
    }

    async createShippingRule({ endpoint, data }) {
        const payload = await this.post(endpoint, data);

        return payload.data?.rule ?? null;
    }

    async updateShippingRule({ endpoint, id, data }) {
        const url = this.mergeParams(endpoint, { id });
        const payload = await this.put(url, data);

        return payload.data?.rule ?? null;
    }

    async deleteShippingRules({ endpoint, ids }) {
        const payload = await this.delete(endpoint, {
            body: JSON.stringify({ ids }),
            headers: {
                "Content-Type": "application/json",
            },
        });

        return payload.data?.deleted ?? 0;
    }

    async fetchLogs({
        endpoint,
        limit = 20,
        start = 0,
        search = "",
        entity = "",
        signal,
    }) {
        const url = this.mergeParams(endpoint, {
            limit,
            start,
            search: search || undefined,
            entity: entity || undefined,
        });

        const payload = await this.get(url, { signal });
        const body = payload.data ?? {};

        return {
            items: body.items ?? [],
            pagination: body.pagination ?? {
                total: 0,
                limit,
                pages: 0,
                current: 0,
            },
        };
    }

    /**
     * Retrieve dashboard metrics and checklist.
     */
    async fetchDashboard({ endpoint }) {
        if (!endpoint) {
            return {};
        }

        const payload = await this.get(endpoint);

        return payload.data ?? {};
    }

    async fetchSettings({ endpoint }) {
        if (!endpoint) {
            return {};
        }

        const payload = await this.get(endpoint);

        return payload.data?.settings ?? {};
    }

    async updateSettings({ endpoint, data }) {
        const payload = await this.put(endpoint, data);

        return payload.data?.settings ?? {};
    }

    /**
     * Retrieve a single order by id or number.
     */
    async fetchOrder({ endpoint, id = null, orderNumber = "" }) {
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
     * Transition multiple orders in one request.
     */
    async bulkTransitionOrders({ endpoint, ids, state }) {
        const body = {
            ids,
            state,
        };

        const payload = await this.post(endpoint, body);

        return payload.data ?? {};
    }

    /**
     * Append an admin note to an order.
     */
    async addOrderNote({ endpoint, id, message }) {
        const payload = await this.post(endpoint, { id, message });

        return payload.data?.order ?? null;
    }

    /**
     * Fetch invoice (PDF) for an order.
     */
    async fetchOrderInvoice({ endpoint, id, orderNumber }) {
        const body = {
            id: id || undefined,
            order_no: orderNumber || undefined,
        };

        const payload = await this.post(endpoint, body);

        return payload.data?.invoice ?? null;
    }

    /**
     * Export orders to CSV.
     */
    async exportOrders({ endpoint, search = "", state = "" }) {
        const url = this.mergeParams(endpoint, {
            search: search || undefined,
            state: state || undefined,
        });

        const payload = await this.get(url);

        return payload.data?.export ?? null;
    }

    /**
     * Update order tracking metadata.
     */
    async updateOrderTracking({ endpoint, id, tracking }) {
        const payload = await this.post(endpoint, {
            id,
            ...tracking,
        });

        return payload.data?.order ?? null;
    }

    /**
     * Send order email notification.
     */
    async sendOrderEmail({ endpoint, id, type }) {
        const payload = await this.post(endpoint, {
            id,
            type,
        });

        return payload.data?.order ?? null;
    }

    /**
     * Record a manual transaction for COD or Bank Transfer orders.
     */
    async recordTransaction({ endpoint, id, amountCents, reference, note }) {
        const payload = await this.post(endpoint, {
            id,
            amount_cents: amountCents,
            reference: reference || undefined,
            note: note || undefined,
        });

        return payload.data?.order ?? null;
    }

    /**
     * Resend downloads email for a digital order.
     */
    async resendDownloads({ endpoint, id }) {
        const payload = await this.post(endpoint, { id });

        return payload.data?.order ?? null;
    }

    /**
     * Reset download count for a specific download record.
     */
    async resetDownload({ endpoint, downloadId, orderId }) {
        const payload = await this.post(endpoint, {
            download_id: downloadId,
            order_id: orderId || undefined,
        });

        return payload.data ?? null;
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
        const payload = await this.post(
            endpoint,
            { ids },
            {
                headers: {
                    "Content-Type": "application/json",
                },
            }
        );

        return payload.data?.deleted ?? [];
    }

    async fetchDigitalFiles({ endpoint, productId, variantId = null, signal }) {
        const url = this.mergeParams(endpoint, {
            product_id: productId,
            variant_id: variantId || undefined,
        });

        const payload = await this.get(url, { signal });

        return payload.data?.files ?? [];
    }

    async uploadDigitalFile({ endpoint, productId, file, variantId = null, version = "1.0" }) {
        const formData = new FormData();
        formData.append("product_id", productId);

        if (variantId) {
            formData.append("variant_id", variantId);
        }

        if (version) {
            formData.append("version", version);
        }

        formData.append("file", file);

        const payload = await this.request(endpoint, {
            method: "POST",
            body: formData,
        });

        return payload.data?.file ?? null;
    }

    async deleteDigitalFile({ endpoint, fileId }) {
        const payload = await this.post(endpoint, { id: fileId });

        return payload.data?.deleted ?? null;
    }

    async createCategory({ endpoint, data }) {
        const payload = await this.post(endpoint, data);

        return payload.data?.item ?? null;
    }

    async updateCategory({ endpoint, id, data }) {
        const url = this.mergeParams(endpoint, { id });
        const payload = await this.put(url, data);

        return payload.data?.item ?? null;
    }

    async deleteCategories({ endpoint, ids }) {
        const payload = await this.delete(endpoint, {
            body: JSON.stringify({ ids }),
            headers: {
                "Content-Type": "application/json",
            },
        });

        return payload.data?.deleted ?? 0;
    }

    /**
     * Export customer data for GDPR compliance.
     * @param {Object} params
     * @param {string} params.endpoint - The GDPR export endpoint
     * @param {string} params.email - Customer email to export
     * @returns {Promise<Object>} Export data
     */
    async gdprExport({ endpoint, email }) {
        const url = this.mergeParams(endpoint, { email });
        const payload = await this.get(url);

        return payload.data?.export ?? null;
    }

    /**
     * Anonymise customer data for GDPR compliance.
     * @param {Object} params
     * @param {string} params.endpoint - The GDPR anonymise endpoint
     * @param {string} params.email - Customer email to anonymise
     * @returns {Promise<Object>} Result with affected count
     */
    async gdprAnonymise({ endpoint, email }) {
        const payload = await this.post(endpoint, { email });

        return {
            affected: payload.data?.affected ?? 0,
            message: payload.data?.message ?? "",
        };
    }

    /**
     * Append an id query parameter to endpoint.
     */
    mergeParams(endpoint, params = {}) {
        const search = new URLSearchParams();

        Object.entries(params).forEach(([key, value]) => {
            if (value === undefined || value === null || value === "") {
                return;
            }

            search.set(key, String(value));
        });

        if (!search.toString()) {
            return endpoint;
        }

        return `${endpoint}${endpoint.includes("?") ? "&" : "?"}${search.toString()}`;
    }
}

export const createApiClient = (config) => new ApiClient(config);

export default ApiClient;
