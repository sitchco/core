import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InnerBlocks, InspectorControls } from '@wordpress/block-editor';
import { Placeholder, TextControl, SelectControl, PanelBody, Spinner } from '@wordpress/components';
import { useState, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import metadata from './block.json';

/**
 * Detect video provider from URL.
 */
function detectProvider(url) {
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
 */
function slugify(text) {
    return text
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');
}

/**
 * Generic play icon SVG component.
 * Temporary -- Plan 03 adds provider-specific branded icons.
 */
function PlayIcon() {
    return (
        <div className="sitchco-video__play-icon" aria-hidden="true">
            <svg width="68" height="68" viewBox="0 0 68 68" xmlns="http://www.w3.org/2000/svg">
                <circle cx="34" cy="34" r="34" fill="rgba(0, 0, 0, 0.6)" />
                <polygon points="26,18 52,34 26,50" fill="#ffffff" />
            </svg>
        </div>
    );
}

function Edit({ attributes, setAttributes }) {
    const blockProps = useBlockProps();
    const { url, displayMode, videoTitle, modalId, _videoTitleEdited, _modalIdEdited } = attributes;
    const isModalMode = displayMode === 'modal' || displayMode === 'modal-only';
    const isModalOnly = displayMode === 'modal-only';

    const [oembedData, setOembedData] = useState(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState(null);
    const abortControllerRef = useRef(null);

    // URL change handler: update url + auto-detect provider
    const onUrlChange = (newUrl) => {
        setAttributes({
            url: newUrl,
            provider: detectProvider(newUrl),
        });
    };

    // oEmbed fetch with 500ms debounce
    useEffect(() => {
        const provider = detectProvider(url);
        if (!url || !provider) {
            setOembedData(null);
            setIsLoading(false);
            setError(null);
            return;
        }

        setIsLoading(true);
        setError(null);

        const timeout = setTimeout(() => {
            // Abort any in-flight request
            if (abortControllerRef.current) {
                abortControllerRef.current.abort();
            }

            const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
            abortControllerRef.current = controller;

            apiFetch({
                path: addQueryArgs('/oembed/1.0/proxy', { url }),
                signal: controller ? controller.signal : undefined,
            })
                .then((response) => {
                    setOembedData(response);
                    setIsLoading(false);
                    setError(null);

                    // Auto-populate videoTitle if not manually edited
                    if (response.title && !_videoTitleEdited) {
                        setAttributes({ videoTitle: response.title });
                    }
                    // Auto-populate modalId if not manually edited
                    if (response.title && !_modalIdEdited) {
                        setAttributes({ modalId: slugify(response.title) });
                    }
                })
                .catch((err) => {
                    if (err.name === 'AbortError') {
                        return;
                    }

                    setError(err.message || __('Failed to fetch video data.', 'sitchco'));
                    setOembedData(null);
                    setIsLoading(false);
                });
        }, 500);
        return () => {
            clearTimeout(timeout);

            if (abortControllerRef.current) {
                abortControllerRef.current.abort();
            }
        };
    }, [url]);
    return (
        <div {...blockProps}>
            <InspectorControls>
                <PanelBody title={__('Video Settings', 'sitchco')} initialOpen={true}>
                    <TextControl
                        label={__('Video URL', 'sitchco')}
                        value={url}
                        onChange={onUrlChange}
                        placeholder="https://youtube.com/watch?v=..."
                        __nextHasNoMarginBottom
                    />
                    <SelectControl
                        label={__('Display Mode', 'sitchco')}
                        value={displayMode}
                        options={[
                            {
                                label: __('Inline', 'sitchco'),
                                value: 'inline',
                            },
                            {
                                label: __('Modal', 'sitchco'),
                                value: 'modal',
                            },
                            {
                                label: __('Modal Only', 'sitchco'),
                                value: 'modal-only',
                            },
                        ]}
                        onChange={(value) => setAttributes({ displayMode: value })}
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
                {isModalMode && (
                    <PanelBody title={__('Modal Settings', 'sitchco')} initialOpen={true}>
                        <TextControl
                            label={__('Video Title', 'sitchco')}
                            value={videoTitle}
                            onChange={(value) =>
                                setAttributes({
                                    videoTitle: value,
                                    _videoTitleEdited: true,
                                })
                            }
                            help={__(
                                'Used for accessibility and modal heading. Auto-populated from video metadata.',
                                'sitchco'
                            )}
                            __nextHasNoMarginBottom
                        />
                        <TextControl
                            label={__('Modal ID', 'sitchco')}
                            value={modalId}
                            onChange={(value) =>
                                setAttributes({
                                    modalId: slugify(value),
                                    _modalIdEdited: true,
                                })
                            }
                            help={__('Unique identifier for deep linking. Auto-generated from title.', 'sitchco')}
                            __nextHasNoMarginBottom
                        />
                    </PanelBody>
                )}
            </InspectorControls>

            {!url && (
                <Placeholder
                    icon="video-alt3"
                    label={__('Video', 'sitchco')}
                    instructions={__('Enter a video URL in the block settings.', 'sitchco')}
                />
            )}

            {url && isLoading && (
                <div className="sitchco-video__loading">
                    <Spinner />
                </div>
            )}

            {url && error && (
                <div className="sitchco-video__error">
                    <p>{error}</p>
                </div>
            )}

            {url && oembedData && oembedData.thumbnail_url && (
                <div className={'sitchco-video__preview' + (isModalOnly ? ' sitchco-video__preview--modal-only' : '')}>
                    <img
                        className="sitchco-video__thumbnail"
                        src={oembedData.thumbnail_url}
                        alt={oembedData.title || ''}
                    />
                    <PlayIcon />
                    {isModalOnly && <span className="sitchco-video__badge">{__('Modal Only', 'sitchco')}</span>}
                </div>
            )}

            {url && !isLoading && !error && !oembedData && (
                <div className="sitchco-video__placeholder">
                    <p>{__('Enter a YouTube or Vimeo URL to see a preview.', 'sitchco')}</p>
                </div>
            )}

            {isModalOnly ? (
                <p className="sitchco-video__modal-only-notice">
                    {__('Modal Only mode -- block content is not visible on the page.', 'sitchco')}
                </p>
            ) : (
                <InnerBlocks />
            )}
        </div>
    );
}

function Save() {
    const blockProps = useBlockProps.save();
    return (
        <div {...blockProps}>
            <InnerBlocks.Content />
        </div>
    );
}

registerBlockType(metadata.name, {
    edit: Edit,
    save: Save,
});
