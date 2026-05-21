import { pushEvent, registerClickTracker, captureLandingParams, registerOutboundDecorator } from '@sitchco/datalayer';
import { registerModalTracker } from './lib/modal-tracker.mjs';
import { registerFormTracker } from './lib/form-tracker.mjs';
import { registerHashTracker } from './lib/hash-tracker.mjs';
import { registerVideoTracker } from './lib/video-tracker.mjs';

const { hooks, constants } = window.sitchco;

hooks.addAction(
    constants.REGISTER,
    () => {
        // registerDataLayerActions — inlined here (sitchco-coupled)
        hooks.addAction(constants.GTM_INTERACTION, (data, element) => pushEvent(data, element), 10, 'tag-manager');
        hooks.addAction(constants.GTM_STATE, (data, element) => pushEvent(data, element), 10, 'tag-manager');

        registerClickTracker(pushEvent);
        registerModalTracker(pushEvent);
        registerFormTracker(pushEvent);
        registerHashTracker(pushEvent);
        registerVideoTracker(pushEvent);
        const domainsRecord = window.sitchco?.tagManager?.landingParams?.domains ?? {};
        const landingParamsConfig = {
            domains: Object.entries(domainsRecord).map(([domain, entry]) => ({
                domain,
                extraParams: entry?.extraParams ?? [],
            })),
        };
        captureLandingParams(landingParamsConfig);
        const landingParamsHandle = registerOutboundDecorator(landingParamsConfig);
        window.sitchco.tagManager = window.sitchco.tagManager ?? {};
        window.sitchco.tagManager.updateLandingParams = landingParamsHandle.update;
        window.sitchco.tagManager.clearLandingParams = landingParamsHandle.clear;
    },
    10,
    'tag-manager'
);
