import { READY, SET_HASH_STATE, HASH_STATE_CHANGE } from './constants.mjs';

function getPath() {
    return decodeURIComponent(location.hash.slice(1)).replace(/^\/+/, '');
}

function getPathList() {
    const path = getPath();
    return path ? path.split('/') : [];
}

class HashState {
    constructor(previous) {
        this.current = getPath();
        this.currentList = getPathList();
        this.currentId = this.current.replace('/', '__');

        if (previous !== undefined) {
            this.previous = previous.current;
            this.previousList = previous.currentList;
        }
    }

    hasChanged() {
        return this.current !== this.previous;
    }

    isset() {
        return !!this.current;
    }

    matchesHash(hash) {
        return this.current.includes(hash.replace(/^#/, ''));
    }
}
let currentState = new HashState();

export const hashState = {
    emit() {
        if (currentState.hasChanged() && (currentState.isset() || currentState.previous !== undefined)) {
            sitchco.hooks.doAction(HASH_STATE_CHANGE, currentState);
        }
    },
    isset() {
        return currentState.isset();
    },
    get() {
        return currentState;
    },
    set(newState, { push = false } = {}) {
        if (typeof newState === 'string') {
            const cleaned = newState.replace(/^[#/]+/, '');
            if (push) {
                location.hash = cleaned;
                return; // hashchange listener handles state update + emit
            }

            history.replaceState(null, '', `#${cleaned}`);
        }

        currentState = new HashState(currentState);
        this.emit();
    },
    clear() {
        history.replaceState(null, '', window.location.pathname + window.location.search);
        currentState = new HashState(currentState);
        this.emit();
    },
    setList(stateList, options) {
        this.set(stateList.join('/'), options);
    },
};

export function registerHashStateActions() {
    sitchco.hooks.addAction(
        READY,
        () => {
            hashState.emit();
            window.addEventListener('hashchange', () => {
                hashState.set();
            });
        },
        99
    );

    sitchco.hooks.addAction(SET_HASH_STATE, (hash, options) => {
        hashState.set(hash, options);
    });
}
