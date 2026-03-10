/* global Vimeo */

/**
 * Click-to-play playback for sitchco/video block.
 *
 * Handles three display modes:
 * - inline: click poster to play video in-place (replaces poster)
 * - modal: click poster to open dialog with video player
 * - modal-only: dialog triggered by external links (UIModal handles triggers)
 *
 * Loads YouTube IFrame API or Vimeo Player SDK on first interaction only.
 * Uses sitchco.register() lifecycle for initialization and
 * sitchco.loadScript() for deduplicating SDK loads.
 *
 * Privacy: YouTube uses youtube-nocookie.com, Vimeo uses dnt:true.
 * No provider SDK, iframe, or CDN resource loads before user interacts.
 *
 * Phase 4 additions:
 * - activePlayers registry for mutual exclusion (MXCL-01, MXCL-02)
 * - Lifecycle hooks: video-play, video-pause, video-ended, video-progress (ANLT-01 -- ANLT-03, EXTN-01, EXTN-03)
 * - video-request-pause subscriber (EXTN-02, NOOP-02)
 * - Milestone progress polling at 25/50/75% (ANLT-02)
 * - JS filters for player parameters (EXTN-04, EXTN-05)
 */

/**
 * YouTube IFrame API singleton loader.
 * Wraps the global onYouTubeIframeAPIReady callback in a Promise.
 * Deduplicates via sitchco.loadScript() and module-level promise cache.
 */
let ytAPIPromise = null;

/**
 * Modal player instance storage.
 * Maps modalId -> { player: SDKPlayer|null, provider: string, loading: boolean, cancelled: boolean }
 * Enables pause on close and resume on reopen without creating duplicate iframes.
 */
const modalPlayers = new Map();

/**
 * Active player registry for mutual exclusion.
 * Maps videoId -> { player: SDKPlayer, provider: 'youtube'|'vimeo', url: string }
 * Only one player is active at a time (MXCL-01).
 */
const activePlayers = new Map();

/**
 * Milestone polling interval storage.
 * Maps videoId -> intervalId
 */
const pollIntervals = new Map();

/**
 * Tracks which milestones have fired per video.
 * Maps videoId -> Set<number> (set of fired percentages: 25, 50, 75)
 * Never cleared -- milestones fire at most once per page load.
 */
const milestonesFired = new Map();

/**
 * Progress milestones to poll for (percent values).
 * 100% is handled by the ended event, not polling.
 */
const MILESTONES = [25, 50, 75];

/**
 * Pause a player by its provider video ID.
 * No-ops if the videoId is not in the activePlayers registry.
 *
 * @param {string} videoId - Provider video ID.
 */
function pausePlayerById(videoId) {
    const entry = activePlayers.get(videoId);
    if (!entry) {
        return;
    }
    if (entry.provider === 'youtube') {
        entry.player.pauseVideo();
    } else {
        entry.player.pause();
    }
}

/**
 * Register a player as active, pausing all other active players first.
 * Implements mutual exclusion (MXCL-01, MXCL-02): only one video plays at a time.
 *
 * @param {string} videoId - Provider video ID.
 * @param {Object} player - SDK player instance.
 * @param {'youtube'|'vimeo'} provider - Player provider.
 * @param {string} url - Original video URL.
 */
function registerActivePlayer(videoId, player, provider, url) {
    activePlayers.forEach(function (entry, id) {
        if (id !== videoId) {
            if (entry.provider === 'youtube') {
                entry.player.pauseVideo();
            } else {
                entry.player.pause();
            }
        }
    });

    activePlayers.set(videoId, {
        player: player,
        provider: provider,
        url: url,
    });
}

/**
 * Check milestone percentages and fire video-progress hooks for newly crossed thresholds.
 * Handles seeking past multiple milestones correctly -- all crossed milestones fire.
 *
 * @param {string} videoId - Provider video ID.
 * @param {'youtube'|'vimeo'} provider - Player provider.
 * @param {string} url - Original video URL.
 * @param {number} current - Current playback time in seconds.
 * @param {number} duration - Total video duration in seconds.
 */
function checkMilestones(videoId, provider, url, current, duration) {
    if (!duration || duration <= 0) {
        return;
    }

    const pct = (current / duration) * 100;
    const fired = milestonesFired.get(videoId);
    MILESTONES.forEach(function (milestone) {
        if (pct >= milestone && !fired.has(milestone)) {
            fired.add(milestone);
            sitchco.hooks.doAction('video-progress', {
                id: videoId,
                provider: provider,
                url: url,
                milestone: milestone,
            });
        }
    });
}

/**
 * Start polling for milestone progress on a playing video.
 * Polls every 1 second. No-ops if polling is already active for this videoId.
 *
 * @param {string} videoId - Provider video ID.
 * @param {Object} player - SDK player instance.
 * @param {'youtube'|'vimeo'} provider - Player provider.
 * @param {string} url - Original video URL.
 */
