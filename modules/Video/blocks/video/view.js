/**
 * Click-to-play orchestrator for sitchco/video block.
 *
 * Handles three display modes:
 * - inline: click poster to play video in-place (replaces poster)
 * - modal: click poster to open dialog with video player
 * - modal-only: dialog triggered by external links (UIModal handles triggers)
 *
 * Provider-specific logic (YouTube, Vimeo) is delegated to provider modules
 * in lib/youtube.js and lib/vimeo.js. This orchestrator is provider-agnostic,
 * interacting with players through a normalized adapter interface.
 *
 * Uses sitchco.register() lifecycle for initialization.
 *
 * Phase 4 additions:
 * - activePlayers registry for mutual exclusion (MXCL-01, MXCL-02)
 * - Lifecycle hooks: video-play, video-pause, video-ended, video-progress (ANLT-01 -- ANLT-03, EXTN-01, EXTN-03)
 * - video-request-pause subscriber (EXTN-02, NOOP-02)
 * - Milestone progress polling at 25/50/75% (ANLT-02)
 * - JS filters for player parameters (EXTN-04, EXTN-05)
 */
import * as youtube from './lib/youtube.js';
import * as vimeo from './lib/vimeo.js';
import { startMilestonePolling, stopMilestonePolling } from './lib/milestones.js';
import { activePlayers, pausePlayerById, registerActivePlayer, nextInstanceId } from './lib/active-players.js';

/**
 * Provider dispatch map.
 * Each provider module exports: loadSDK(), createPlayer(), extractStartTime().
 */
const providers = {
    youtube,
    vimeo,
};

/**
 * Look up a provider module by name.
 *
 * @param {string} name - Provider name ('youtube' or 'vimeo').
 * @returns {Object|null} Provider module or null.
 */
function getProvider(name) {
    return providers[name] || null;
}

/**
 * Modal player instance storage.
 * Maps modalId -> { adapter: PlayerAdapter|null, provider: string, loading: boolean, cancelled: boolean }
 * Enables pause on close and resume on reopen without creating duplicate iframes.
 */
const modalPlayers = new Map();

/**
 * Replace a video container's content with an error fallback.
 * Cleans up milestone polling, active player entry, and modal player entry.
 *
 * @param {Element} container - The element to replace content in.
 * @param {string} url - Original video URL for the fallback link.
 * @param {string} provider - 'youtube' or 'vimeo'.
 * @param {string} instanceId - Unique player instance ID.
 * @param {string|null} [modalId] - Modal ID to clean up, if applicable.
 */
function showErrorFallback(container, url, provider, instanceId, modalId) {
    stopMilestonePolling(instanceId);
    activePlayers.delete(instanceId);

    if (modalId && modalPlayers.has(modalId)) {
        modalPlayers.delete(modalId);
    }

    const providerLabel = provider === 'youtube' ? 'YouTube' : 'Vimeo';
    const errorDiv = document.createElement('div');
    errorDiv.className = 'sitchco-video__runtime-error';
    const message = document.createElement('p');
    message.className = 'sitchco-video__runtime-error-message';
    message.textContent = 'This video couldn\u2019t be loaded.';
    const link = document.createElement('a');
    link.className = 'sitchco-video__runtime-error-link';
    link.href = url;
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    link.textContent = 'Watch on ' + providerLabel;
    errorDiv.appendChild(message);
    errorDiv.appendChild(link);
    container.innerHTML = '';
    container.appendChild(errorDiv);
}

/**
 * Build the common callback set for provider createPlayer().
 * Captures instanceId, videoId, provider name, URL, and container in closure scope
 * so provider modules never see orchestrator state.
 *
 * @param {Object} opts
 * @param {string} opts.instanceId - Unique player instance ID.
 * @param {string} opts.videoId - Provider video ID.
 * @param {string} opts.providerName - Provider name.
 * @param {string} opts.url - Original video URL.
 * @param {Element} opts.container - Container element (for error fallback).
 * @param {string|null} opts.modalId - Modal ID if modal mode, null for inline.
 * @param {Function} [opts.onReadyExtra] - Additional logic to run on ready (before play).
 * @returns {Object} Callback set: { onReady, onPlay, onPause, onEnded, onError }
 */
function buildPlayerCallbacks({ instanceId, videoId, providerName, url, container, modalId, onReadyExtra }) {
    return {
        onReady: function (adapter) {
            if (onReadyExtra) {
                if (onReadyExtra(adapter) === false) {
                    return;
                }
            }

            adapter.play();
        },
        onPlay: function (adapter) {
            registerActivePlayer(instanceId, videoId, adapter, providerName, url);
            sitchco.hooks.doAction('video-play', {
                id: videoId,
                provider: providerName,
                url: url,
            });

            startMilestonePolling(instanceId, videoId, adapter, providerName, url);
        },
        onPause: function () {
            sitchco.hooks.doAction('video-pause', {
                id: videoId,
                provider: providerName,
                url: url,
            });

            stopMilestonePolling(instanceId);
        },
        onEnded: function () {
            sitchco.hooks.doAction('video-progress', {
                id: videoId,
                provider: providerName,
                url: url,
                milestone: 100,
            });

            sitchco.hooks.doAction('video-ended', {
                id: videoId,
                provider: providerName,
                url: url,
            });

            stopMilestonePolling(instanceId);
            activePlayers.delete(instanceId);
        },
        onError: function () {
            showErrorFallback(container, url, providerName, instanceId, modalId);
        },
    };
}

