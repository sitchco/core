export function registerHeaderHeightActions() {
    const header = document.querySelector('header');
    sitchco.hooks.addFilter('header-height', () => header?.offsetHeight || 0, 5);

    // Publishes header-height filter → drives --dynamic__header-height CSS variable.
    sitchco.hooks.addFilter('css-vars.register', (styles) => {
        styles['header-height'] = () => `${sitchco.hooks.applyFilters('header-height')}px`;
        return styles;
    });
}
