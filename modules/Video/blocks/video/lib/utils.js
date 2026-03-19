/**
 * View-side utilities for the sitchco/video block.
 * Imported by view.js entry point tree (provider modules).
 *
 * Editor-only utilities (detectProvider, slugify) live in editor-utils.js
 * to prevent Vite from creating a shared chunk across entry points.
 */

/**
 * Parse a time string (e.g. "90", "90s", "1m30s", "1h2m30s") into seconds.
 * Used by provider-specific extractStartTime functions.
 *
 * @param {string} t - Time string.
 * @returns {number} Time in seconds.
 */
export function parseTimeString(t) {
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
