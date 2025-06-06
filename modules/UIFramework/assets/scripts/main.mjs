import * as constants from './lib/constants.mjs';
import * as util from './lib/util.mjs';
import * as viewport from './lib/viewport.mjs';
import * as hooks from './lib/hooks.mjs';
import { loadScript, registerScript } from './lib/script-registration.js';
import { hashState, registerHashStateActions } from './lib/hash-state.mjs';
import { registerLayoutActions, updateLayout } from './lib/event-actions.mjs';
import { registerScrollWatchActions, scrollWatch } from './lib/scroll-watch.mjs';
import { registerHeaderHeightActions } from './lib/header-height.mjs';
import { registerCssVarsActions } from './lib/css-vars.mjs';
import { registerExitIntentActions } from './lib/exit-intent.mjs';

const register = (cb) => hooks.addAction(constants.REGISTER, cb, 100);
window.sitchco = {
    constants,
    hooks,
    util,
    viewport,
    loadScript,
    registerScript,
    hashState,
    register,
    updateLayout,
    scrollWatch,
};

// Init listeners
hooks.addAction(
    constants.READY,
    () => {
        hashState.emit();
        window.addEventListener('hashchange', () => {
            hashState.set();
        });
    },
    99
);

hooks.addAction(constants.SET_HASH_STATE, (hash) => {
    hashState.set(hash);
});

// Core components
register(registerHashStateActions);
register(registerLayoutActions);
register(registerScrollWatchActions);
register(registerHeaderHeightActions);
register(registerCssVarsActions);
register(registerExitIntentActions);
window.addEventListener('DOMContentLoaded', () => {
    // Use this event if for external or non-explicit dependencies
    document.dispatchEvent(new CustomEvent('sitchco/core/init', window.sitchco));
    // Theme should register here to add any filters
    hooks.doAction(constants.INIT);
    // Components should register here so theme can filter
    hooks.doAction(constants.REGISTER);
    // Component post-registration actions can happen here
    hooks.doAction(constants.READY);
    // Reveal page after everything is initialized
    requestAnimationFrame(function () {
        document.body.classList.remove('sitchco-app-loading');
    });
});

if (window.jQuery) {
    jQuery(document).bind('gform_confirmation_loaded', function (event, formId) {
        hooks.doAction(constants.GFORM_CONFIRM, formId);
    });
}
// document.write('TEsting testing testing')
