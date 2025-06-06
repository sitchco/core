import { NAMESPACE } from './constants.mjs';

const {
    addAction: _addAction,
    addFilter: _addFilter,
    removeAction: _removeAction,
    removeFilter: _removeFilter,
    hasAction: _hasAction,
    hasFilter: _hasFilter,
    removeAllActions,
    removeAllFilters,
    doAction,
    doActionAsync,
    applyFilters,
    applyFiltersAsync,
    doingAction,
    doingFilter,
    didAction,
    didFilter,
    actions,
    filters,
    defaultHooks,
} = window.wp.hooks.createHooks();

/**
 * Build the full namespace for a hook.
 * @param {string} [subNamespace]
 * @returns {string}
 */
function buildNamespace(subNamespace) {
    return subNamespace ? `${NAMESPACE}/${subNamespace}` : NAMESPACE;
}

/**
 * Add a namespaced action.
 */
export function addAction(hookName, callback, priority = 10, subNamespace = '') {
    _addAction(hookName, buildNamespace(subNamespace), callback, priority);
}

/**
 * Add a namespaced filter.
 */
export function addFilter(hookName, callback, priority = 10, subNamespace = '') {
    _addFilter(hookName, buildNamespace(subNamespace), callback, priority);
}

/**
 * Remove a namespaced action.
 */
export function removeAction(hookName, callback, subNamespace) {
    _removeAction(hookName, buildNamespace(subNamespace), callback);
}

/**
 * Remove a namespaced filter.
 */
export function removeFilter(hookName, callback, subNamespace) {
    _removeFilter(hookName, buildNamespace(subNamespace), callback);
}

/**
 * Check if a namespaced action exists.
 */
export function hasAction(hookName, subNamespace = '') {
    return _hasAction(hookName, buildNamespace(subNamespace));
}

/**
 * Check if a namespaced filter exists.
 */
export function hasFilter(hookName, subNamespace = '') {
    return _hasFilter(hookName, buildNamespace(subNamespace));
}

// Pass through exports for rest of API
export {
    removeAllActions,
    removeAllFilters,
    doAction,
    doActionAsync,
    applyFilters,
    applyFiltersAsync,
    doingAction,
    doingFilter,
    didAction,
    didFilter,
    actions,
    filters,
    defaultHooks,
};
