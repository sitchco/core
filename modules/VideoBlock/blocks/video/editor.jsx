import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InnerBlocks, InspectorControls } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { Placeholder, TextControl, SelectControl, RangeControl, PanelBody, Spinner } from '@wordpress/components';
import { useState, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import metadata from './block.json';
import youtubePlaySvg from '../../assets/images/svg-sprite/icon-youtube-play.svg?raw';
import genericPlaySvg from '../../assets/images/svg-sprite/icon-generic-play.svg?raw';

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
 * Falls back to the optional `fallback` string if the result is empty
 * (e.g. non-Latin titles that produce no ASCII characters).
 */
function slugify(text, fallback) {
    const result = text
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');
    return result || fallback || '';
}

/**
 * Upgrade oEmbed thumbnail URL to high-resolution variant.
 *
 * Mirrors VideoBlockRenderer::upgradeThumbnailUrl() in PHP.
 */
function upgradeThumbnailUrl(url, provider, width, height) {
    if (provider === 'youtube') {
        return url.replace(/\/hqdefault\.jpg$/, '/maxresdefault.jpg');
    }
    if (provider === 'vimeo') {
        const isPortrait = width && height && height > width;
        const dims = isPortrait ? '720x1280' : '1280x720';
        return url.replace(/_\d+x\d+/, '_' + dims);
    }
    return url;
}

/**
 * Play icon SVG markup, sourced from sprite SVG files via Vite ?raw import.
 * The canonical shapes live in assets/images/svg-sprite/icon-*.svg.
 * PHP frontend uses these same files via the compiled SVG sprite.
 */
function getPlayIconSvg(provider) {
    const svg = provider === 'youtube' ? youtubePlaySvg : genericPlaySvg;
    return <span dangerouslySetInnerHTML={{ __html: svg }} />;
}

function Edit({ attributes, setAttributes, clientId }) {
    const { provider } = attributes;
    const blockProps = useBlockProps({
        className: 'sitchco-video',
        ...(provider ? { 'data-provider': provider } : {}),
    });
    const { url, displayMode, videoTitle, modalId, playIconStyle, playIconX, playIconY, clickBehavior } = attributes;
    const isModalMode = displayMode === 'modal' || displayMode === 'modal-only';
    const isModalOnly = displayMode === 'modal-only';
    const hasInnerBlocks = useSelect((select) => select('core/block-editor').getBlockCount(clientId) > 0, [clientId]);

    const [oembedData, setOembedData] = useState(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState(null);
    const abortControllerRef = useRef(null);
    const prevOembedTitleRef = useRef(null);
    const videoTitleRef = useRef(videoTitle);
    const modalIdRef = useRef(modalId);
    videoTitleRef.current = videoTitle;
    modalIdRef.current = modalId;

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

        // Clear stale preview data immediately on URL change (before debounce resolves)
        setOembedData(null);
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

                        setOembedData(null);
                        setIsLoading(false);
                        return;
                    }

                    setOembedData(response);
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

    const oembedAspectRatio =
        oembedData?.width && oembedData?.height ? `${oembedData.width} / ${oembedData.height}` : '16 / 9';

    // Canvas style: aspect-ratio
    const canvasStyle = {
        aspectRatio: oembedAspectRatio,
        background: '#000',
    };

    const renderLoading = () => {
        if (!url || !isLoading) {
            return null;
        }
        return (
            <div className="sitchco-video__canvas" style={canvasStyle}>
                <div className="sitchco-video__loading">
                    <Spinner />
                </div>
            </div>
        );
    };

    const renderError = () => {
        if (!url || !error) {
            return null;
        }
        return (
            <div className="sitchco-video__canvas" style={canvasStyle}>
                <div className="sitchco-video__error sitchco-video__error--canvas">
                    <p>{error}</p>
                </div>
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
                style={oembedData.width && oembedData.height ? { aspectRatio: oembedAspectRatio } : undefined}
            >
                <img
                    className="sitchco-video__thumbnail"
                    src={upgradeThumbnailUrl(oembedData.thumbnail_url, provider, oembedData.width, oembedData.height)}
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
            ) : url && !hasInnerBlocks && !oembedData?.thumbnail_url && !isLoading && !error ? (
                <div className="sitchco-video__canvas" style={canvasStyle}>
                    <InnerBlocks />
                </div>
            ) : (
                <InnerBlocks />
            )}

            {url && !isModalOnly && !error && (
                <div
                    className={`sitchco-video__play-button sitchco-video__play-button--${playIconStyle}`}
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
