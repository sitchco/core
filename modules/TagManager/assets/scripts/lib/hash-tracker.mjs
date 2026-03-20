export function registerHashTracker(pushEvent) {
    const { hooks, constants } = window.sitchco;

    hooks.addAction(
        constants.HASH_STATE_CHANGE,
        (hashState) => {
            if (hashState.previous === undefined) {
                return;
            }

            pushEvent({
                event: 'hash_change',
                hash_value: hashState.current,
            });
        },
        10,
        'tag-manager'
    );
}
