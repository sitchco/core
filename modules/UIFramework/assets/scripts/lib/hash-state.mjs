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
        if (currentState.isset() && currentState.hasChanged()) {
            sitchco.hooks.doAction(HASH_STATE_CHANGE, currentState);
        }
    },
    isset() {
        return currentState.isset();
    },
    get() {
        return currentState;
    },
    set(newState) {
        if (typeof newState === 'string') {
            location.hash = `/${newState.replace(/^\/+/, '')}`;
            return;
        }

        currentState = new HashState(currentState);
        this.emit();
    },
    setList(stateList) {
        this.set(stateList.join('/'));
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

    sitchco.hooks.addAction(SET_HASH_STATE, (hash) => {
        hashState.set(hash);
    });
}