/**
 * Bind a click handler to an element.
 * Passes the event to the callback when provided; always calls callback.
 *
 * @param {Element} element - The element to bind to.
 * @param {Function} callback - Called on click. Receives the MouseEvent.
 * @param {Object} [options] - addEventListener options (e.g. { once: true }).
 */
function bindPlayTrigger(element, callback, options) {
    element.addEventListener('click', callback, options || {});
}

/**
 * Bind a keyboard (Enter/Space) handler to an element with role="button".
 * No-ops if the element does not have role="button".
 *
 * @param {Element} element - The element to bind to.
 * @param {Function} callback - Called when Enter or Space is pressed.
 * @param {Object} [options] - addEventListener options (e.g. { once: true }).
 */
function bindKeyboardTrigger(element, callback, options) {
    if (element.getAttribute('role') !== 'button') {
        return;
    }

    element.addEventListener(
        'keydown',
        function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                callback();
            }
        },
        options || {}
    );
}

/**
 * Handle modal hide event from UIModal hook.
 * Pauses video on any close method (Escape, backdrop, close button).
 * Skips non-video modals (no modalPlayers entry).
 * If the SDK is still loading, sets a cancellation flag so the onReady
 * callback won't autoplay after the modal has already been closed.
 */
function handleModalHide(modal) {
    const entry = modalPlayers.get(modal.id);
    if (!entry) {
        return;
    }
    if (!entry.adapter) {
        if (entry.loading) {
            entry.cancelled = true;
        }
        return;
    }

    entry.adapter.pause();
}

/**
 * Handle modal open event from UIModal hook.
 * Loads SDK and autoplays on first open, resumes on subsequent opens.
 * Skips non-video modals (no .sitchco-video__modal-player container).
 *
 * Registered at priority 20 (after UIModal core at 10) to run after
 * the dialog is already open via showModal().
 */
function handleModalShow(modal) {
    const playerContainer = modal.querySelector('.sitchco-video__modal-player');
    if (!playerContainer) {
        return; // Not a video modal
    }

    const modalId = modal.id;
    const entry = modalPlayers.get(modalId);
    // Resume existing player
    if (entry && entry.adapter) {
        entry.cancelled = false;
        entry.adapter.play();
        return;
    }
    // Prevent double-creation during SDK load
    if (entry && entry.loading) {
        entry.cancelled = false;
        return;
    }

    // First open: load SDK and create player
    const providerName = playerContainer.dataset.provider;
    const provider = getProvider(providerName);
    if (!provider) {
        return;
    }

    const videoId = playerContainer.dataset.videoId;
    const url = playerContainer.dataset.url;
    const startTime = provider.extractStartTime(url);
    const instanceId = nextInstanceId();

    modalPlayers.set(modalId, {
        adapter: null,
        provider: providerName,
        loading: true,
        cancelled: false,
    });

    // Clear stale error fallback if reopening after a runtime error
    const staleError = playerContainer.querySelector('.sitchco-video__runtime-error');
    if (staleError) {
        staleError.remove();
    }

    const target = document.createElement('div');
    target.className = 'sitchco-video__player';
    playerContainer.appendChild(target);

    const callbacks = buildPlayerCallbacks({
        instanceId: instanceId,
        videoId: videoId,
        providerName: providerName,
        url: url,
        container: playerContainer,
        modalId: modalId,
        onReadyExtra: function (adapter) {
            const modalEntry = modalPlayers.get(modalId);
            if (modalEntry) {
                modalEntry.adapter = adapter;
                modalEntry.loading = false;

                if (modalEntry.cancelled) {
                    modalEntry.cancelled = false;
                    adapter.pause();
                    return false;
                }
            }

            playerContainer.classList.add('sitchco-video__modal-player--ready');
        },
    });

    provider.createPlayer(target, videoId, {
        startTime: startTime,
        url: url,
        displayMode: 'modal',
        onReady: callbacks.onReady,
        onPlay: callbacks.onPlay,
        onPause: callbacks.onPause,
        onEnded: callbacks.onEnded,
        onError: callbacks.onError,
    });
}

/**
 * Handle play interaction on a video block wrapper.
 * Locks dimensions (INLN-02), hides poster (INLN-03), creates player.
 */
