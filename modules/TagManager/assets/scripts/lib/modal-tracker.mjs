import { resolveAriaLabelledBy } from '@sitchco/datalayer';

export function registerModalTracker(pushEvent) {
    const { hooks } = window.sitchco;

    const events = [
        {
            hook: 'ui-modal-show',
            event: 'modal_open',
        },
        {
            hook: 'ui-modal-hide',
            event: 'modal_close',
        },
    ];

    for (const { hook, event } of events) {
        hooks.addAction(
            hook,
            (modal) => {
                pushEvent({
                    event,
                    modal_label: resolveAriaLabelledBy(modal) || modal.id || '',
                });
            },
            20,
            'tag-manager'
        );
    }
}
