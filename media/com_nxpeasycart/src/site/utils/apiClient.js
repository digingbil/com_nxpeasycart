const DEFAULT_ERROR = "Unable to complete the request right now.";

const shouldRetry = (status) => status >= 500 && status < 600;

const parseJsonSafe = async (response) => {
    try {
        return await response.clone().json();
    } catch (error) {
        return null;
    }
};

export function createApiClient(csrfToken = "") {
    const csrf = (csrfToken || "").trim();

    const withHeaders = (headers = {}) => ({
        ...headers,
        ...(csrf ? { "X-CSRF-Token": csrf } : {}),
        "X-Requested-With": "XMLHttpRequest",
    });

    const handleResponse = async (response, retried = false) => {
        const json = await parseJsonSafe(response);
        const data = json && typeof json === 'object' ? json.data || {} : {};

        if (!response.ok || (json && json.success === false)) {
            const message =
                (json && (json.message || json.error)) ||
                (data && (data.message || data.error || data.detail)) ||
                DEFAULT_ERROR;

            if (!retried && shouldRetry(response.status)) {
                return null; // Signal caller to retry once.
            }

            const error = new Error(message);
            error.status = response.status;
            error.payload = json;
            error.details = data && typeof data === 'object' ? data : {};
            throw error;
        }

        return json || {};
    };

    const postJson = async (url, body, attempt = 0) => {
        const response = await fetch(url, {
            method: "POST",
            headers: withHeaders({
                "Content-Type": "application/json",
                Accept: "application/json",
            }),
            body: JSON.stringify(body || {}),
            credentials: "same-origin",
        });

        const result = await handleResponse(response, attempt > 0);

        if (result === null) {
            return postJson(url, body, attempt + 1);
        }

        return result;
    };

    const postForm = async (url, fields = {}, attempt = 0) => {
        const formData = new FormData();

        Object.entries(fields).forEach(([key, value]) => {
            if (value === undefined || value === null) {
                return;
            }

            formData.append(key, value);
        });

        if (csrf) {
            formData.append(csrf, "1");
        }

        const response = await fetch(url, {
            method: "POST",
            body: formData,
            headers: withHeaders({ Accept: "application/json" }),
        });

        const result = await handleResponse(response, attempt > 0);

        if (result === null) {
            return postForm(url, fields, attempt + 1);
        }

        return result;
    };

    return {
        postJson,
        postForm,
    };
}
