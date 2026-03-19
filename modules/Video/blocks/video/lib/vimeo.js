/* global Vimeo */

/**
 * Vimeo provider module for sitchco/video block.
 *
 * Exports a uniform provider interface: loadSDK(), createPlayer(), extractStartTime().
 * Uses dnt:true for privacy (PRIV-03).
 */
import { parseTimeString } from './utils.js';

/**
 * Load the Vimeo Player SDK from CDN.
 * Uses sitchco.loadScript() for deduplication.
 *
 * @returns {Promise<void>}
 */
export function loadSDK() {
    return sitchco.loadScript('vimeo-player', 'https://player.vimeo.com/api/player.js');
}

/**
 * Extract start time (in seconds) from a Vimeo URL.
 * Handles: #t=90s, #t=90, #t=1m30s, #t=1h2m30s
 *
 * @param {string} url - Vimeo video URL.
 * @returns {number} Start time in seconds.
 */
export function extractStartTime(url) {
    const hash = url.split('#')[1] || '';
    const tMatch = hash.match(/t=([\dhms]+)/);
    if (!tMatch) {
        return 0;
    }
    return parseTimeString(tMatch[1]);
}

/**
 * Create a Vimeo player inside the given target element.
 *
 * Autoplay on creation (INLN-05). Start time from URL (INLN-06).
 * Applies sitchco/video/playerVars/vimeo filter before player creation (EXTN-05).
 *
 * @param {Element} target - DOM element to create the player in.
 * @param {string} videoId - Vimeo video ID.
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
        .then(function () {
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

            const adapter = {
                play: function () {
                    player.play();
                },
                pause: function () {
                    player.pause();
                },
                getCurrentTime: function () {
                    return player.getCurrentTime();
                },
                getDuration: function () {
                    return player.getDuration();
                },
                raw: player,
            };

            player
                .ready()
                .then(function () {
                    if (startTime > 0) {
                        player.setCurrentTime(startTime);
                    }

                    onReady(adapter);
                })
                .catch(function () {
                    onError();
                });

            player.on('play', function () {
                onPlay(adapter);
            });

            player.on('pause', function () {
                onPause(adapter);
            });

            player.on('error', function () {
                onError();
            });

            player.on('ended', function () {
                onEnded(adapter);
            });
        })
        .catch(function (err) {
            console.error('sitchco-video: Vimeo SDK load failed', err);
            onError();
        });
}
