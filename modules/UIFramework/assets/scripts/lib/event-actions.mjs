import * as viewport from './viewport.mjs';
import { LAYOUT, LAYOUTEND, SCROLL, SCROLLSTART, SCROLLEND, USER_FIRST_INTERACTION, isiOS } from './constants.mjs';
import { debounce, throttle } from './util.mjs';

let broadcastLayoutUpdate = () => {};

let broadcastLayoutEnd = () => {};

export function updateLayout() {
    broadcastLayoutUpdate();
    broadcastLayoutEnd();
}

export function registerLayoutActions() {
    const debounceDelay = sitchco.hooks.applyFilters('debounceDelay', 300) || 300;
    const throttleLayout = sitchco.hooks.applyFilters('layoutThrottle', 300) || 300;
    const throttleScroll = sitchco.hooks.applyFilters('scrollThrottle', 100) || 100;
    let docWidth = viewport.width();
    let isScrolling = false;
    let currentWidth = 0;

    const widthHasChanged = () => {
        const newWidth = viewport.width();
        if (currentWidth === newWidth) {
            return false;
        }

        currentWidth = newWidth;
        return true;
    };

    broadcastLayoutUpdate = throttle(
        () => {
            requestAnimationFrame(() => {
                sitchco.hooks.doAction(LAYOUT, widthHasChanged());
            });
        },
        throttleLayout,
        {
            leading: true,
            trailing: true,
        }
    );

    broadcastLayoutEnd = debounce(() => {
        requestAnimationFrame(() => {
            sitchco.hooks.doAction(LAYOUTEND);
        });
    }, debounceDelay);

    const broadcastScrollEnd = debounce((event, position) => {
        isScrolling = false;
        requestAnimationFrame(() => {
            sitchco.hooks.doAction(SCROLLEND, event, position);
        });
    }, debounceDelay);
    const onScroll = throttle(
        (event) => {
            const position = viewport.scrollPosition();
            if (!isScrolling) {
                isScrolling = true;
                requestAnimationFrame(() => {
                    sitchco.hooks.doAction(SCROLLSTART, event, position);
                });
            }

            requestAnimationFrame(() => {
                sitchco.hooks.doAction(SCROLL, event, position);
            });

            broadcastScrollEnd(event, position);
        },
        throttleScroll,
        { leading: true }
    );

    const onUserFirstInteraction = () => {
        window.removeEventListener('keydown', onUserFirstInteraction);
        window.removeEventListener('mousemove', onUserFirstInteraction);
        window.removeEventListener('touchmove', onUserFirstInteraction);
        window.removeEventListener('touchstart', onUserFirstInteraction);
        window.removeEventListener('touchend', onUserFirstInteraction);
        window.removeEventListener('wheel', onUserFirstInteraction);
        requestAnimationFrame(() => {
            sitchco.hooks.doAction(USER_FIRST_INTERACTION);
        });
    };

    // Event bindings
    window.addEventListener('load', updateLayout);
    window.addEventListener('orientationchange', updateLayout);
    window.addEventListener('orientationchange', () => {
        setTimeout(updateLayout, 600);
    });

    if (isiOS) {
        window.addEventListener('resize', () => {
            const width = viewport.width();
            if (width !== docWidth) {
                updateLayout();
            }

            docWidth = width;
        });
    } else {
        window.addEventListener('resize', updateLayout);
    }

    document.addEventListener('keydown', (e) => {
        const code = e.which || e.keyCode;
        const codes = {
            9: 'tab',
            13: 'return',
            27: 'esc',
        };
        if (codes.hasOwnProperty(code)) {
            sitchco.hooks.doAction('key.' + codes[code], e);
        }
    });

    window.addEventListener('scroll', onScroll);
    // First interaction trigger
    ['keydown', 'mousemove', 'touchmove', 'touchstart', 'touchend', 'wheel'].forEach((event) =>
        window.addEventListener(event, onUserFirstInteraction, { once: true })
    );

    updateLayout();
}
