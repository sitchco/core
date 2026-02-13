const { addAction, doAction, applyFilters } = sitchco.hooks;

const COMPONENT = 'ui-modal';
const SHOW_MODAL_HOOK = `${COMPONENT}-show`;
const HIDE_MODAL_HOOK = `${COMPONENT}-hide`;
const ENABLE_DISMISS_HOOK = `${COMPONENT}-enableDismiss`;

let previouslyFocusedElement = null;

const showModal = (modal) => doAction(SHOW_MODAL_HOOK, modal);
const hideModal = (modal) => doAction(HIDE_MODAL_HOOK, modal);

const escHandler = () => {
    const modal = document.querySelector('.sitchco-modal--open');
    if (modal && !modal.classList.contains('.sitchco-modal--blockdismiss')) {
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
};

// Open modal from URL hash on load
const openFromHash = () => {
    const hash = window.location.hash;
    if (!hash) {
        return;
    }

    const modal = document.querySelector(`.sitchco-modal${hash}`);
    if (modal) {
        showModal(modal);
    }
};

addAction(
    SHOW_MODAL_HOOK,
    (modal) => {
        previouslyFocusedElement = document.activeElement;
        setModalLabel(modal);

        if (applyFilters('sitcho/ui-modal/lockScroll', true, modal)) {
            setTimeout(() => document.body.classList.add('lock-scroll'), 700);
        }
        if (modal.id && window.location.hash !== `#${modal.id}`) {
            history.replaceState(null, '', `#${modal.id}`);
        }

        setTimeout(() => {
            modal.classList.add('sitchco-modal--open');
            modal.focus();
        }, 50);

        document.addEventListener('keydown', onKeyDown);
    },
    10,
    COMPONENT
);

addAction(
    HIDE_MODAL_HOOK,
    (modal) => {
        document.removeEventListener('keydown', onKeyDown);
        document.body.classList.remove('lock-scroll');
        modal.classList.remove('sitchco-modal--open');

        if (modal.id && window.location.hash === `#${modal.id}`) {
            history.replaceState(null, '', window.location.pathname + window.location.search);
        }
        if (previouslyFocusedElement) {
            previouslyFocusedElement.focus();
            previouslyFocusedElement = null;
        }
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
    // Mark trigger elements
    document.querySelectorAll('.sitchco-modal').forEach((modal) => {
        const id = modal.id;
        if (!id) {
            return;
        }

        document
            .querySelectorAll(`a[href="#${id}"], [data-target="#${id}"]`)
            .forEach((el) => el.classList.add('js-modal-trigger'));
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

    openFromHash();
    window.addEventListener('hashchange', openFromHash);
});
