export function integrationError(error, fallback = 'The request could not finish. Please retry.') {
    const data = error?.response?.data;
    const validation = Object.values(data?.errors || {}).flat().find(value => typeof value === 'string');
    if (validation) return validation;
    if (error?.response?.status === 419) return 'Your session expired. Refresh the page and sign in again.';
    if (error?.response?.status === 429) return 'Too many requests. Wait a minute before trying again.';
    if (typeof data?.message === 'string') return data.message;
    if (typeof data?.error === 'string') return data.error;
    return fallback;
}

export function whatsappQrSource(value) {
    if (typeof value !== 'string' || !value) return '';
    if (/^data:image\/(png|jpeg|webp);base64,[A-Za-z0-9+/=\r\n]+$/.test(value)) return value;
    if (/^[A-Za-z0-9+/=\r\n]+$/.test(value)) return `data:image/png;base64,${value}`;
    return '';
}
