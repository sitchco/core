const SELECTOR = 'a, button, input[type=submit], [data-button]';
const MAX_LENGTH = 100;

function isOptedOut(el) {
    const val = el.dataset.gtm;
    return val === '0' || val === 'false';
}

function parseGtmData(el) {
    const raw = el.dataset.gtm;
    if (!raw || raw.charAt(0) !== '{') {
        return null;
    }
    try {
        const obj = JSON.parse(raw);
        return typeof obj === 'object' && obj !== null ? obj : null;
    } catch {
        return null;
    }
}

function resolveLabel(el, gtmData) {
    if (gtmData?.label) {
        return gtmData.label;
    }
    const ariaLabel = el.getAttribute('aria-label');
    if (ariaLabel) {
        return ariaLabel;
    }
    const labelledBy = el.getAttribute('aria-labelledby');
    if (labelledBy) {
        const ref = document.getElementById(labelledBy);
        if (ref?.textContent?.trim()) {
            return ref.textContent.trim();
        }
    }
    const title = el.getAttribute('title');
    if (title) {
        return title;
    }
    if (el.value) {
        return el.value;
    }
    const text = el.textContent?.trim().replace(/\s+/g, ' ') || '';
    return text.length > MAX_LENGTH ? text.slice(0, MAX_LENGTH) : text;
}

function resolveContext(el) {
    const parts = [];
    let ancestor = el.parentElement;
    while (ancestor && ancestor !== document.documentElement) {
        const val = ancestor.dataset?.gtm;
        if (val && val !== '0' && val !== 'false' && val.charAt(0) !== '{') {
            parts.push(val);
        }
        ancestor = ancestor.parentElement;
    }
    parts.reverse();
    let result = parts.join(' > ');
    if (result.length > MAX_LENGTH) {
        while (parts.length > 1 && parts.join(' > ').length > MAX_LENGTH) {
            parts.pop();
        }
        result = parts.join(' > ');
        if (result.length > MAX_LENGTH) {
            result = result.slice(0, MAX_LENGTH);
        }
    }
    return result;
}

function resolveDirection(el) {
    if (el.tagName !== 'A' || !el.href) {
        return undefined;
    }
    if (el.protocol !== 'http:' && el.protocol !== 'https:') {
        return undefined;
    }
    return el.hostname !== location.hostname ? 'outbound' : 'internal';
}

function resolveUrl(el) {
    if (el.tagName !== 'A' || !el.href) {
        return undefined;
    }
    if (el.protocol !== 'http:' && el.protocol !== 'https:') {
        return undefined;
    }
    return el.pathname + el.search + el.hash || '/';
}

function buildPayload(el, gtmData) {
    const label = resolveLabel(el, gtmData);
    const context = resolveContext(el);
    const direction = resolveDirection(el);
    const url = resolveUrl(el);

    const payload = { event: 'site_click' };
    if (label) payload.click_label = label;
    if (context) payload.click_context = context;
    if (direction) payload.click_direction = direction;
    if (url) payload.click_url = url;

    if (gtmData) {
        for (const [key, value] of Object.entries(gtmData)) {
            payload[`click_${key}`] = value;
        }
    }

    return payload;
}

export function registerClickTracker(pushEvent) {
    document.addEventListener('click', (e) => {
        const el = e.target.closest(SELECTOR);
        if (!el) return;
        if (isOptedOut(el)) return;

        const gtmData = parseGtmData(el);
        pushEvent(buildPayload(el, gtmData));
    });
}
