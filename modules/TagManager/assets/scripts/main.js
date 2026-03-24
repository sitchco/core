import { pushEvent, registerClickTracker } from '@sitchco/datalayer';
import { captureUtmParams } from '@sitchco/datalayer/utm';
import { registerOutboundDecorator } from '@sitchco/datalayer/outbound';
import { registerModalTracker } from './lib/modal-tracker.mjs';
import { registerFormTracker } from './lib/form-tracker.mjs';
import { registerHashTracker } from './lib/hash-tracker.mjs';
import { registerVideoTracker } from './lib/video-tracker.mjs';

const { hooks, constants } = window.sitchco;

hooks.addAction(
    constants.REGISTER,
    () => {
        // registerDataLayerActions — inlined here (sitchco-coupled)
        hooks.addAction(constants.GTM_INTERACTION, (data) => pushEvent(data), 10, 'tag-manager');
        hooks.addAction(constants.GTM_STATE, (data) => pushEvent(data), 10, 'tag-manager');

        registerClickTracker(pushEvent);
        registerModalTracker(pushEvent);
        registerFormTracker(pushEvent);
        registerHashTracker(pushEvent);
        registerVideoTracker(pushEvent);
        captureUtmParams();

        const domains = Object.keys(window.sitchco?.tagManager?.outboundDomains || {});
        registerOutboundDecorator({ domains });
    },
    10,
    'tag-manager'
);