function startMilestonePolling(videoId, player, provider, url) {
    if (pollIntervals.has(videoId)) {
        return;
    }
    if (!milestonesFired.has(videoId)) {
        milestonesFired.set(videoId, new Set());
    }

    let intervalId;
    if (provider === 'youtube') {
        intervalId = setInterval(function () {
            const current = player.getCurrentTime();
            const duration = player.getDuration();
            checkMilestones(videoId, provider, url, current, duration);
        }, 1000);
    } else {
        intervalId = setInterval(function () {
            Promise.all([player.getCurrentTime(), player.getDuration()])
                .then(function ([current, duration]) {
                    checkMilestones(videoId, provider, url, current, duration);
                })
                .catch(function () {
                    // Player may be destroyed mid-poll -- ignore silently
                });
        }, 1000);
    }

    pollIntervals.set(videoId, intervalId);
}

/**
 * Stop milestone polling for a video.
 * Does NOT clear milestonesFired -- milestones fire at most once per page load.
 *
 * @param {string} videoId - Provider video ID.
 */
function stopMilestonePolling(videoId) {
    const intervalId = pollIntervals.get(videoId);
    if (intervalId !== undefined) {
        clearInterval(intervalId);
        pollIntervals.delete(videoId);
    }
}

function loadYouTubeAPI() {
    if (ytAPIPromise) {
        return ytAPIPromise;
    }
    if (window.YT && window.YT.Player) {
        ytAPIPromise = Promise.resolve(window.YT);
        return ytAPIPromise;
    }

    ytAPIPromise = new Promise(function (resolve) {
        const prev = window.onYouTubeIframeAPIReady;

        window.onYouTubeIframeAPIReady = function () {
            if (prev) {
                prev();
            }

            resolve(window.YT);
        };

        sitchco.loadScript('youtube-iframe-api', 'https://www.youtube.com/iframe_api');
    });
    return ytAPIPromise;
}

/**
 * Create a YouTube player inside the given container.
 * Uses youtube-nocookie.com for privacy (PRIV-02).
 * Autoplay on ready (INLN-05). Start time from URL (INLN-06).
 * Fires lifecycle hooks: video-play, video-pause, video-ended (ANLT-01, ANLT-03, EXTN-01, EXTN-03).
 * Applies sitchco/video/playerVars/youtube filter before player creation (EXTN-04).
 *
 * When modalId is provided (modal mode): creates a wrapper div inside container,
 * stores the player reference in modalPlayers on ready, and adds a --ready class.
 * When modalId is null (inline mode): uses container directly with no modalPlayers interaction.
 *
 * @param {Element} container - DOM element to create the player in.
 * @param {string} videoId - YouTube video ID.
 * @param {number} startTime - Start time in seconds.
 * @param {string|null} modalId - Modal ID if in modal mode, null for inline.
 * @param {string} url - Original video URL.
 * @param {string} displayMode - Display mode ('inline', 'modal', 'modal-only').
 */
function createYouTubePlayer(container, videoId, startTime, modalId, url, displayMode) {
    modalId = modalId || null;
    const target = modalId
        ? (function () {
              const wrapper = document.createElement('div');
              wrapper.className = 'sitchco-video__player';
              container.appendChild(wrapper);
              return wrapper;
          })()
        : container;

    loadYouTubeAPI()
        .then(function (YT) {
            const defaultPlayerVars = {
                autoplay: 1,
                playsinline: 1,
                enablejsapi: 1,
                origin: window.location.origin,
                start: startTime,
                rel: 0,
                iv_load_policy: 3,
            };
            const playerVars = sitchco.hooks.applyFilters('sitchco/video/playerVars/youtube', defaultPlayerVars, {
                url: url,
                videoId: videoId,
                displayMode: displayMode,
            });

            new YT.Player(target, {
                videoId: videoId,
                host: 'https://www.youtube-nocookie.com',
                playerVars: playerVars,
                events: {
                    onReady: function (event) {
                        if (modalId) {
                            const entry = modalPlayers.get(modalId);
                            if (entry) {
                                entry.player = event.target;
                                entry.loading = false;

                                if (entry.cancelled) {
                                    entry.cancelled = false;
                                    event.target.pauseVideo();
                                    return;
                                }
                            }

                            container.classList.add('sitchco-video__modal-player--ready');
                        }

                        event.target.playVideo();
                    },
                    onStateChange: function (event) {
                        if (event.data === YT.PlayerState.PLAYING) {
                            registerActivePlayer(videoId, event.target, 'youtube', url);
                            sitchco.hooks.doAction('video-play', {
                                id: videoId,
                                provider: 'youtube',
                                url: url,
                            });

                            startMilestonePolling(videoId, event.target, 'youtube', url);
                        } else if (event.data === YT.PlayerState.PAUSED) {
                            sitchco.hooks.doAction('video-pause', {
                                id: videoId,
                                provider: 'youtube',
                                url: url,
                            });

                            stopMilestonePolling(videoId);
                        } else if (event.data === YT.PlayerState.ENDED) {
                            sitchco.hooks.doAction('video-progress', {
                                id: videoId,
                                provider: 'youtube',
                                url: url,
                                milestone: 100,
                            });

                            sitchco.hooks.doAction('video-ended', {
                                id: videoId,
                                provider: 'youtube',
                                url: url,
                            });

                            stopMilestonePolling(videoId);
                            activePlayers.delete(videoId);
                        }
                    },
                },
            });
        })
        .catch(function (err) {
            console.error('sitchco-video: YouTube SDK load failed', err);
        });
}

