let onUnauthorized = null;

export function setUnauthorizedHandler(fn) {
    onUnauthorized = fn;
}

function buildHeaders(options, token) {
    const headers = {
        Accept: 'application/json',
        ...(options.headers || {}),
    };

    if (options.body && !(options.body instanceof FormData) && !headers['Content-Type']) {
        headers['Content-Type'] = 'application/json';
    }

    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }

    return headers;
}

export async function apiFetch(path, options = {}, token) {
    const res = await fetch(`/api${path}`, {
        ...options,
        headers: buildHeaders(options, token),
    });

    if (res.status === 401) {
        onUnauthorized?.();
        throw new Error('Unauthorized');
    }

    const isJson = res.headers.get('content-type')?.includes('application/json');
    const data = isJson ? await res.json() : null;

    if (!res.ok) {
        const error = new Error(data?.message || 'Request failed');
        error.status = res.status;
        error.data = data;
        throw error;
    }

    return data;
}
