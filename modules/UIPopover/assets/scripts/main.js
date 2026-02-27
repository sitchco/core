const { addAction, doAction } = sitchco.hooks;

const COMPONENT = 'ui-popover';
const SHOW_HOOK = `${COMPONENT}-show`;
const HIDE_HOOK = `${COMPONENT}-hide`;
const TOGGLE_HOOK = `${COMPONENT}-toggle`;

const getTriggerForPanel = (panel) =>
    panel.id ? document.querySelector(`[data-popover-trigger="${panel.id}"]`) : null;

const getPanelForTrigger = (trigger) =>
    trigger.dataset.popoverTrigger ? document.getElementById(trigger.dataset.popoverTrigger) : null;

const positionArrow = (panel) => {
    if (!panel.classList.contains('sitchco-popover--arrow')) {
        return;
    }

    const trigger = getTriggerForPanel(panel);
    if (!trigger) {
        return;
    }

    const triggerRect = trigger.getBoundingClientRect();
    const panelRect = panel.getBoundingClientRect();
    const left = triggerRect.left + triggerRect.width / 2 - panelRect.left;
    panel.style.setProperty('--popover-arrow-left', `${left}px`);
};

const focusPanel = (panel) => {
    const focusable = panel.querySelector(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    );
    if (focusable) {
        focusable.focus();
    } else {
        if (!panel.getAttribute('tabindex')) {
            panel.setAttribute('tabindex', '-1');
        }

        panel.focus();
    }
};

addAction(
    SHOW_HOOK,
    (panel) => {
        panel.showPopover();
        const trigger = getTriggerForPanel(panel);
        if (trigger) {
            trigger.setAttribute('aria-expanded', 'true');
        }
    },
    10,
    COMPONENT
);

addAction(
    HIDE_HOOK,
    (panel) => {
        panel.hidePopover();
    },
    10,
    COMPONENT
);

addAction(
    TOGGLE_HOOK,
    (panel) => {
        panel.togglePopover();
    },
    10,
    COMPONENT
);

document.addEventListener('DOMContentLoaded', function () {
    // Toggle event listener on each popover panel
    document.querySelectorAll('[popover].sitchco-popover').forEach((panel) => {
        panel.addEventListener('toggle', (e) => {
            const trigger = getTriggerForPanel(panel);
            if (e.newState === 'open') {
                positionArrow(panel);
                focusPanel(panel);
            }
            if (e.newState === 'closed') {
                if (trigger) {
                    trigger.setAttribute('aria-expanded', 'false');
                }
                if (!document.activeElement || document.activeElement === document.body) {
                    if (trigger) {
                        trigger.focus();
                    }
                }
            }
        });

        // Tab-out dismiss: close when keyboard focus leaves the panel
        let tabbing = false;
        panel.addEventListener('keydown', (e) => {
            if (e.key === 'Tab') {
                tabbing = true;
            }
        });

        panel.addEventListener('focusout', (e) => {
            if (!tabbing || panel.getAttribute('popover') === 'manual') {
                tabbing = false;
                return;
            }

            tabbing = false;
            const trigger = getTriggerForPanel(panel);
            if (e.relatedTarget && e.relatedTarget === trigger) {
                return;
            }
            if (!e.relatedTarget || !panel.contains(e.relatedTarget)) {
                panel.hidePopover();
            }
        });
    });

    // ARIA decoration of trigger elements
    document.querySelectorAll('[data-popover-trigger]').forEach((trigger) => {
        if (!trigger.getAttribute('aria-controls')) {
            trigger.setAttribute('aria-controls', trigger.dataset.popoverTrigger);
        }
        if (!trigger.getAttribute('aria-haspopup')) {
            trigger.setAttribute('aria-haspopup', 'dialog');
        }
        if (!trigger.getAttribute('aria-expanded')) {
            trigger.setAttribute('aria-expanded', 'false');
        }
    });

    // Track whether the popover was open when the trigger mousedown fires,
    // so we can distinguish "click to close" from "click to open" after
    // native light dismiss has already closed the popover between mousedown and click.
    let panelWasOpen = false;
    document.body.addEventListener('mousedown', (e) => {
        const trigger = e.target.closest('[data-popover-trigger]');
        if (!trigger) {
            panelWasOpen = false;
            return;
        }

        const panel = getPanelForTrigger(trigger);
        panelWasOpen = panel ? panel.matches(':popover-open') : false;
    });

    // Click delegation for triggers
    document.body.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-popover-trigger]');
        if (!trigger) {
            return;
        }

        e.preventDefault();
        const panel = getPanelForTrigger(trigger);
        if (!panel) {
            return;
        }
        // If the popover was open at mousedown, light dismiss already closed it — done.
        if (panelWasOpen) {
            panelWasOpen = false;
            return;
        }

        panelWasOpen = false;
        doAction(TOGGLE_HOOK, panel);
    });
});
