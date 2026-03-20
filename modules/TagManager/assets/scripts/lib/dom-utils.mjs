export function resolveAriaLabelledBy(el) {
    const id = el.getAttribute('aria-labelledby');
    if (!id) {
        return '';
    }

    const ref = document.getElementById(id);
    return ref?.textContent?.trim() || '';
}

export function isHttpLink(el) {
    return el.tagName === 'A' && !!el.href && (el.protocol === 'http:' || el.protocol === 'https:');
}
