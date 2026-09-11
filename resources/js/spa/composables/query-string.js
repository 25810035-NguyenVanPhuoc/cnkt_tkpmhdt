export function buildQuery(params = {}) {
    const search = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            search.set(key, value);
        }
    });

    const qs = search.toString();

    return qs ? `?${qs}` : '';
}
