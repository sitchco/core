import { resolveAriaLabelledBy } from './dom-utils.mjs';

export function registerModalTracker(pushEvent) {
    const { hooks } = window.sitchco;

    hooks.addAction(
        'ui-modal-show',
        (modal) => {
            pushEvent({
                event: 'modal_open',
                modal_label: resolveAriaLabelledBy(modal) || modal.id || '',
            });
        },
        20,
        'tag-manager',
    );

    hooks.addAction(
        'ui-modal-hide',
        (modal) => {
            pushEvent({
                event: 'modal_close',
                modal_label: resolveAriaLabelledBy(modal) || modal.id || '',
            });
        },
        20,
        'tag-manager',
    );
}
