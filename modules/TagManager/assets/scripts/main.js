import { pushEvent, registerDataLayerActions } from './lib/datalayer.mjs';
import { registerClickTracker } from './lib/click-tracker.mjs';
import { registerModalTracker } from './lib/modal-tracker.mjs';
import { registerFormTracker } from './lib/form-tracker.mjs';
import { registerHashTracker } from './lib/hash-tracker.mjs';

const { hooks, constants } = window.sitchco;

hooks.addAction(
    constants.REGISTER,
    () => {
        registerDataLayerActions();
        registerClickTracker(pushEvent);
        registerModalTracker(pushEvent);
        registerFormTracker(pushEvent);
        registerHashTracker(pushEvent);
    },
    10,
    'tag-manager',
);
