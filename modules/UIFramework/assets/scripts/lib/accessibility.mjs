const COMPONENT = 'accessibility';

const FOCUSABLE_SELECTOR = [
    'a[href]',
    'button:not([disabled])',
    'textarea:not([disabled])',
    'input:not([disabled])',
    'select:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
    'iframe',
].join(', ');

// --- Focus State Detection ---
// Toggles accessibility-on/off body classes based on keyboard vs mouse input

function registerFocusStateDetection() {
    let isAccessibleMode = false;
    document.body.classList.add('accessibility-off');

    sitchco.hooks.addAction(
        'key.tab',
        () => {
            if (!isAccessibleMode) {
                document.body.classList.add('accessibility-on');
                document.body.classList.remove('accessibility-off');
                isAccessibleMode = true;
            }
        },
        10,
        COMPONENT
    );

    document.addEventListener('mousedown', () => {
        if (isAccessibleMode) {
            document.body.classList.remove('accessibility-on');
            document.body.classList.add('accessibility-off');
            isAccessibleMode = false;
        }
    });
}

// --- Focus Trap ---

class FocusTrap {
    constructor(el) {
        this.el = el;
        this.previouslyFocused = document.activeElement;
        this._onKeyDown = this._onKeyDown.bind(this);

        if (!el.hasAttribute('tabindex')) {
            el.setAttribute('tabindex', '-1');
        }
    }

    getFocusableElements() {
        return Array.from(this.el.querySelectorAll(FOCUSABLE_SELECTOR)).filter((el) =>
            el.checkVisibility({ checkVisibilityCSS: true })
        );
    }

    activate({ focusFirst = true } = {}) {
        document.addEventListener('keydown', this._onKeyDown);

        if (focusFirst) {
            const focusable = this.getFocusableElements();
            if (focusable.length) {
                focusable[0].focus();
            } else {
                this.el.focus();
            }
        }
        return this;
    }

    deactivate({ restoreFocus = true } = {}) {
        document.removeEventListener('keydown', this._onKeyDown);

        if (restoreFocus) {
            const target =
                this.previouslyFocused && this.previouslyFocused !== document.body
                    ? this.previouslyFocused
                    : document.querySelector('main');
            if (target) {
                target.focus();
            }
        }

        this.previouslyFocused = null;
        return this;
    }

    _onKeyDown(e) {
        if (e.key !== 'Tab') {
            return;
        }

        const focusable = this.getFocusableElements();
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
    }
}

// --- Skip Navigation ---

function getNextSibling(el) {
    if (el.nextElementSibling) {
        return el.nextElementSibling;
    }
    return el.parentElement ? getNextSibling(el.parentElement) : null;
}

function registerSkipNav() {
    document.querySelectorAll('[data-skip-nav]').forEach((nav) => {
        const text = nav.dataset.skipNav || 'Skip to Content';
        const targetSelector = nav.dataset.skipNavTarget;

        const link = document.createElement('a');
        link.href = '#';
        link.className = 'sitchco-skip-nav';
        link.textContent = text;

        link.addEventListener('click', (e) => {
            e.preventDefault();
            const target = targetSelector ? document.querySelector(targetSelector) : getNextSibling(nav);
            if (target) {
                target.setAttribute('tabindex', '-1');
                target.focus();
                target.scrollIntoView({ behavior: 'smooth' });
            }
        });

        nav.prepend(link);
    });
}

// --- Buttonify ---
// Enhances [data-button] elements with role, tabindex, aria-label, and keyboard activation

function registerButtons() {
    document.querySelectorAll('[data-button]').forEach((el) => {
        if (!el.getAttribute('role')) {
            el.setAttribute('role', 'button');
        }
        if (!el.getAttribute('tabindex')) {
            el.setAttribute('tabindex', '0');
        }
        if (!el.getAttribute('aria-label') && el.dataset.button) {
            el.setAttribute('aria-label', el.dataset.button);
        }

        el.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                el.click();
            }
        });
    });
}

// --- Focus Trap Hook Actions ---

function registerFocusTrapActions() {
    let currentTrap = null;

    sitchco.hooks.addAction(
        'focusTrapInit',
        (el) => {
            if (currentTrap) {
                currentTrap.deactivate({ restoreFocus: false });
            }

            currentTrap = new FocusTrap(el);
        },
        10,
        COMPONENT
    );

    sitchco.hooks.addAction(
        'focusTrapActivate',
        () => {
            if (currentTrap) {
                currentTrap.activate();
            }
        },
        10,
        COMPONENT
    );

    sitchco.hooks.addAction(
        'focusTrapDeactivate',
        () => {
            if (currentTrap) {
                currentTrap.deactivate();
                currentTrap = null;
            }
        },
        10,
        COMPONENT
    );
}

// --- Registration ---

export function registerAccessibilityActions() {
    registerFocusStateDetection();
    registerFocusTrapActions();
    registerSkipNav();
    registerButtons();
}
