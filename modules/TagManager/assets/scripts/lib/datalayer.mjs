export function pushEvent(data) {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push(data);
}

export function registerDataLayerActions() {
    const { hooks, constants } = window.sitchco;
    hooks.addAction(constants.GTM_INTERACTION, (data) => pushEvent(data), 10, 'tag-manager');
    hooks.addAction(
        constants.GTM_STATE,
        (data) => {
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push(data);
        },
        10,
        'tag-manager',
    );
}