/**
 * Load the Vimeo Player SDK from CDN.
 * Uses sitchco.loadScript() for deduplication.
 */
function loadVimeoSDK() {
    return sitchco.loadScript('vimeo-player', 'https://player.vimeo.com/api/player.js');
}

/**
 * Create a Vimeo player inside the given container.
 * Uses dnt:true for privacy (PRIV-03).
 * Autoplay on creation (INLN-05). Start time from URL (INLN-06).
 * Fires lifecycle hooks: video-play, video-pause, video-ended (ANLT-01, ANLT-03, EXTN-01, EXTN-03).
 * Applies sitchco/video/playerVars/vimeo filter before player creation (EXTN-05).
 *
 * When modalId is provided (modal mode): creates a wrapper div inside container,
 * stores the player reference in modalPlayers on ready, and adds a --ready class.
 * When modalId is null (inline mode): uses container directly with no modalPlayers interaction.
 *
 * @param {Element} container - DOM element to create the player in.
 * @param {string} videoId - Vimeo video ID.
 * @param {number} startTime - Start time in seconds.
 * @param {string|null} modalId - Modal ID if in modal mode, null for inline.
 * @param {string} url - Original video URL.
 * @param {string} displayMode - Display mode ('inline', 'modal', 'modal-only').
 */
function createVimeoPlayer(container, videoId, startTime, modalId, url, displayMode) {
    modalId = modalId || null;
    loadVimeoSDK()
        .then(function () {
            const target = modalId
                ? (function () {
                      const wrapper = document.createElement('div');
                      wrapper.className = 'sitchco-video__player';
                      container.appendChild(wrapper);
                      return wrapper;
                  })()
                : container;

            const defaultOptions = {
                id: parseInt(videoId, 10),
                autoplay: true,
                dnt: true,
                title: false,
                byline: false,
                portrait: false,
                badge: false,
                vimeo_logo: false,
            };
            const options = sitchco.hooks.applyFilters('sitchco/video/playerVars/vimeo', defaultOptions, {
                url: url,
                videoId: videoId,
                displayMode: displayMode,
            });

            const player = new Vimeo.Player(target, options);

            player.ready().then(function () {
                if (modalId) {
                    const entry = modalPlayers.get(modalId);
                    if (entry) {
                        entry.player = player;
                        entry.loading = false;

                        if (entry.cancelled) {
                            entry.cancelled = false;
                            player.pause();
                            return;
                        }
                    }

                    container.classList.add('sitchco-video__modal-player--ready');
                }
                if (startTime > 0) {
                    player.setCurrentTime(startTime);
                }
            });

            player.on('play', function () {
                registerActivePlayer(videoId, player, 'vimeo', url);
                sitchco.hooks.doAction('video-play', {
                    id: videoId,
                    provider: 'vimeo',
                    url: url,
                });

                startMilestonePolling(videoId, player, 'vimeo', url);
            });

            player.on('pause', function () {
                sitchco.hooks.doAction('video-pause', {
                    id: videoId,
                    provider: 'vimeo',
                    url: url,
                });

                stopMilestonePolling(videoId);
            });

            player.on('ended', function () {
                sitchco.hooks.doAction('video-progress', {
                    id: videoId,
                    provider: 'vimeo',
                    url: url,
                    milestone: 100,
                });

                sitchco.hooks.doAction('video-ended', {
                    id: videoId,
                    provider: 'vimeo',
                    url: url,
                });

                stopMilestonePolling(videoId);
                activePlayers.delete(videoId);
            });
        })
        .catch(function (err) {
            console.error('sitchco-video: Vimeo SDK load failed', err);
        });
}

/**
 * Extract start time (in seconds) from a YouTube URL.
 * Handles: ?t=90, ?t=90s, ?t=1m30s, ?t=1h2m30s, ?start=90, &t=90
 */
