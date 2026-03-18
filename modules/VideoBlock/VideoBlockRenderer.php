<?php

namespace Sitchco\Modules\VideoBlock;

use Sitchco\Modules\UIModal\ModalData;
use Sitchco\Modules\UIModal\UIModal;
use Sitchco\Utils\Cache;

/**
 * Handles all data preparation and HTML rendering for the sitchco/video block.
 */
readonly class VideoBlockRenderer
{
    public function __construct(private UIModal $uiModal) {}

    /**
     * Fetch oEmbed data with transient caching, returning a structured value object.
     *
     * Caches results for 30 days. Caches null for failures with a 1-hour TTL.
     */
    private static function fetchOembedData(VideoAttributes $attrs): ?VideoOembedData
    {
        $cache_key = 'sitchco_voembed_' . md5($attrs->url);
        $result = Cache::rememberTransient(
            $cache_key,
            function () use ($attrs) {
                return _wp_oembed_get_object()->get_data($attrs->url, []) ?: null;
            },
            30 * DAY_IN_SECONDS,
            HOUR_IN_SECONDS,
        );

        if (!$result) {
            return null;
        }

        return VideoOembedData::fromRaw($result, $attrs->provider);
    }

    public function render(array $attributes, string $content, object $block): string
    {
        if (empty($attributes['url'])) {
            return $content;
        }

        $attrs = new VideoAttributes($attributes);
        $has_inner_blocks = count($block->inner_blocks) > 0;

        // Fetch oEmbed when no InnerBlocks provide a poster
        $oembed = !$has_inner_blocks ? self::fetchOembedData($attrs) : null;

        // oEmbed failure (no InnerBlocks and no valid oEmbed): early return with fallback
        if (!$has_inner_blocks && $oembed === null) {
            if ($attrs->isModalOnly()) {
                return '';
            }

            $wrapper_attrs = $this->buildWrapperAttrs($attrs);
            $wrapper_attrs['data-video-unavailable'] = 'true';

            return sprintf(
                '<div %s><a class="sitchco-video__fallback-link" href="%s" target="_blank" rel="noopener noreferrer"><div class="sitchco-video__placeholder-poster"></div><span class="sitchco-video__fallback-label">%s</span></a></div>',
                get_block_wrapper_attributes($wrapper_attrs),
                esc_url($attrs->url),
                esc_html(sprintf('Watch on %s', $attrs->provider->label)),
            );
        }

        // Poster resolution
        if ($has_inner_blocks) {
            $poster_html = $content;
            $poster_style = '';
        } elseif ($oembed && $oembed->hasThumbnail) {
            $escaped_thumb = esc_url($oembed->thumbnailUrl);
            $escaped_title = esc_attr($oembed->title);
            $poster_html = <<<HTML
            <img class="sitchco-video__poster-img" src="{$escaped_thumb}" alt="{$escaped_title}" loading="lazy">
            HTML;
            $poster_style = $oembed->aspectRatioStyle;
        } else {
            $poster_html = '<div class="sitchco-video__placeholder-poster"></div>';
            $poster_style = '';
        }

        // Wrapper attributes
        $wrapper_attrs = $this->buildWrapperAttrs($attrs);

        // Modal side effects
        if ($attrs->isModal()) {
            // Always resolve oEmbed data for modal dialog content (even if InnerBlocks used for page poster)
            $modal_oembed = $oembed ?? self::fetchOembedData($attrs);
            $aspect_w = $modal_oembed ? $modal_oembed->aspectWidth : VideoOembedData::DEFAULT_ASPECT_WIDTH;
            $aspect_h = $modal_oembed ? $modal_oembed->aspectHeight : VideoOembedData::DEFAULT_ASPECT_HEIGHT;
            $has_oembed_poster = !$has_inner_blocks ? 'true' : 'false';

            // Build modal thumbnail image HTML
            $thumb_img = '';
            if ($modal_oembed && $modal_oembed->hasThumbnail) {
                $escaped_url = esc_url($modal_oembed->thumbnailUrl);
                $escaped_w = esc_attr($aspect_w);
                $escaped_h = esc_attr($aspect_h);
                $thumb_img = <<<HTML
                <img src="{$escaped_url}" alt="" class="sitchco-video__modal-poster-img" width="{$escaped_w}" height="{$escaped_h}">
                HTML;
            }

            // Build modal player content HTML
            $escaped_url_attr = esc_attr($attrs->url);
            $escaped_provider = esc_attr($attrs->provider);
            $escaped_video_id = esc_attr($attrs->videoId);
            $escaped_has_oembed = esc_attr($has_oembed_poster);
            $escaped_aspect_w = esc_attr($aspect_w);
            $escaped_aspect_h = esc_attr($aspect_h);
            $modal_content = <<<HTML
            <div class="sitchco-video__modal-player" data-url="{$escaped_url_attr}" data-provider="{$escaped_provider}" data-video-id="{$escaped_video_id}" data-has-oembed-poster="{$escaped_has_oembed}" style="--aspect-w: {$escaped_aspect_w}; --aspect-h: {$escaped_aspect_h}; aspect-ratio: {$escaped_aspect_w} / {$escaped_aspect_h}">{$thumb_img}<div class="sitchco-video__spinner"></div></div>
            HTML;

            $modalData = new ModalData($attrs->modalId, $attrs->videoTitle, $modal_content, 'video');
            $this->uiModal->loadModal($modalData);

            // Modal-only: render nothing on page
            if ($attrs->isModalOnly()) {
                return '';
            }

            // Modal mode: use normalized ID (ModalData may prefix digit-leading IDs with "modal-")
            $wrapper_attrs['data-modal-id'] = $modalData->id();
        }

        $play_button = self::buildPlayButton($attrs);

        // Accessibility attributes
        if ($attrs->clickBehavior === 'poster') {
            $wrapper_attrs['role'] = 'button';
            $wrapper_attrs['tabindex'] = '0';
            $wrapper_attrs['aria-label'] = sprintf('Play video: %s', $attrs->videoTitle);
        }

        return sprintf(
            '<div %s><div class="sitchco-video__poster"%s>%s</div>%s</div>',
            get_block_wrapper_attributes($wrapper_attrs),
            $poster_style,
            $poster_html,
            $play_button,
        );
    }

    private function buildWrapperAttrs(VideoAttributes $attrs): array
    {
        return [
            'class' => 'sitchco-video',
            'data-url' => $attrs->url,
            'data-provider' => $attrs->provider->name,
            'data-display-mode' => $attrs->displayMode,
            'data-play-icon-style' => $attrs->playIconStyle,
            'data-play-icon-x' => $attrs->playIconX,
            'data-play-icon-y' => $attrs->playIconY,
            'data-click-behavior' => $attrs->clickBehavior,
            'data-video-id' => $attrs->videoId,
            'data-video-title' => $attrs->videoTitle,
        ];
    }

    /**
     * Build the play button HTML with SVG icon.
     *
     * In poster click mode, the wrapper div is the interactive element (role="button"),
     * so the play icon is rendered as a presentational <span> to avoid nested interactive
     * elements (ARIA 1.2 violation). In icon click mode, the play icon is the sole
     * interactive element and renders as a <button>.
     */
    private static function buildPlayButton(VideoAttributes $attrs): string
    {
        $escaped_icon_width = esc_attr(VideoProvider::PLAY_ICON_WIDTH);
        $escaped_icon_height = esc_attr($attrs->provider->playIconHeight);
        $escaped_icon_name = esc_attr($attrs->provider->playIconName);
        $svg = <<<HTML
        <svg class="sitchco-video__play-icon-svg" aria-hidden="true" width="{$escaped_icon_width}" height="{$escaped_icon_height}" viewBox="0 0 {$escaped_icon_width} {$escaped_icon_height}"><use href="#icon-{$escaped_icon_name}"></use></svg>
        HTML;

        $svg = apply_filters(
            VideoBlock::hookName('play_icon_svg'),
            $svg,
            $attrs->provider->name,
            $attrs->playIconStyle,
        );

        $style = sprintf(
            'position:absolute;left:%s%%;top:%s%%;transform:translate(-50%%,-50%%)',
            esc_attr($attrs->playIconX),
            esc_attr($attrs->playIconY),
        );

        if ($attrs->clickBehavior === 'poster') {
            return sprintf(
                '<span class="sitchco-video__play-button sitchco-video__play-button--%s" aria-hidden="true" style="%s">%s</span>',
                esc_attr($attrs->playIconStyle),
                $style,
                $svg,
            );
        }

        return sprintf(
            '<button type="button" class="sitchco-video__play-button sitchco-video__play-button--%s" aria-label="%s" style="%s">%s</button>',
            esc_attr($attrs->playIconStyle),
            esc_attr(sprintf('Play video: %s', $attrs->videoTitle)),
            $style,
            $svg,
        );
    }
}
