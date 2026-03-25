export function registerFormTracker(pushEvent) {
    const { hooks, constants } = window.sitchco;

    hooks.addAction(
        constants.GFORM_CONFIRM,
        (formId) => {
            const formEl =
                document.getElementById(`gform_wrapper_${formId}`) ||
                document.getElementById(`gform_confirmation_wrapper_${formId}`);
            pushEvent(
                {
                    event: 'gform_submit',
                    form: { id: formId },
                },
                formEl
            );
        },
        10,
        'tag-manager'
    );
}