function extractYouTubeStartTime(url) {
    try {
        const urlObj = new URL(url);
        let t = urlObj.searchParams.get('t') || urlObj.searchParams.get('start');
        if (!t) {
            return 0;
        }

        t = String(t);
        const hMatch = t.match(/(\d+)h/);
        const mMatch = t.match(/(\d+)m/);
        const sMatch = t.match(/(\d+)s?$/);

        let seconds = 0;
        if (hMatch) {
            seconds += parseInt(hMatch[1], 10) * 3600;
        }
        if (mMatch) {
            seconds += parseInt(mMatch[1], 10) * 60;
        }
        if (sMatch && !mMatch && !hMatch) {
            seconds = parseInt(sMatch[1], 10);
        } else if (sMatch) {
            seconds += parseInt(sMatch[1], 10);
        }
        return seconds;
    } catch {
        return 0;
    }
}

/**
 * Extract start time (in seconds) from a Vimeo URL.
 * Handles: #t=90s, #t=90, #t=1m30s, #t=1h2m30s
 */
function extractVimeoStartTime(url) {
    const hash = url.split('#')[1] || '';
    const tMatch = hash.match(/t=([\dhms]+)/);
    if (!tMatch) {
        return 0;
    }

    const t = tMatch[1];
    const hMatch = t.match(/(\d+)h/);
    const mMatch = t.match(/(\d+)m/);
    const sMatch = t.match(/(\d+)s?$/);
    let seconds = 0;
    if (hMatch) {
        seconds += parseInt(hMatch[1], 10) * 3600;
    }
    if (mMatch) {
        seconds += parseInt(mMatch[1], 10) * 60;
    }
    if (sMatch && !mMatch && !hMatch) {
        seconds = parseInt(sMatch[1], 10);
    } else if (sMatch) {
        seconds += parseInt(sMatch[1], 10);
    }
    return seconds;
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
    if (!entry.player) {
        if (entry.loading) {
            entry.cancelled = true;
        }
        return;
    }
    if (entry.provider === 'youtube') {
        entry.player.pauseVideo();
    } else {
        entry.player.pause();
    }
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
    if (entry && entry.player) {
        entry.cancelled = false;

        if (entry.provider === 'youtube') {
            entry.player.playVideo();
        } else {
            entry.player.play();
        }
        return;
    }
    // Prevent double-creation during SDK load
    if (entry && entry.loading) {
        entry.cancelled = false;
        return;
    }

    // First open: load SDK and create player
    const provider = playerContainer.dataset.provider;
    const videoId = playerContainer.dataset.videoId;
    const url = playerContainer.dataset.url;
    const startTime = provider === 'youtube' ? extractYouTubeStartTime(url) : extractVimeoStartTime(url);

    modalPlayers.set(modalId, {
        player: null,
        provider: provider,
        loading: true,
    });

    if (provider === 'youtube') {
        createYouTubePlayer(playerContainer, videoId, startTime, modalId, url, 'modal');
    } else if (provider === 'vimeo') {
        createVimeoPlayer(playerContainer, videoId, startTime, modalId, url, 'modal');
    }
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

    // Create player container
    const playerContainer = document.createElement('div');
    playerContainer.className = 'sitchco-video__player';
    wrapper.appendChild(playerContainer);

    // Load SDK and create player based on provider
    const provider = wrapper.dataset.provider;
    const url = wrapper.dataset.url;
    const videoId = wrapper.dataset.videoId;
    if (provider === 'youtube') {
        createYouTubePlayer(playerContainer, videoId, extractYouTubeStartTime(url), null, url, 'inline');
    } else if (provider === 'vimeo') {
        createVimeoPlayer(playerContainer, videoId, extractVimeoStartTime(url), null, url, 'inline');
    }
}

/**
 * Initialize a single video block wrapper.
 * Attaches click and keyboard handlers for play interaction.
 * Handles both inline mode (play in-place) and modal mode (open dialog).
 */
function initVideoBlock(wrapper) {
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
        // Suppress pointer events on the poster div so child interactive elements
        // (links, buttons inside InnerBlocks) don't intercept the wrapper click.
        const posterEl = wrapper.querySelector('.sitchco-video__poster');
        if (posterEl) {
            posterEl.style.pointerEvents = 'none';
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
// SDK fires native pause event when pauseVideo()/pause() is called, which triggers
// the onStateChange(PAUSED)/Vimeo 'pause' listener, so video-pause hook fires naturally.
// (EXTN-02, NOOP-02)
sitchco.hooks.addAction(
    'video-request-pause',
    function (videoId) {
        pausePlayerById(videoId);
        stopMilestonePolling(videoId);
    },
    10,
    'video-block'
);

// Register with sitchco lifecycle (runs on DOMContentLoaded)
sitchco.register(function initVideoBlocks() {
    document.querySelectorAll('.sitchco-video[data-url]').forEach(initVideoBlock);
});
