import { addAction, addFilter, applyFilters } from './hooks.mjs';
import { debounce } from './util.mjs';
import { HASH_STATE_CHANGE, isiOS } from './constants.mjs';

export function registerHeaderHeightActions() {
    const header = document.querySelector('header');
    let headerHeight = header?.offsetHeight || 0;
    addFilter('header-height', () => header?.offsetHeight || 0, 5);
    addFilter(
        'header-offset',
        () => {
            if (window.scrollY === 0) {
                headerHeight = header?.offsetHeight || 0;
            }
            return headerHeight;
        },
        5
    );

    addFilter('css-vars.register', (styles) => {
        styles['header-height'] = () => `${applyFilters('header-height')}px`;
        styles['header-offset'] = () => `${applyFilters('header-offset')}px`;
        return styles;
    });

    const scrollTargetFallback = debounce(
        (event) => {
            const hash = window.location.hash;
            if (!hash) {
                return;
            }

            const browserTarget = document.querySelector(hash);
            if (!browserTarget) {
                return;
            }

            const computedStyle = getComputedStyle(browserTarget);
            const paddingTop = parseInt(computedStyle.paddingTop, 10);
            const hasAnchorClass = browserTarget.classList.contains('anchor');
            if (!paddingTop && !hasAnchorClass) {
                return;
            }
            if (event) {
                const prevented = typeof event.isDefaultPrevented === 'function' ? event.isDefaultPrevented() : false;
                const stopped = typeof event.isPropagationStopped === 'function' ? event.isPropagationStopped() : false;
                if (prevented || stopped) {
                    return;
                }
            }

            let selector;

            try {
                selector = event?.currentTarget?.getAttribute('href');
            } catch {
                selector = event?.current ? `#${event.current}` : '';
            }

            if (!selector?.startsWith('#')) {
                return;
            }

            const actionTarget = document.querySelector(selector);
            if (!actionTarget || actionTarget !== browserTarget) {
                return;
            }

            const targetOffset = actionTarget.getBoundingClientRect().top + window.scrollY;
            window.scrollTo({
                top: targetOffset - applyFilters('header-height'),
                behavior: 'smooth',
            });
        },
        isiOS ? 300 : 0
    );
    addAction(HASH_STATE_CHANGE, scrollTargetFallback);
    document.addEventListener('click', (event) => {
        const target = event.target.closest('a[href^="#"]');
        if (target) {
            scrollTargetFallback(event);
        }
    });
}
