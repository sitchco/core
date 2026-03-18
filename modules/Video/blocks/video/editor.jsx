import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InnerBlocks, InspectorControls } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { Placeholder, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';
import { detectProvider } from './lib/editor-utils.js';
import useOembed from './hooks/use-oembed.js';
import VideoSettingsPanel from './components/VideoSettingsPanel.jsx';
import ModalSettingsPanel from './components/ModalSettingsPanel.jsx';
import PlayIconPanel from './components/PlayIconPanel.jsx';
import youtubePlaySvg from '../../assets/images/svg-sprite/icon-youtube-play.svg?raw';
import genericPlaySvg from '../../assets/images/svg-sprite/icon-generic-play.svg?raw';

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

    const {
        data: oembedData,
        isLoading,
        error,
    } = useOembed(url, provider, {
        videoTitle,
        modalId,
        setAttributes,
    });

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

    const oembedAspectRatio =
        oembedData?.width && oembedData?.height ? `${oembedData.width} / ${oembedData.height}` : '16 / 9';

    // Canvas style: aspect-ratio
    const canvasStyle = {
        aspectRatio: oembedAspectRatio,
        background: '#000',
    };

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
        if (!url || !isLoading || isModalOnly) {
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
        if (!url || !error || hasInnerBlocks || isModalOnly) {
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
        if (!url || isLoading || error || oembedData || isModalOnly) {
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
                <VideoSettingsPanel
                    url={url}
                    displayMode={displayMode}
                    onUrlChange={onUrlChange}
                    setAttributes={setAttributes}
                    error={error}
                    hasInnerBlocks={hasInnerBlocks}
                />
                {isModalMode && (
                    <ModalSettingsPanel videoTitle={videoTitle} modalId={modalId} setAttributes={setAttributes} />
                )}
                {url && !isModalOnly && (
                    <PlayIconPanel
                        provider={provider}
                        playIconStyle={playIconStyle}
                        playIconX={playIconX}
                        playIconY={playIconY}
                        clickBehavior={clickBehavior}
                        setAttributes={setAttributes}
                    />
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

            {url && !isModalOnly && !isLoading && (!error || hasInnerBlocks) && (
                <div
                    className={`sitchco-video__play-button sitchco-video__play-button--${playIconStyle}`}
                    aria-hidden="true"
                    style={{
                        position: 'absolute',
                        left: `${playIconX}%`,
                        top: `${playIconY}%`,
                        transform: 'translate(-50%, -50%)',
                        pointerEvents: 'none',
                    }}
                >
                    {getPlayIconSvg(provider)}
                </div>
            )}
        </div>
    );
}

function Save() {
    return <InnerBlocks.Content />;
}

registerBlockType(metadata.name, {
    edit: Edit,
    save: Save,
});
