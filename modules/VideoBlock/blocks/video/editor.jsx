import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InnerBlocks, InspectorControls } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { Placeholder, TextControl, SelectControl, RangeControl, PanelBody, Spinner } from '@wordpress/components';
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
 * Upgrade oEmbed thumbnail URL to high-resolution variant.
 *
 * Mirrors VideoBlockRenderer::upgradeThumbnailUrl() in PHP.
 */
function upgradeThumbnailUrl(url, provider) {
    if (provider === 'youtube') {
        return url.replace(/\/hqdefault\.jpg$/, '/maxresdefault.jpg');
    }
    if (provider === 'vimeo') {
        return url.replace(/_\d+x\d+/, '_1280x720');
    }
    return url;
}

/**
 * Return play icon SVG JSX based on provider.
 *
 * Colors are controlled via CSS custom properties (--sitchco-play-bg, --sitchco-play-fg)
 * set on the parent element via style modifier classes.
 */
function getPlayIconSvg(provider) {
    if (provider === 'youtube') {
        return (
            <svg
                width="68"
                height="48"
                viewBox="0 0 68 48"
                xmlns="http://www.w3.org/2000/svg"
                className="sitchco-video__play-icon-svg"
            >
                <rect width="68" height="48" rx="12" fill="var(--sitchco-play-bg, rgba(0, 0, 0, 0.8))" />
                <polygon points="27,12 27,36 50,24" fill="var(--sitchco-play-fg, #fff)" />
            </svg>
        );
    }
    return (
        <svg
            width="68"
            height="68"
            viewBox="0 0 68 68"
            xmlns="http://www.w3.org/2000/svg"
            className="sitchco-video__play-icon-svg"
        >
            <circle cx="34" cy="34" r="34" fill="var(--sitchco-play-bg, rgba(0, 0, 0, 0.8))" />
            <polygon points="26,18 52,34 26,50" fill="var(--sitchco-play-fg, #fff)" />
        </svg>
    );
}

