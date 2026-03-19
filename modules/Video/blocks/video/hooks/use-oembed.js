/**
 * Custom hook for fetching oEmbed data with debounce, abort, and auto-populate logic.
 */
import { useState, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { slugify } from '../lib/editor-utils.js';

/**
 * Fetch oEmbed data for a video URL with 500ms debounce.
 * Auto-populates videoTitle and modalId from oEmbed response title,
 * guarding against overwriting manual user edits.
 *
 * @param {string} url - Video URL.
 * @param {string} provider - Provider name ('youtube' or 'vimeo').
 * @param {Object} opts
 * @param {string} opts.videoTitle - Current videoTitle attribute.
 * @param {string} opts.modalId - Current modalId attribute.
 * @param {Function} opts.setAttributes - Block setAttributes function.
 * @returns {{ data: Object|null, isLoading: boolean, error: string|null }}
 */
export default function useOembed(url, provider, { videoTitle, modalId, setAttributes }) {
    const [data, setData] = useState(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState(null);
    const abortControllerRef = useRef(null);
    const prevOembedTitleRef = useRef(null);
    const videoTitleRef = useRef(videoTitle);
    const modalIdRef = useRef(modalId);
    videoTitleRef.current = videoTitle;
    modalIdRef.current = modalId;

    // oEmbed fetch with 500ms debounce
    useEffect(() => {
        if (!url || !provider) {
            setData(null);
            setIsLoading(false);
            setError(null);
            return;
        }

        // Clear stale preview data immediately on URL change (before debounce resolves)
        setData(null);
        setError(null);

        const timeout = setTimeout(() => {
            // Abort any in-flight request
            if (abortControllerRef.current) {
                abortControllerRef.current.abort();
            }

            const controller = new AbortController();
            abortControllerRef.current = controller;

            setIsLoading(true);

            apiFetch({
                path: addQueryArgs('/oembed/1.0/proxy', { url }),
                signal: controller.signal,
            })
                .then((response) => {
                    // Detect domain-level embedding restrictions (Vimeo returns domain_status_code: 403)
                    if (response.domain_status_code >= 400) {
                        setError(
                            __(
                                'This video cannot be embedded on this domain. Update its embed settings to include this site.',
                                'sitchco'
                            )
                        );

                        setData(null);
                        setIsLoading(false);
                        return;
                    }

                    setData(response);
                    setIsLoading(false);
                    setError(null);

                    if (response.title) {
                        const updates = {};
                        const prevTitle = prevOembedTitleRef.current;
                        // Extract a video ID from the URL for non-Latin title fallback
                        const videoIdMatch = url.match(
                            /(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|shorts\/|watch\?v=|watch\?.+&v=))([\w-]{11})|vimeo\.com\/(?:video\/)?(\d+)/
                        );
                        const videoIdFallback = videoIdMatch ? videoIdMatch[1] || videoIdMatch[2] : '';
                        // Auto-populate only if current value is empty or matches what
                        // oEmbed would have auto-generated (user hasn't manually edited).
                        // Use refs to avoid stale closure over videoTitle/modalId.
                        if (!videoTitleRef.current || videoTitleRef.current === prevTitle) {
                            updates.videoTitle = response.title;
                        }
                        if (!modalIdRef.current || modalIdRef.current === slugify(prevTitle || '')) {
                            updates.modalId = slugify(response.title, videoIdFallback);
                        }
                        if (Object.keys(updates).length > 0) {
                            setAttributes(updates);
                        }

                        prevOembedTitleRef.current = response.title;
                    }
                })
                .catch((err) => {
                    if (err.name === 'AbortError') {
                        return;
                    }

                    setError(
                        __(
                            'Video preview unavailable. Check that the URL is valid and the video allows embedding.',
                            'sitchco'
                        )
                    );

                    setData(null);
                    setIsLoading(false);
                });
        }, 500);
        return () => {
            clearTimeout(timeout);

            if (abortControllerRef.current) {
                abortControllerRef.current.abort();
            }
        };
    }, [url, provider]);
    return {
        data,
        isLoading,
        error,
    };
}
