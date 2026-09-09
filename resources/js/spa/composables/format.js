export function formatVnd(amount) {
    if (amount === null || amount === undefined || amount === '') return '';

    return new Intl.NumberFormat('vi-VN').format(amount) + ' đ';
}

export function formatDateTime(value) {
    if (!value) return '';

    return new Date(value).toLocaleString('vi-VN');
}

export function slugify(value) {
    const normalized = (value || '').toString().normalize('NFD');

    let stripped = '';
    for (const ch of normalized) {
        const code = ch.codePointAt(0);
        const isCombiningMark = code >= 0x0300 && code <= 0x036f;
        if (!isCombiningMark) {
            stripped += ch;
        }
    }

    return stripped
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'D')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}
