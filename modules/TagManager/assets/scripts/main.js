import { pushEvent, registerDataLayerActions } from './lib/datalayer.mjs';
import { registerClickTracker } from './lib/click-tracker.mjs';

const { hooks, constants } = window.sitchco;

hooks.addAction(
    constants.REGISTER,
    () => {
        registerDataLayerActions();
        registerClickTracker(pushEvent);
    },
    10,
    'tag-manager',
);