function handlePlay(wrapper) {
    // Guard against double activation (e.g. rapid clicks in poster mode)
    if (wrapper.classList.contains('sitchco-video--playing')) {
        return;
    }

    // INLN-02: Lock dimensions before any DOM changes to prevent layout shift
    wrapper.style.width = wrapper.offsetWidth + 'px';
    wrapper.style.height = wrapper.offsetHeight + 'px';

    // INLN-03: Hide poster and play button via CSS class toggle
    wrapper.classList.add('sitchco-video--playing');

    // Remove interactive attributes (no longer needed after activation)
    wrapper.removeAttribute('role');
    wrapper.removeAttribute('tabindex');
    wrapper.removeAttribute('aria-label');

    const providerName = wrapper.dataset.provider;
    const provider = getProvider(providerName);
    if (!provider) {
        return;
    }

    const url = wrapper.dataset.url;
    const videoId = wrapper.dataset.videoId;
    const startTime = provider.extractStartTime(url);
    const instanceId = nextInstanceId();

    // Create player container
    const target = document.createElement('div');
    target.className = 'sitchco-video__player';
    wrapper.appendChild(target);

    const callbacks = buildPlayerCallbacks({
        instanceId: instanceId,
        videoId: videoId,
        providerName: providerName,
        url: url,
        container: wrapper,
        modalId: null,
    });

    provider.createPlayer(target, videoId, {
        startTime: startTime,
        url: url,
        displayMode: 'inline',
        onReady: callbacks.onReady,
        onPlay: callbacks.onPlay,
        onPause: callbacks.onPause,
        onEnded: callbacks.onEnded,
        onError: callbacks.onError,
    });
}

/**
 * Initialize a single video block wrapper.
 * Attaches click and keyboard handlers for play interaction.
 * Handles both inline mode (play in-place) and modal mode (open dialog).
 */
function initVideoBlock(wrapper) {
    // Skip blocks flagged as unavailable by server-side fallback
    if (wrapper.dataset.videoUnavailable) {
        return;
    }

    const displayMode = wrapper.dataset.displayMode;
    if (displayMode === 'modal') {
        // Modal mode: click poster -> open modal (not inline play)
        const modalId = wrapper.dataset.modalId;
        const modal = document.getElementById(modalId);
        if (!modal) {
            return;
        }

        const modalClickBehavior = wrapper.dataset.clickBehavior;
        const modalClickTarget =
            modalClickBehavior === 'icon' ? wrapper.querySelector('.sitchco-video__play-button') : wrapper;
        if (!modalClickTarget) {
            return;
        }

        // Click handler (NOT { once: true } -- modal can be opened multiple times)
        bindPlayTrigger(modalClickTarget, function (e) {
            e.preventDefault();
            sitchco.hooks.doAction('ui-modal-show', modal);
        });

        // Keyboard handler for wrapper with role="button"
        bindKeyboardTrigger(wrapper, function () {
            sitchco.hooks.doAction('ui-modal-show', modal);
        });
        return;
    }
    // Modal-only blocks have no on-page wrapper -- skip
    if (displayMode !== 'inline') {
        return;
    }

    const clickBehavior = wrapper.dataset.clickBehavior;
    let clickTarget;
    if (clickBehavior === 'icon') {
        clickTarget = wrapper.querySelector('.sitchco-video__play-button');

        if (!clickTarget) {
            return;
        }
    } else {
        // Default: poster click mode -- entire wrapper is the click target
        clickTarget = wrapper;
        // Make poster inert so child interactive elements (links, buttons inside
        // InnerBlocks) can't intercept clicks or receive keyboard focus.
        const posterEl = wrapper.querySelector('.sitchco-video__poster');
        if (posterEl) {
            posterEl.inert = true;
        }
    }

    // Click handler (once: true ensures single activation)
    // Note: inline click does NOT preventDefault -- it just triggers play
    bindPlayTrigger(
        clickTarget,
        function () {
            handlePlay(wrapper);
        },
        { once: true }
    );

    // Keyboard handler for wrapper with role="button" (Enter/Space), once only
    bindKeyboardTrigger(
        wrapper,
        function () {
            handlePlay(wrapper);
        },
        { once: true }
    );
}

// Register modal hooks at priority 20 (after UIModal core at 10).
sitchco.hooks.addAction('ui-modal-show', handleModalShow, 20, 'video-block');
sitchco.hooks.addAction('ui-modal-hide', handleModalHide, 20, 'video-block');

// External pause request -- callers can pause a specific video by provider video ID.
// SDK fires native pause event when pause() is called via the adapter, which triggers
// the provider's pause listener, so video-pause hook fires naturally.
// (EXTN-02, NOOP-02)
sitchco.hooks.addAction(
    'video-request-pause',
    function (videoId) {
        pausePlayerById(videoId);
        // Stop milestone polling for all instances of this video ID
        activePlayers.forEach(function (entry, instanceId) {
            if (entry.videoId === videoId) {
                stopMilestonePolling(instanceId);
            }
        });
    },
    10,
    'video-block'
);

// Register with sitchco lifecycle (runs on DOMContentLoaded)
sitchco.register(function initVideoBlocks() {
    document.querySelectorAll('.sitchco-video[data-url]').forEach(initVideoBlock);
});
