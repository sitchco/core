const UTM_PARAMS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
const STORAGE_KEY = 'utm_params';

export function captureUtmParams() {
    const params = new URLSearchParams(window.location.search);
    const current = {};
    let hasUtm = false;

    for (const key of UTM_PARAMS) {
        const value = params.get(key);
        if (value) {
            current[key] = value;
            hasUtm = true;
        }
    }

    if (!hasUtm) {
        return;
    }

    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(current));
    } catch {
        // localStorage unavailable (private browsing, quota exceeded, corrupt data)
    }
}

export function getStoredUtmParams() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) {
            return {};
        }

        const parsed = JSON.parse(raw);
        return typeof parsed === 'object' && parsed !== null ? parsed : {};
    } catch {
        return {};
    }
}
