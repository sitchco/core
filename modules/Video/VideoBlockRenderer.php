<?php

namespace Sitchco\Modules\Video;

use Sitchco\Modules\UIModal\ModalData;
use Sitchco\Modules\UIModal\UIModal;
use Sitchco\Utils\Cache;
use Sitchco\Utils\Str;

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

    public function render(array $attributes, string $content, \WP_Block $block): string
    {
        if (empty($attributes['url'])) {
            return $content;
        }

        $attrs = new VideoAttributes($attributes, $block);

        // Fetch oEmbed when no InnerBlocks provide a poster
        $oembed = !$attrs->hasInnerBlocks ? self::fetchOembedData($attrs) : null;

        // oEmbed failure (no InnerBlocks and no valid oEmbed): early return with fallback
        if (!$attrs->hasInnerBlocks && $oembed === null) {
            return $attrs->isModalOnly() ? '' : $this->buildFallback($attrs);
        }

        // Wrapper attributes
        $wrapper_attrs = $this->buildWrapperAttrs($attrs);

        // Modal side effects
        if ($attrs->isModal()) {
            $modalData = $this->buildModal($attrs, $oembed);
            $this->uiModal->loadModal($modalData);
            if ($attrs->isModalOnly()) {
                return '';
            }
            $wrapper_attrs['data-modal-id'] = $modalData->id();
        }

        // Accessibility attributes
        if ($attrs->clickBehavior === 'poster') {
            $wrapper_attrs['role'] = 'button';
            $wrapper_attrs['tabindex'] = '0';
            $wrapper_attrs['aria-label'] = $attrs->playAriaLabel();
        }

        return Str::wrapElement(
            self::buildPoster($attrs, $content, $oembed) . self::buildPlayButton($attrs),
            'div',
            get_block_wrapper_attributes($wrapper_attrs),
        );
    }

    private function buildFallback(VideoAttributes $attrs): string
    {
        $wrapper_attrs = $this->buildWrapperAttrs($attrs);
        $wrapper_attrs['data-video-unavailable'] = 'true';

        $fallback_content =
            self::buildPlaceholderPoster() .
            Str::wrapElement(esc_html(sprintf('Watch on %s', $attrs->provider->label)), 'span', [
                'class' => 'sitchco-video__fallback-label',
            ]);

        return Str::wrapElement(
            Str::wrapElement($fallback_content, 'a', [
                'class' => 'sitchco-video__fallback-link',
                'href' => $attrs->url,
                'target' => '_blank',
                'rel' => 'noopener noreferrer',
            ]),
            'div',
            get_block_wrapper_attributes($wrapper_attrs),
        );
    }

    private function buildModal(VideoAttributes $attrs, ?VideoOembedData $oembed): ModalData
    {
        // Always resolve oEmbed data for modal dialog content (even if InnerBlocks used for page poster)
        $modal_oembed = $oembed ?? (self::fetchOembedData($attrs) ?? new VideoOembedData());

        $thumb_img = $modal_oembed->hasThumbnail
            ? self::buildPosterImg($modal_oembed, [
                'alt' => '',
                'class' => 'sitchco-video__modal-poster-img',
                'width' => $modal_oembed->aspectWidth,
                'height' => $modal_oembed->aspectHeight,
            ])
            : '';

        $modal_content = Str::wrapElement(
            $thumb_img . Str::wrapElement('', 'div', ['class' => 'sitchco-video__spinner']),
            'div',
            [
                'class' => 'sitchco-video__modal-player',
                'data-url' => $attrs->url,
                'data-provider' => $attrs->provider->name,
                'data-video-id' => $attrs->videoId,
                'data-has-oembed-poster' => !$attrs->hasInnerBlocks ? 'true' : 'false',
                'style' => [
                    '--aspect-w' => $modal_oembed->aspectWidth,
                    '--aspect-h' => $modal_oembed->aspectHeight,
                    'aspect-ratio' => "$modal_oembed->aspectWidth / $modal_oembed->aspectHeight",
                ],
            ],
        );

        return new ModalData($attrs->modalId, $attrs->videoTitle, $modal_content, 'video');
    }

    private static function buildPoster(VideoAttributes $attrs, string $content, ?VideoOembedData $oembed): string
    {
        $style = null;
        if ($attrs->hasInnerBlocks) {
            $html = $content;
        } elseif ($oembed && $oembed->hasThumbnail) {
            $html = self::buildPosterImg($oembed, [
                'class' => 'sitchco-video__poster-img',
                'loading' => 'lazy',
            ]);
            if ($oembed->width && $oembed->height) {
                $style = ['aspect-ratio' => "$oembed->width / $oembed->height"];
            }
        } else {
            $html = self::buildPlaceholderPoster();
        }

        return Str::wrapElement($html, 'div', [
            'class' => 'sitchco-video__poster',
            'style' => $style,
        ]);
    }

    private static function buildPlaceholderPoster(): string
    {
        return Str::wrapElement('', 'div', ['class' => 'sitchco-video__placeholder-poster']);
    }

    private static function buildPosterImg(VideoOembedData $oembed, array $attributes = []): string
    {
        if (!$oembed->hasThumbnail) {
            return '';
        }

        return Str::wrapElement(
            '',
            'img',
            array_merge(['src' => $oembed->thumbnailUrl, 'alt' => $oembed->title], $attributes),
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
        $use = Str::wrapElement('', 'use', ['href' => '#icon-' . $attrs->provider->playIconName]);
        $svg = Str::wrapElement($use, 'svg', [
            'class' => 'sitchco-video__play-icon-svg',
            'aria-hidden' => 'true',
            'width' => VideoProvider::PLAY_ICON_WIDTH,
            'height' => $attrs->provider->playIconHeight,
            'viewBox' => sprintf('0 0 %s %s', VideoProvider::PLAY_ICON_WIDTH, $attrs->provider->playIconHeight),
        ]);

        $svg = apply_filters(
            VideoModule::hookName('play_icon_svg'),
            $svg,
            $attrs->provider->name,
            $attrs->playIconStyle,
        );

        $button_attrs = [
            'class' => ['sitchco-video__play-button', 'sitchco-video__play-button--' . $attrs->playIconStyle],
            'style' => [
                'position' => 'absolute',
                'left' => $attrs->playIconX . '%',
                'top' => $attrs->playIconY . '%',
                'transform' => 'translate(-50%, -50%)',
            ],
        ];

        if ($attrs->clickBehavior === 'poster') {
            $button_attrs['aria-hidden'] = 'true';
            return Str::wrapElement($svg, 'span', $button_attrs);
        }

        $button_attrs['type'] = 'button';
        $button_attrs['aria-label'] = $attrs->playAriaLabel();
        return Str::wrapElement($svg, 'button', $button_attrs);
    }
}
