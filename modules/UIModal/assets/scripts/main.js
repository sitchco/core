const { addAction, doAction, applyFilters } = sitchco.hooks;

const NAMESPACE = 'ui-modal';
const SHOW_MODAL = 'how';
const HIDE_MODAL = 'hide';
const ENABLE_DISMISS = 'enableDismiss';

let previouslyFocusedElement = null;

const showModal = (modal) => doAction(`sitchco/${NAMESPACE}/${SHOW_MODAL}`, modal);
const hideModal = (modal) => doAction(`sitchco/${NAMESPACE}/${HIDE_MODAL}`, modal);

const escHandler = () => {
    const modal = document.querySelector('.modal--open');
    if (modal && !modal.classList.contains('modal--blockdismiss')) {
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

// Mark trigger elements
document.querySelectorAll('.modal').forEach((modal) => {
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
    const modal = e.target.closest('.modal');
    if (!modal || modal.classList.contains('modal--blockdismiss')) {
        return;
    }

    const target = e.target;
    if (target === modal || target.closest('.modal__close') || target.closest('.js-modal-close')) {
        hideModal(modal);
    }
});

addAction(
    SHOW_MODAL,
    (modal) => {
        previouslyFocusedElement = document.activeElement;
        setModalLabel(modal);

        if (applyFilters('sitcho/ui-modal/lockScroll', true, modal)) {
            setTimeout(() => document.body.classList.add('lock-scroll'), 700);
        }

        setTimeout(() => {
            modal.classList.add('modal--open');
            modal.focus();
        }, 50);

        document.addEventListener('keydown', onKeyDown);
    },
    10,
    NAMESPACE
);

addAction(
    HIDE_MODAL,
    (modal) => {
        document.removeEventListener('keydown', onKeyDown);
        document.body.classList.remove('lock-scroll');
        modal.classList.remove('modal--open');

        if (previouslyFocusedElement) {
            previouslyFocusedElement.focus();
            previouslyFocusedElement = null;
        }
    },
    10,
    NAMESPACE
);

addAction(
    ENABLE_DISMISS,
    (modal) => {
        const el = modal || document.querySelector('.modal--open.modal--blockdismiss');
        if (el) {
            el.classList.remove('modal--blockdismiss');
        }
    },
    10,
    NAMESPACE
);

function onKeyDown(e) {
    if (e.key === 'Escape') {
        escHandler();
    }
}

// Open modal from URL hash on load
const openFromHash = () => {
    const hash = window.location.hash;
    if (!hash) {
        return;
    }

    const modal = document.querySelector(`.modal${hash}`);
    if (modal) {
        showModal(modal);
    }
};

openFromHash();
window.addEventListener('hashchange', openFromHash);
