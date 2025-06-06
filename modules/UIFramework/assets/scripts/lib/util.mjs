/**
 * Usage:
 * const newUrl = updateUrlParameter(oldUrl, 'myparam', 'myvalue');
 * - or -
 * const newUrl = updateUrlParameter(oldUrl, 'myparam=myvalue');
 * Result:
 * "http://old-url.com/?myparam=myvalue"
 *
 * Accommodates existing different or some query parameters, as well as hash state
 *
 * @param uri {string}
 * @param key {string}
 * @param value {string}
 * @returns {string}
 */
export function updateUrlParameter(uri, key, value) {
    try {
        const url = new URL(uri, window.location.origin); // Handles relative URLs
        const params = url.searchParams;
        if (!value && key.includes('=')) {
            [key, value] = key.split('=');
        }
        if (!value) {
            params.delete(key);
        } else {
            params.set(key, value);
        }
        return url.origin + url.pathname + (params.toString() ? `?${params.toString()}` : '') + url.hash;
    } catch (err) {
        console.warn(`updateUrlParameter: Invalid URL - "${uri}"`, err);
        return uri;
    }
}

export function splitStr(str, delim = ',') {
    if (Array.isArray(str)) {
        return str;
    }
    if (typeof str !== 'string') {
        return [];
    }
    return str
        .split(delim)
        .map((item) => item.trim())
        .filter(Boolean); // Removes empty strings
}

export function groupByParent(selector) {
    const elements = Array.from(document.querySelectorAll(selector));
    const parents = new Set(elements.map((el) => el.parentElement));
    return Array.from(parents).map((parent) => Array.from(parent.querySelectorAll(`:scope > ${selector}`)));
}

export function uniqueID() {
    return crypto.randomUUID();
}

export function whitelistAssign(defaults, override) {
    if (!override || typeof override !== 'object') {
        return { ...defaults };
    }

    const result = { ...defaults };
    Object.keys(override).forEach((key) => {
        if (Object.prototype.hasOwnProperty.call(defaults, key)) {
            result[key] = override[key];
        }
    });
    return result;
}

export function groupByRow(els) {
    const groups = [];

    for (const el of els) {
        if (el.offsetParent === null) {
            // element is not visible (similar to jQuery :visible)
            continue;
        }

        const rect = el.getBoundingClientRect();
        const top = rect.top + window.scrollY; // top relative to document
        const bottom = top + rect.height;
        let found = false;

        for (const group of groups) {
            // Overlap conditions from original logic
            if (top <= group.top && bottom >= group.bottom) {
                group.top = top;
                group.bottom = bottom;
                group.elements.push(el);
                found = true;
                break;
            } else if (top >= group.top && bottom <= group.bottom) {
                group.elements.push(el);
                found = true;
                break;
            }
        }

        if (!found) {
            groups.push({
                top,
                bottom,
                elements: [el],
            });
        }
    }

    // Sort groups by their top position ascending
    groups.sort((a, b) => a.top - b.top);
    // Return only arrays of grouped elements
    return groups.map((group) => group.elements);
}

export function imageBrightness(imageSrc) {
    return new Promise((resolve) => {
        const isExternal = /^([\w]+:)?\/\//.test(imageSrc) && !imageSrc.includes(location.host);
        if (isExternal) {
            resolve(false);
            return;
        }

        const img = new Image();
        img.crossOrigin = 'anonymous';

        img.onload = function () {
            const canvas = document.createElement('canvas');
            canvas.width = this.width;
            canvas.height = this.height;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(this, 0, 0);

            try {
                const { data } = ctx.getImageData(0, 0, canvas.width, canvas.height);
                let colorSum = 0;

                for (let i = 0; i < data.length; i += 4) {
                    const [r, g, b] = [data[i], data[i + 1], data[i + 2]];
                    const lum = 0.299 * r + 0.587 * g + 0.114 * b;
                    colorSum += lum;
                }

                const brightness = Math.round((colorSum / (this.width * this.height) / 255) * 100);
                resolve(brightness);
            } catch (err) {
                resolve(false); // Usually canvas is tainted by CORS issues
            }
        };

        img.onerror = () => resolve(false);
        img.src = imageSrc;
    });
}

export function debounce(func, wait, { leading = false, trailing = true } = {}) {
    let timeout, lastArgs, lastThis, result;

    const invoke = () => {
        result = func.apply(lastThis, lastArgs);
        lastArgs = lastThis = null;
    };
    return function (...args) {
        const context = this;
        const callNow = leading && !timeout;
        if (timeout) {
            clearTimeout(timeout);
        }

        lastArgs = args;
        lastThis = context;
        timeout = setTimeout(() => {
            timeout = null;

            if (trailing && !callNow) {
                invoke();
            }
        }, wait);

        if (callNow) {
            invoke();
        }
        return result;
    };
}

export function throttle(fn, limit, { leading = true, trailing = true } = {}) {
    let lastCall = 0;
    let timeout = null;
    let lastArgs;
    return function (...args) {
        const now = Date.now();
        if (!lastCall && !leading) {
            lastCall = now;
        }

        const remaining = limit - (now - lastCall);
        lastArgs = args;

        if (remaining <= 0) {
            if (timeout) {
                clearTimeout(timeout);
                timeout = null;
            }

            lastCall = now;
            fn(...args);
        } else if (!timeout && trailing) {
            timeout = setTimeout(() => {
                lastCall = leading ? Date.now() : 0;
                timeout = null;
                fn(...lastArgs);
            }, remaining);
        }
    };
}
