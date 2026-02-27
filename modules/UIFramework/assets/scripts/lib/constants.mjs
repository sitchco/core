export const NAMESPACE = 'sitchco';

export const INIT = 'init';

export const REGISTER = 'initRegister';

export const READY = 'initReady';

export const HASH_STATE_CHANGE = 'hashStateChange';

export const SET_HASH_STATE = 'setHashState';

export const LAYOUT = 'layout';

export const LAYOUTEND = 'layoutEnd';

export const SCROLLSTART = 'scrollStart';

export const SCROLL = 'scroll';

export const SCROLLEND = 'scrollEnd';

export const RESTORED = 'restored';

export const USER_FIRST_INTERACTION = 'userFirstInteraction';

export const GFORM_CONFIRM = 'gformConfirmation';

export const GA_EVENT = 'gaEvent';

export const GTM_INTERACTION = 'dataLayerInteraction';

export const GTM_STATE = 'dataLayerState';

export const TRANSITION_END_EVENT = 'webkitTransitionEnd otransitionend oTransitionEnd msTransitionEnd transitionend';

export const isiOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
