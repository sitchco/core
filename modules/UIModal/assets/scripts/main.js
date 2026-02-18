const { addAction, doAction, applyFilters } = sitchco.hooks;

const COMPONENT = 'ui-modal';
const SHOW_MODAL_HOOK = `${COMPONENT}-show`;
const HIDE_MODAL_HOOK = `${COMPONENT}-hide`;
const ENABLE_DISMISS_HOOK = `${COMPONENT}-enableDismiss`;

let previouslyFocusedElement = null;
let scrollLockTimeout = null;
let openAnimationTimeout = null;

const showModal = (modal) => doAction(SHOW_MODAL_HOOK, modal);
const hideModal = (modal) => doAction(HIDE_MODAL_HOOK, modal);

const escHandler = () => {
    const modal = document.querySelector('.sitchco-modal--open');
    if (modal && !modal.classList.contains('sitchco-modal--blockdismiss')) {
        hideModal(modal);
    }
};

const setModalLabel = (modal) => {
    const labelId = `${modal.id}-label`;
    const heading = modal.querySelector('h1, h2, h3, h4, h5, h6');
    if (!heading) {
        return;
    }

    heading.id = labelId;
    modal.setAttribute('aria-labelledby', heading.id);
};

const getFocusableElements = (modal) => {
    return modal.querySelectorAll(
        'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
    );
};

const trapFocus = (e) => {
    const modal = document.querySelector('.sitchco-modal--open');
    if (!modal || e.key !== 'Tab') {
        return;
    }

    const focusable = getFocusableElements(modal);
    if (!focusable.length) {
        e.preventDefault();
        return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
    }
};

const getTriggerTarget = (trigger) => {
    const href = trigger.getAttribute('href');
    const dataTarget = trigger.dataset.target;
    const selector = dataTarget || href;
    return selector ? document.querySelector(selector) : null;
};

const onKeyDown = (e) => {
    if (e.key === 'Escape') {
        escHandler();
    }

    trapFocus(e);
};

// Open modal from URL hash, close modal on hash-away
const syncModalWithHash = () => {
    const hash = window.location.hash;
    const openModal = document.querySelector('.sitchco-modal--open');
    if (openModal && (!hash || `#${openModal.id}` !== hash)) {
        hideModal(openModal);
    }
    if (!hash) {
        return;
    }

    try {
        const modal = document.querySelector(`.sitchco-modal${hash}`);
        if (modal && !modal.classList.contains('sitchco-modal--open')) {
            showModal(modal);
        }
    } catch {
        // CSS-invalid hash characters — ignore
    }
};

addAction(
    SHOW_MODAL_HOOK,
    (modal) => {
        // Close any already-open modal before opening a new one
        const currentModal = document.querySelector('.sitchco-modal--open');
        if (currentModal && currentModal !== modal) {
            hideModal(currentModal);
        }

        previouslyFocusedElement = document.activeElement;
        setModalLabel(modal);

        if (applyFilters('sitchco/ui-modal/lockScroll', true, modal)) {
            scrollLockTimeout = setTimeout(() => document.body.classList.add('lock-scroll'), 700);
        }
        if (modal.id && window.location.hash !== `#${modal.id}`) {
            history.replaceState(null, '', `#${modal.id}`);
        }

        // Set inert on background content
        document.querySelectorAll('body > *:not(.sitchco-modal)').forEach((el) => {
            el.setAttribute('inert', '');
        });

        openAnimationTimeout = setTimeout(() => {
            modal.classList.add('sitchco-modal--open');
            const focusable = getFocusableElements(modal);
            if (focusable.length) {
                focusable[0].focus();
            } else {
                modal.focus();
            }
        }, 50);

        document.addEventListener('keydown', onKeyDown);
    },
    10,
    COMPONENT
);

addAction(
    HIDE_MODAL_HOOK,
    (modal) => {
        clearTimeout(scrollLockTimeout);
        clearTimeout(openAnimationTimeout);
        document.removeEventListener('keydown', onKeyDown);
        document.body.classList.remove('lock-scroll');
        modal.classList.remove('sitchco-modal--open');

        // Remove inert from background content
        document.querySelectorAll('body > [inert]').forEach((el) => {
            el.removeAttribute('inert');
        });

        if (modal.id && window.location.hash === `#${modal.id}`) {
            history.replaceState(null, '', window.location.pathname + window.location.search);
        }
        if (previouslyFocusedElement && previouslyFocusedElement !== document.body) {
            previouslyFocusedElement.focus();
        }

        previouslyFocusedElement = null;
    },
    10,
    COMPONENT
);

addAction(
    ENABLE_DISMISS_HOOK,
    (modal) => {
        const el = modal || document.querySelector('.sitchco-modal--open.sitchco-modal--blockdismiss');
        if (el) {
            el.classList.remove('sitchco-modal--blockdismiss');
        }
    },
    10,
    COMPONENT
);

document.addEventListener('DOMContentLoaded', function () {
    // Mark trigger elements and add ARIA attributes
    document.querySelectorAll('.sitchco-modal').forEach((modal) => {
        const id = modal.id;
        if (!id) {
            return;
        }

        document.querySelectorAll(`a[href="#${id}"], [data-target="#${id}"]`).forEach((el) => {
            el.classList.add('js-modal-trigger');
            el.setAttribute('aria-haspopup', 'dialog');
        });
    });

    // Trigger click
    document.body.addEventListener('click', (e) => {
        const trigger = e.target.closest('.js-modal-trigger');
        if (!trigger) {
            return;
        }

        e.preventDefault();
        const target = getTriggerTarget(trigger);
        if (target) {
            showModal(target);
        }
    });

    // Modal dismiss click (overlay, close button, or dismiss trigger)
    document.body.addEventListener('click', (e) => {
        const modal = e.target.closest('.sitchco-modal');
        if (!modal || modal.classList.contains('sitchco-modal--blockdismiss')) {
            return;
        }

        const target = e.target;
        if (target === modal || target.closest('.sitchco-modal__close') || target.closest('.js-modal-close')) {
            hideModal(modal);
        }
    });

    syncModalWithHash();
    window.addEventListener('hashchange', syncModalWithHash);
});
