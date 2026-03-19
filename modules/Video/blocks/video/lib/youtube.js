/**
 * YouTube provider module for sitchco/video block.
 *
 * Exports a uniform provider interface: loadSDK(), createPlayer(), extractStartTime().
 * Uses youtube-nocookie.com for privacy (PRIV-02).
 */
import { parseTimeString } from './utils.js';

/**
 * YouTube IFrame API singleton loader.
 * Wraps the global onYouTubeIframeAPIReady callback in a Promise.
 * Deduplicates via sitchco.loadScript() and module-level promise cache.
 */
let ytAPIPromise = null;

export function loadSDK() {
    if (ytAPIPromise) {
        return ytAPIPromise;
    }
    if (window.YT && window.YT.Player) {
        ytAPIPromise = Promise.resolve(window.YT);
        return ytAPIPromise;
    }

    ytAPIPromise = new Promise(function (resolve, reject) {
        const prev = window.onYouTubeIframeAPIReady;

        window.onYouTubeIframeAPIReady = function () {
            if (prev) {
                prev();
            }

            resolve(window.YT);
        };

        sitchco.loadScript('youtube-iframe-api', 'https://www.youtube.com/iframe_api').catch(function (err) {
            ytAPIPromise = null;
            reject(err);
        });
    });
    return ytAPIPromise;
}

/**
 * Extract start time (in seconds) from a YouTube URL.
 * Handles: ?t=90, ?t=90s, ?t=1m30s, ?t=1h2m30s, ?start=90, &t=90
 *
 * @param {string} url - YouTube video URL.
 * @returns {number} Start time in seconds.
 */
export function extractStartTime(url) {
    try {
        const urlObj = new URL(url);
        const t = urlObj.searchParams.get('t') || urlObj.searchParams.get('start');
        if (!t) {
            return 0;
        }
        return parseTimeString(String(t));
    } catch {
        return 0;
    }
}

/**
 * Create a YouTube player inside the given target element.
 *
 * Autoplay on ready (INLN-05). Start time from URL (INLN-06).
 * Applies sitchco/video/playerVars/youtube filter before player creation (EXTN-04).
 *
 * @param {Element} target - DOM element to create the player in.
 * @param {string} videoId - YouTube video ID.
 * @param {Object} opts - Player options.
 * @param {number} opts.startTime - Start time in seconds.
 * @param {string} opts.url - Original video URL.
 * @param {string} opts.displayMode - Display mode ('inline', 'modal', 'modal-only').
 * @param {Function} opts.onReady - Called with normalized player adapter when ready.
 * @param {Function} opts.onPlay - Called with adapter when playback starts.
 * @param {Function} opts.onPause - Called with adapter when playback pauses.
 * @param {Function} opts.onEnded - Called with adapter when playback ends.
 * @param {Function} opts.onError - Called when an error occurs.
 */
export function createPlayer(
    target,
    videoId,
    { startTime, url, displayMode, onReady, onPlay, onPause, onEnded, onError }
) {
    loadSDK()
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

            let adapter = null;

            new YT.Player(target, {
                videoId: videoId,
                host: 'https://www.youtube-nocookie.com',
                playerVars: playerVars,
                events: {
                    onReady: function (event) {
                        adapter = {
                            play: function () {
                                event.target.playVideo();
                            },
                            pause: function () {
                                event.target.pauseVideo();
                            },
                            getCurrentTime: function () {
                                return Promise.resolve(event.target.getCurrentTime());
                            },
                            getDuration: function () {
                                return Promise.resolve(event.target.getDuration());
                            },
                            raw: event.target,
                        };

                        onReady(adapter);
                    },
                    onError: function () {
                        onError();
                    },
                    onStateChange: function (event) {
                        if (!adapter) {
                            return;
                        }
                        if (event.data === YT.PlayerState.PLAYING) {
                            onPlay(adapter);
                        } else if (event.data === YT.PlayerState.PAUSED) {
                            onPause(adapter);
                        } else if (event.data === YT.PlayerState.ENDED) {
                            onEnded(adapter);
                        }
                    },
                },
            });
        })
        .catch(function (err) {
            console.error('sitchco-video: YouTube SDK load failed', err);
            onError();
        });
}
