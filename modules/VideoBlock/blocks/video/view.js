/* global Vimeo */

/**
 * Click-to-play inline playback for sitchco/video block.
 *
 * Loads YouTube IFrame API or Vimeo Player SDK on first click only.
 * Uses sitchco.register() lifecycle for initialization and
 * sitchco.loadScript() for deduplicating SDK loads.
 *
 * Privacy: YouTube uses youtube-nocookie.com, Vimeo uses dnt:true.
 * No provider SDK, iframe, or CDN resource loads before user clicks play.
 */

/**
 * YouTube IFrame API singleton loader.
 * Wraps the global onYouTubeIframeAPIReady callback in a Promise.
 * Deduplicates via sitchco.loadScript() and module-level promise cache.
 */
var ytAPIPromise = null;

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
 */
function initVideoBlock(wrapper) {
    var displayMode = wrapper.dataset.displayMode;
    // Skip non-inline display modes (modal handled in Phase 3)
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

// Register with sitchco lifecycle (runs on DOMContentLoaded)
sitchco.register(function initVideoBlocks() {
    document.querySelectorAll('.sitchco-video[data-url]').forEach(initVideoBlock);
});
