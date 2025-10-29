export async function fetchProducts({ endpoint, token, signal, limit = 20, start = 0, search = '' }) {
    const params = new URLSearchParams({ limit: String(limit), start: String(start) });

    if (search) {
        params.set('search', search);
    }

    const url = `${endpoint}&${params.toString()}`;

    const response = await fetch(url, {
        method: 'GET',
        headers: {
            'X-CSRF-Token': token,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
        credentials: 'same-origin',
        signal,
    });

    if (!response.ok) {
        const message = `Request failed with status ${response.status}`;
        throw new Error(message);
    }

    const payload = await response.json();

    if (!payload.success) {
        const errorMessage = payload.message || 'Unknown API error';
        throw new Error(errorMessage);
    }

    const body = payload.data ?? {};

    return {
        items: body.data ?? [],
        pagination: body.pagination ?? { total: 0, limit, pages: 0, current: 0 },
    };
}
