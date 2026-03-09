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
 */

/**
 * YouTube IFrame API singleton loader.
 * Wraps the global onYouTubeIframeAPIReady callback in a Promise.
 * Deduplicates via sitchco.loadScript() and module-level promise cache.
 */
var ytAPIPromise = null;

/**
 * Modal player instance storage.
 * Maps modalId -> { player: SDKPlayer|null, provider: string, loading: boolean }
 * Enables pause on close and resume on reopen without creating duplicate iframes.
 */
var modalPlayers = new Map();

function loadYouTubeAPI() {
    if (ytAPIPromise) {
        return ytAPIPromise;
    }
    if (window.YT && window.YT.Player) {
        ytAPIPromise = Promise.resolve(window.YT);
        return ytAPIPromise;
    }

    ytAPIPromise = new Promise(function (resolve) {
        var prev = window.onYouTubeIframeAPIReady;

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
 */
function createYouTubePlayer(container, videoId, startTime) {
    loadYouTubeAPI()
        .then(function (YT) {
            new YT.Player(container, {
                videoId: videoId,
                host: 'https://www.youtube-nocookie.com',
                playerVars: {
                    autoplay: 1,
                    playsinline: 1,
                    enablejsapi: 1,
                    origin: window.location.origin,
                    start: startTime,
                    rel: 0,
                },
                events: {
                    onReady: function (event) {
                        event.target.playVideo();
                    },
                },
            });
        })
        .catch(function (err) {
            console.error('sitchco-video: YouTube SDK load failed', err);
        });
}

/**
 * Create a YouTube player inside a modal dialog container.
 * Stores player reference in modalPlayers Map for pause/resume lifecycle.
 * Uses youtube-nocookie.com for privacy (PRIV-02).
 */
function createModalYouTubePlayer(container, videoId, startTime, modalId) {
    loadYouTubeAPI()
        .then(function (YT) {
            var iframeContainer = document.createElement('div');
            iframeContainer.className = 'sitchco-video__player';
            container.appendChild(iframeContainer);

            new YT.Player(iframeContainer, {
                videoId: videoId,
                host: 'https://www.youtube-nocookie.com',
                playerVars: {
                    autoplay: 1,
                    playsinline: 1,
                    enablejsapi: 1,
                    origin: window.location.origin,
                    start: startTime,
                    rel: 0,
                },
                events: {
                    onReady: function (event) {
                        var entry = modalPlayers.get(modalId);
                        if (entry) {
                            entry.player = event.target;
                            entry.loading = false;
                        }

                        container.classList.add('sitchco-video__modal-player--ready');
                        event.target.playVideo();
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
 */
function createVimeoPlayer(container, videoId, startTime) {
    loadVimeoSDK()
        .then(function () {
            var player = new Vimeo.Player(container, {
                id: parseInt(videoId, 10),
                autoplay: true,
                dnt: true,
            });
            if (startTime > 0) {
                player.ready().then(function () {
                    player.setCurrentTime(startTime);
                });
            }
        })
        .catch(function (err) {
            console.error('sitchco-video: Vimeo SDK load failed', err);
        });
}

/**
 * Create a Vimeo player inside a modal dialog container.
 * Stores player reference in modalPlayers Map for pause/resume lifecycle.
 * Uses dnt:true for privacy (PRIV-03).
 */
function createModalVimeoPlayer(container, videoId, startTime, modalId) {
    loadVimeoSDK()
        .then(function () {
            var iframeContainer = document.createElement('div');
            iframeContainer.className = 'sitchco-video__player';
            container.appendChild(iframeContainer);

            var player = new Vimeo.Player(iframeContainer, {
                id: parseInt(videoId, 10),
                autoplay: true,
                dnt: true,
            });

            player.ready().then(function () {
                var entry = modalPlayers.get(modalId);
                if (entry) {
                    entry.player = player;
                    entry.loading = false;
                }

                container.classList.add('sitchco-video__modal-player--ready');

                if (startTime > 0) {
                    player.setCurrentTime(startTime);
                }
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
        var urlObj = new URL(url);
        var t = urlObj.searchParams.get('t') || urlObj.searchParams.get('start');
        if (!t) {
            return 0;
        }

        t = String(t);
        var hMatch = t.match(/(\d+)h/);
        var mMatch = t.match(/(\d+)m/);
        var sMatch = t.match(/(\d+)s?$/);

        var seconds = 0;
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
 * Handles: #t=90s, #t=90
 */
function extractVimeoStartTime(url) {
    var hash = url.split('#')[1] || '';
    var match = hash.match(/t=(\d+)s?/);
    return match ? parseInt(match[1], 10) : 0;
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
    var playerContainer = modal.querySelector('.sitchco-video__modal-player');
    if (!playerContainer) {
        return; // Not a video modal
    }

    var modalId = modal.id;
    var entry = modalPlayers.get(modalId);
    // Resume existing player
    if (entry && entry.player) {
        if (entry.provider === 'youtube') {
            entry.player.playVideo();
        } else {
            entry.player.play();
        }
        return;
    }
    // Prevent double-creation during SDK load
    if (entry && entry.loading) {
        return;
    }

    // First open: load SDK and create player
    var provider = playerContainer.dataset.provider;
    var videoId = playerContainer.dataset.videoId;
    var url = playerContainer.dataset.url;
    var startTime = provider === 'youtube' ? extractYouTubeStartTime(url) : extractVimeoStartTime(url);

    modalPlayers.set(modalId, {
        player: null,
        provider: provider,
        loading: true,
    });

    if (provider === 'youtube') {
        createModalYouTubePlayer(playerContainer, videoId, startTime, modalId);
    } else if (provider === 'vimeo') {
        createModalVimeoPlayer(playerContainer, videoId, startTime, modalId);
    }
}

/**
 * Handle play interaction on a video block wrapper.
 * Locks dimensions (INLN-02), hides poster (INLN-03), creates player.
 */
function handlePlay(wrapper) {
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
    var playerContainer = document.createElement('div');
    playerContainer.className = 'sitchco-video__player';
    wrapper.appendChild(playerContainer);

    // Load SDK and create player based on provider
    var provider = wrapper.dataset.provider;
    var url = wrapper.dataset.url;
    var videoId = wrapper.dataset.videoId;
    if (provider === 'youtube') {
        createYouTubePlayer(playerContainer, videoId, extractYouTubeStartTime(url));
    } else if (provider === 'vimeo') {
        createVimeoPlayer(playerContainer, videoId, extractVimeoStartTime(url));
    }
}

/**
 * Initialize a single video block wrapper.
 * Attaches click and keyboard handlers for play interaction.
 * Handles both inline mode (play in-place) and modal mode (open dialog).
 */
function initVideoBlock(wrapper) {
    var displayMode = wrapper.dataset.displayMode;
    if (displayMode === 'modal') {
        // Modal mode: click poster -> open modal (not inline play)
        var modalId = wrapper.dataset.modalId;
        var modal = document.getElementById(modalId);
        if (!modal) {
            return;
        }

        var modalClickBehavior = wrapper.dataset.clickBehavior;
        var modalClickTarget =
            modalClickBehavior === 'icon' ? wrapper.querySelector('.sitchco-video__play-button') : wrapper;
        if (!modalClickTarget) {
            return;
        }

        // Click handler (NOT { once: true } -- modal can be opened multiple times)
        modalClickTarget.addEventListener('click', function (e) {
            e.preventDefault();
            sitchco.hooks.doAction('ui-modal-show', modal);
        });

        // Keyboard handler for wrapper with role="button"
        if (wrapper.getAttribute('role') === 'button') {
            wrapper.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    sitchco.hooks.doAction('ui-modal-show', modal);
                }
            });
        }
        return;
    }
    // Modal-only blocks have no on-page wrapper -- skip
    if (displayMode !== 'inline') {
        return;
    }

    var clickBehavior = wrapper.dataset.clickBehavior;
    var clickTarget;
    if (clickBehavior === 'icon') {
        clickTarget = wrapper.querySelector('.sitchco-video__play-button');

        if (!clickTarget) {
            return;
        }
    } else {
        // Default: poster click mode -- entire wrapper is the click target
        clickTarget = wrapper;
    }

    // Click handler (once: true ensures single activation)
    clickTarget.addEventListener(
        'click',
        function () {
            handlePlay(wrapper);
        },
        { once: true }
    );

    // Keyboard handler for wrapper with role="button" (Enter/Space)
    if (wrapper.getAttribute('role') === 'button') {
        wrapper.addEventListener(
            'keydown',
            function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    handlePlay(wrapper);
                }
            },
            { once: true }
        );
    }
}

// Register modal show hook at priority 20 (after UIModal core at 10).
// Fires for all modal opens: poster click, trigger link, and deep link via syncModalWithHash().
sitchco.hooks.addAction('ui-modal-show', handleModalShow, 20, 'video-block');

// Register with sitchco lifecycle (runs on DOMContentLoaded)
sitchco.register(function initVideoBlocks() {
    document.querySelectorAll('.sitchco-video[data-url]').forEach(initVideoBlock);

    // Pause video on modal close (all dismiss methods: Escape, backdrop, close button).
    // Uses native close event, NOT ui-modal-hide hook, because the hook does NOT fire
    // on Escape key close. The native close event fires for all close methods.
    document.querySelectorAll('dialog.sitchco-modal--video').forEach(function (modal) {
        modal.addEventListener('close', function () {
            var entry = modalPlayers.get(modal.id);
            if (!entry || !entry.player) {
                return;
            }
            if (entry.provider === 'youtube') {
                entry.player.pauseVideo();
            } else {
                entry.player.pause();
            }
        });
    });
});
