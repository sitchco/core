function resolveModalLabel(modal) {
    const labelledById = modal.getAttribute('aria-labelledby');
    if (labelledById) {
        const el = document.getElementById(labelledById);
        if (el?.textContent?.trim()) {
            return el.textContent.trim();
        }
    }
    return modal.id || '';
}

export function registerModalTracker(pushEvent) {
    const { hooks } = window.sitchco;

    hooks.addAction(
        'ui-modal-show',
        (modal) => {
            pushEvent({
                event: 'modal_open',
                modal_label: resolveModalLabel(modal),
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
                modal_label: resolveModalLabel(modal),
            });
        },
        20,
        'tag-manager',
    );
}
