const EDITOR_INIT = 'editorInit';
const EDITOR_READY = 'editorReady';

const editorInit = (cb, priority = 100) => sitchco.hooks.addAction(EDITOR_INIT, cb, priority);
const editorReady = (cb, priority = 100) => sitchco.hooks.addAction(EDITOR_READY, cb, priority);

let flushed = false;

function editorFlush() {
    if (flushed) {
        return;
    }

    flushed = true;
    sitchco.hooks.doAction(EDITOR_INIT);
    sitchco.hooks.doAction(EDITOR_READY);
}

window.sitchco = Object.assign(window.sitchco || {}, {
    editorInit,
    editorReady,
    editorFlush,
});

// Fallback if PHP flush doesn't fire
document.addEventListener('DOMContentLoaded', editorFlush);
