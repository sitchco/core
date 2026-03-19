/**
 * Editor-only utilities for the sitchco/video block.
 * Imported by editor.jsx entry point tree (Edit component, hooks, panels).
 *
 * Separated from view-side utils because Vite's IIFE output wrapping
 * causes shared imports to be chunked rather than inlined, breaking
 * at runtime.
 */

/**
 * Detect video provider from URL.
 *
 * @param {string} url - Video URL.
 * @returns {''|'youtube'|'vimeo'} Provider name or empty string.
 */
export function detectProvider(url) {
    if (!url) {
        return '';
    }
    if (/(?:youtube\.com|youtu\.be)\//i.test(url)) {
        return 'youtube';
    }
    if (/vimeo\.com\//i.test(url)) {
        return 'vimeo';
    }
    return '';
}

/**
 * Slugify text for modal ID generation.
 * Falls back to the optional `fallback` string if the result is empty
 * (e.g. non-Latin titles that produce no ASCII characters).
 *
 * @param {string} text - Text to slugify.
 * @param {string} [fallback] - Fallback if result is empty.
 * @returns {string} Slugified string.
 */
export function slugify(text, fallback) {
    const result = text
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');
    return result || fallback || '';
}
