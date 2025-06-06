import { addAction, addFilter, applyFilters } from './hooks.mjs';
import { LAYOUT, READY, SCROLL } from './constants.mjs';
import { scrollPosition } from './viewport.mjs';

export class DynamicStylesheet {
    constructor(name) {
        this.name = name;
        this.styles = {};
    }

    update() {
        if (Object.keys(this.styles).length === 0) {
            return;
        }

        for (const [key, value] of Object.entries(this.styles)) {
            const resolved = typeof value === 'function' ? value() : value;
            document.documentElement.style.setProperty(`--dynamic__${key}`, resolved);
        }
    }
}

export function registerCssVarsActions() {
    const layoutStyles = new DynamicStylesheet('dynamic-styles');
    const scrollStyles = new DynamicStylesheet('scroll-styles');
    let currentScrollPosition = scrollPosition().top;
    addAction(
        READY,
        function () {
            const useScroll = applyFilters('css-vars.use-scroll', false);
            layoutStyles.styles = applyFilters('css-vars.register', layoutStyles.styles);
            layoutStyles.update();

            if (useScroll) {
                scrollStyles.styles = applyFilters('css-vars.register-scroll', scrollStyles.styles);
                scrollStyles.update();
                addAction(SCROLL, () => scrollStyles.update(), 10);
            }
        },
        100
    );

    addAction(LAYOUT, () => layoutStyles.update(), 10);
    addAction('css-vars.refresh', () => layoutStyles.update(), 10);
    addFilter('css-vars.register-scroll', function (styles) {
        styles['scroll-direction'] = function () {
            const newPosition = scrollPosition().top;
            const direction = newPosition >= currentScrollPosition ? 1 : -1;
            currentScrollPosition = newPosition;
            return direction;
        };
        return styles;
    });
}
