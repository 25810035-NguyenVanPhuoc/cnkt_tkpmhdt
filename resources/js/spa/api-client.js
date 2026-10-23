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
        // Laravel tra loi validate (422) voi 'message' CHUNG CHUNG ("The
        // given data was invalid.") va noi dung that su nam trong
        // 'errors.<field>[0]'. Code khap noi trong SPA nay deu doc
        // `e.data?.message` de hien thi - nen ghi de thang vao data.message
        // de moi noi tu dong hien dung loi cu the, khong can sua tung cho.
        const firstFieldError = data?.errors ? Object.values(data.errors)[0]?.[0] : null;
        const patchedData = firstFieldError ? { ...data, message: firstFieldError } : data;

        const error = new Error(firstFieldError || data?.message || 'Request failed');
        error.status = res.status;
        error.data = patchedData;
        throw error;
    }

    return data;
}
