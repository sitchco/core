import { pushEvent, registerDataLayerActions } from './lib/datalayer.mjs';
import { registerClickTracker } from './lib/click-tracker.mjs';
import { registerModalTracker } from './lib/modal-tracker.mjs';
import { registerFormTracker } from './lib/form-tracker.mjs';
import { registerHashTracker } from './lib/hash-tracker.mjs';
import { registerVideoTracker } from './lib/video-tracker.mjs';
import { captureUtmParams } from './lib/utm-storage.mjs';
import { registerOutboundDecorator } from './lib/outbound-decorator.mjs';

const { hooks, constants } = window.sitchco;

hooks.addAction(
    constants.REGISTER,
    () => {
        registerDataLayerActions();
        registerClickTracker(pushEvent);
        registerModalTracker(pushEvent);
        registerFormTracker(pushEvent);
        registerHashTracker(pushEvent);
        registerVideoTracker(pushEvent);
        captureUtmParams();
        registerOutboundDecorator();
    },
    10,
    'tag-manager'
);
