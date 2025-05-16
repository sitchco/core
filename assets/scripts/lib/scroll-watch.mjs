import { addAction } from './hooks.mjs'
import { isInViewport, isVisible } from './viewport.mjs'
import { READY, SCROLL, LAYOUT } from './constants.mjs'

const registered = [];
let windowLoaded = false;

function initCallback(group) {
    if (group.options.defer && !windowLoaded) {
        return;
    }

    group.els = group.els.filter(el => {
        const shouldCheck = isInViewport(el) && (group.force || isVisible(el));
        if (shouldCheck) {
            const prune = group.callback.call(el, el);
            return !(prune || group.options.prune); // keep only if NOT pruned
        }
        return true;
    });
}

function checkElements() {
    for (let i = registered.length - 1; i >= 0; i--) {
        const group = registered[i];
        if (group.els.length) {
            initCallback(group);
        } else {
            registered.splice(i, 1);
        }
    }
}

/**
 * Register scroll watcher
 *
 * @param {Element[] | NodeList} els - Elements to observe
 * @param {function} callback - Called when element enters viewport
 * @param {Object} [options]
 * @param {boolean} [options.force=false] - Trigger even if element isn't visible
 * @param {boolean} [options.defer=false] - Wait for window load event
 * @param {boolean} [options.prune=true] - Remove element after triggering once
 */
export function scrollWatch(els, callback, options = {}) {
    const merged = {
        force: false,
        defer: false,
        prune: true,
        ...options
    };

    const arrayEls = Array.from(els).filter(Boolean);
    if (arrayEls.length) {
        registered.push({ els: arrayEls, callback, options: merged });
    }
}

// Call checkElements on scroll
window.addEventListener('scroll', () => requestAnimationFrame(checkElements));

window.addEventListener('load', () => {
    windowLoaded = true;
    checkElements();
});

export function registerScrollWatchActions() {
    addAction(READY, checkElements);
    addAction(SCROLL, checkElements);
    addAction(LAYOUT, checkElements);
}
