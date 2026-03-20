import { isHttpLink, resolveAriaLabelledBy } from './dom-utils.mjs';

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

function truncate(text) {
    if (!text) {
        return '';
    }
    return text.length > MAX_LENGTH ? text.slice(0, MAX_LENGTH) : text;
}

const labelResolvers = [
    (el, gtmData) => gtmData?.label,
    (el) => el.getAttribute('aria-label'),
    (el) => resolveAriaLabelledBy(el),
    (el) => el.getAttribute('title'),
    (el) => el.value,
    (el) => truncate(el.textContent?.trim().replace(/\s+/g, ' ')),
];

function resolveLabel(el, gtmData) {
    for (const resolve of labelResolvers) {
        const result = resolve(el, gtmData);
        if (result) {
            return result;
        }
    }
    return '';
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

function resolveLinkProps(el) {
    if (!isHttpLink(el)) {
        return null;
    }
    const isOutbound = el.hostname !== location.hostname;
    return {
        click_direction: isOutbound ? 'outbound' : 'internal',
        click_url: (isOutbound ? el.origin : '') + (el.pathname + el.search + el.hash || '/'),
    };
}

function buildPayload(el, gtmData) {
    const label = resolveLabel(el, gtmData);
    const context = resolveContext(el);
    const linkProps = resolveLinkProps(el);

    const props = {
        click_label: label,
        click_context: context,
        ...linkProps,
    };

    const payload = { event: 'site_click' };

    for (const [key, value] of Object.entries(props)) {
        if (value) {
            payload[key] = value;
        }
    }

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
        if (!el) {
            return;
        }
        if (isOptedOut(el)) {
            return;
        }

        const gtmData = parseGtmData(el);
        pushEvent(buildPayload(el, gtmData));
    });
}
