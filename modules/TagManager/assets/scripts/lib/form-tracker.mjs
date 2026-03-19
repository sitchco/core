export function registerFormTracker(pushEvent) {
    const { hooks, constants } = window.sitchco;

    hooks.addAction(
        constants.GFORM_CONFIRM,
        (formId) => {
            pushEvent({
                event: 'gform_submit',
                form_id: formId,
            });
        },
        10,
        'tag-manager'
    );
}
