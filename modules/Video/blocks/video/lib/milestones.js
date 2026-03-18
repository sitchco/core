/**
 * Milestone progress tracking for video playback.
 *
 * Polls playing videos at 1-second intervals and fires video-progress hooks
 * when 25%, 50%, or 75% thresholds are crossed. 100% is handled by the
 * ended event in the orchestrator, not by polling.
 *
 * Milestones fire at most once per video per page load (keyed by videoId).
 */

/**
 * Milestone polling interval storage.
 * Maps instanceId -> intervalId
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
 * Check milestone percentages and fire video-progress hooks for newly crossed thresholds.
 * Handles seeking past multiple milestones correctly -- all crossed milestones fire.
 *
 * @param {string} videoId - Provider video ID.
 * @param {string} provider - Player provider name.
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
 * Polls every 1 second. No-ops if polling is already active for this instanceId.
 * Uses the normalized adapter so polling is provider-agnostic.
 *
 * @param {string} instanceId - Unique player instance ID.
 * @param {string} videoId - Provider video ID.
 * @param {Object} adapter - Normalized player adapter.
 * @param {string} provider - Player provider name.
 * @param {string} url - Original video URL.
 */
export function startMilestonePolling(instanceId, videoId, adapter, provider, url) {
    if (pollIntervals.has(instanceId)) {
        return;
    }
    if (!milestonesFired.has(videoId)) {
        milestonesFired.set(videoId, new Set());
    }

    const intervalId = setInterval(function () {
        Promise.all([adapter.getCurrentTime(), adapter.getDuration()])
            .then(function ([current, duration]) {
                checkMilestones(videoId, provider, url, current, duration);
            })
            .catch(function () {
                // Player may be destroyed mid-poll -- ignore silently
            });
    }, 1000);

    pollIntervals.set(instanceId, intervalId);
}

/**
 * Stop milestone polling for a video.
 * Does NOT clear milestonesFired -- milestones fire at most once per page load.
 *
 * @param {string} instanceId - Unique player instance ID.
 */
export function stopMilestonePolling(instanceId) {
    const intervalId = pollIntervals.get(instanceId);
    if (intervalId !== undefined) {
        clearInterval(intervalId);
        pollIntervals.delete(instanceId);
    }
}
