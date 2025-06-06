import { doAction, applyFilters } from './hooks.mjs';
import { debounce } from './util.mjs';

let disabled = false;

function detectExitIntent() {
    const triggerCustomEvent = debounce(
        () => {
            if (!applyFilters('enableExitIntent', true) || disabled) {
                return;
            }

            doAction('exitIntent');
            disabled = true;
        },
        500,
        {
            leading: false,
            trailing: true,
        }
    );
    document.addEventListener('mouseout', (e) => {
        const nearTopEdge = e.clientY < 5;
        const nearLeftEdge = e.clientX < 5;
        if (!e.relatedTarget && !e.toElement && (nearTopEdge || nearLeftEdge)) {
            triggerCustomEvent();
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            triggerCustomEvent();
        }
    });
}

export function registerExitIntentActions() {
    if (applyFilters('enableExitIntent', false)) {
        detectExitIntent();
    }
}