function Edit({ attributes, setAttributes, clientId }) {
    const { provider } = attributes;
    const blockProps = useBlockProps(provider ? { 'data-provider': provider } : {});
    const { url, displayMode, videoTitle, modalId, playIconStyle, playIconX, playIconY, clickBehavior } = attributes;
    const isModalMode = displayMode === 'modal' || displayMode === 'modal-only';
    const isModalOnly = displayMode === 'modal-only';
    const hasInnerBlocks = useSelect((select) => select('core/block-editor').getBlockCount(clientId) > 0, [clientId]);

    const [oembedData, setOembedData] = useState(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState(null);
    const abortControllerRef = useRef(null);
    const prevOembedTitleRef = useRef(null);

    // Play icon style options are provider-conditional
    const playIconStyleOptions =
        provider === 'youtube'
            ? [
                  {
                      label: __('Dark', 'sitchco'),
                      value: 'dark',
                  },
                  {
                      label: __('Light', 'sitchco'),
                      value: 'light',
                  },
                  {
                      label: __('Red', 'sitchco'),
                      value: 'red',
                  },
              ]
            : [
                  {
                      label: __('Dark', 'sitchco'),
                      value: 'dark',
                  },
                  {
                      label: __('Light', 'sitchco'),
                      value: 'light',
                  },
              ];

    // URL change handler: update url + auto-detect provider
    const onUrlChange = (newUrl) => {
        const newProvider = detectProvider(newUrl);
        const updates = {
            url: newUrl,
            provider: newProvider,
        };
        // Auto-reset "red" style when switching away from YouTube
        if (newProvider !== 'youtube' && playIconStyle === 'red') {
            updates.playIconStyle = 'dark';
        }

        setAttributes(updates);
    };

    // oEmbed fetch with 500ms debounce
    useEffect(() => {
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

                    if (response.title) {
                        const updates = {};
                        const prevTitle = prevOembedTitleRef.current;
                        // Auto-populate only if current value is empty or matches what
                        // oEmbed would have auto-generated (user hasn't manually edited)
                        if (!videoTitle || videoTitle === prevTitle) {
                            updates.videoTitle = response.title;
                        }
                        if (!modalId || modalId === slugify(prevTitle || '')) {
                            updates.modalId = slugify(response.title);
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

    const renderPlaceholder = () => {
        if (url) {
            return null;
        }
        return (
            <Placeholder
                icon="video-alt3"
                label={__('Video', 'sitchco')}
                instructions={__('Enter a video URL in the block settings.', 'sitchco')}
            />
        );
    };

    const renderLoading = () => {
        if (!url || !isLoading) {
            return null;
        }
        return (
            <div className="sitchco-video__loading">
                <Spinner />
            </div>
        );
    };

    const renderError = () => {
        if (!url || !error) {
            return null;
        }
        return (
            <div className="sitchco-video__error">
                <p>{error}</p>
            </div>
        );
    };

    const renderPreview = () => {
        if (!url || isModalOnly || hasInnerBlocks || !oembedData?.thumbnail_url) {
            return null;
        }
        return (
            <div
                className="sitchco-video__preview"
                style={
                    oembedData.width && oembedData.height
                        ? { aspectRatio: `${oembedData.width} / ${oembedData.height}` }
                        : undefined
                }
            >
                <img
                    className="sitchco-video__thumbnail"
                    src={upgradeThumbnailUrl(oembedData.thumbnail_url, provider)}
                    alt={oembedData.title || ''}
                />
            </div>
        );
    };

    const renderEmptyState = () => {
        if (!url || isLoading || error || oembedData) {
            return null;
        }
        return (
            <div className="sitchco-video__placeholder">
                <p>{__('Enter a YouTube or Vimeo URL to see a preview.', 'sitchco')}</p>
            </div>
        );
    };
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
                            onChange={(value) => setAttributes({ videoTitle: value })}
                            help={__(
                                'Used for accessibility and modal heading. Auto-populated from video metadata.',
                                'sitchco'
                            )}
                            __nextHasNoMarginBottom
                        />
                        <TextControl
                            label={__('Modal ID', 'sitchco')}
                            value={modalId}
                            onChange={(value) => setAttributes({ modalId: slugify(value) })}
                            help={__('Unique identifier for deep linking. Auto-generated from title.', 'sitchco')}
                            __nextHasNoMarginBottom
                        />
                    </PanelBody>
                )}
                {url && !isModalOnly && (
                    <PanelBody title={__('Play Icon', 'sitchco')} initialOpen={true}>
                        <SelectControl
                            label={__('Icon Style', 'sitchco')}
                            value={playIconStyle}
                            options={playIconStyleOptions}
                            onChange={(value) => setAttributes({ playIconStyle: value })}
                            __nextHasNoMarginBottom
                        />
                        <RangeControl
                            label={__('Horizontal Position', 'sitchco')}
                            value={playIconX}
                            onChange={(value) => setAttributes({ playIconX: value })}
                            min={0}
                            max={100}
                            step={1}
                            help={__('Position as percentage (50% = centered)', 'sitchco')}
                            __nextHasNoMarginBottom
                        />
                        <RangeControl
                            label={__('Vertical Position', 'sitchco')}
                            value={playIconY}
                            onChange={(value) => setAttributes({ playIconY: value })}
                            min={0}
                            max={100}
                            step={1}
                            help={__('Position as percentage (50% = centered)', 'sitchco')}
                            __nextHasNoMarginBottom
                        />
                        <SelectControl
                            label={__('Click Behavior', 'sitchco')}
                            value={clickBehavior}
                            options={[
                                {
                                    label: __('Entire poster', 'sitchco'),
                                    value: 'poster',
                                },
                                {
                                    label: __('Play icon only', 'sitchco'),
                                    value: 'icon',
                                },
                            ]}
                            onChange={(value) => setAttributes({ clickBehavior: value })}
                            help={__(
                                'Controls whether clicking anywhere on the poster or only the play icon starts the video',
                                'sitchco'
                            )}
                            __nextHasNoMarginBottom
                        />
                    </PanelBody>
                )}
            </InspectorControls>

            {renderPlaceholder()}
            {renderLoading()}
            {renderError()}
            {renderPreview()}
            {renderEmptyState()}

            {isModalOnly ? (
                <Placeholder icon="video-alt3" label={__('Modal Only', 'sitchco')}>
                    {url && (
                        <>
                            <p>
                                <strong>{__('Modal ID:', 'sitchco')}</strong>{' '}
                                {modalId || __('(auto-generated from title)', 'sitchco')}
                            </p>
                            <p>
                                <strong>{__('URL:', 'sitchco')}</strong> {url}
                            </p>
                        </>
                    )}
                </Placeholder>
            ) : (
                <InnerBlocks />
            )}

            {url && !isModalOnly && (
                <div
                    className={`sitchco-video__play-icon sitchco-video__play-button--${playIconStyle}`}
                    aria-hidden="true"
                    style={{
                        position: 'absolute',
                        left: `${playIconX}%`,
                        top: `${playIconY}%`,
                        transform: 'translate(-50%, -50%)',
                    }}
                >
                    {getPlayIconSvg(provider)}
                </div>
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
