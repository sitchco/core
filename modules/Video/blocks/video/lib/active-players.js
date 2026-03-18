/**
 * Active player registry for mutual exclusion.
 *
 * Only one video plays at a time (MXCL-01). When a new player starts,
 * all other active players are paused first (MXCL-02).
 *
 * Also provides instance ID generation and external pause-by-videoId support.
 */

/**
 * Active player registry.
 * Maps instanceId -> { adapter: PlayerAdapter, provider: string, url: string, videoId: string }
 */
export const activePlayers = new Map();

/**
 * Auto-incrementing counter for unique player instance IDs.
 * Used as the key in activePlayers so that multiple player instances
 * of the same provider video ID don't collide.
 */
let instanceCounter = 0;

/**
 * Generate the next unique player instance ID.
 *
 * @returns {string} Instance ID (e.g. 'v1', 'v2').
 */
export function nextInstanceId() {
    return 'v' + ++instanceCounter;
}

/**
 * Pause all player instances matching a provider video ID.
 * Called by the video-request-pause hook for external coordination.
 *
 * @param {string} videoId - Provider video ID.
 */
export function pausePlayerById(videoId) {
    activePlayers.forEach(function (entry) {
        if (entry.videoId === videoId) {
            entry.adapter.pause();
        }
    });
}

/**
 * Register a player as active, pausing all other active players first.
 * Implements mutual exclusion (MXCL-01, MXCL-02): only one video plays at a time.
 *
 * @param {string} instanceId - Unique player instance ID.
 * @param {string} videoId - Provider video ID.
 * @param {Object} adapter - Normalized player adapter.
 * @param {string} provider - Player provider name.
 * @param {string} url - Original video URL.
 */
export function registerActivePlayer(instanceId, videoId, adapter, provider, url) {
    activePlayers.forEach(function (entry, id) {
        if (id !== instanceId) {
            entry.adapter.pause();
        }
    });

    activePlayers.set(instanceId, {
        adapter: adapter,
        provider: provider,
        url: url,
        videoId: videoId,
    });
}
