<?php

namespace Sitchco\Modules\VideoBlock;

use Sitchco\Modules\UIModal\ModalData;
use Sitchco\Modules\UIModal\UIModal;
use Sitchco\Utils\Cache;

/**
 * Handles all data preparation and HTML rendering for the sitchco/video block.
 *
 * Separates pure computation (data preparation) from side effects (modal registration, HTML output).
 * All utility methods are static to avoid instantiation overhead for pure functions.
 */
class VideoBlockRenderer
{
    /**
     * Fetch oEmbed data with transient caching.
     *
     * Uses WP_oEmbed::get_data() for structured data (not wp_oembed_get() which returns HTML).
     * Caches results for 30 days. Caches null for failures with a 1-hour TTL to allow recovery.
     */
    public static function getCachedOembedData(string $url): ?object
    {
        $cache_key = 'sitchco_voembed_' . md5($url);
        $result = Cache::rememberTransient(
            $cache_key,
            function () use ($url) {
                return _wp_oembed_get_object()->get_data($url, []) ?: null;
            },
            30 * DAY_IN_SECONDS,
            HOUR_IN_SECONDS,
        );

        return $result ?: null;
    }

    /**
     * Upgrade oEmbed thumbnail URL to high-resolution variant.
     *
     * YouTube returns hqdefault.jpg (480x360 with letterbox bars) — upgrade to maxresdefault.jpg (1280x720).
     * Vimeo returns tiny thumbnails (295x166) — rewrite CDN dimensions to 1280x720.
     */
    public static function upgradeThumbnailUrl(
        string $url,
        string $provider,
        ?int $width = null,
        ?int $height = null,
    ): string {
        if ($provider === 'youtube') {
            $url = preg_replace('#/hqdefault\.jpg$#', '/maxresdefault.jpg', $url);
        }

        if ($provider === 'vimeo') {
            $isPortrait = $width && $height && $height > $width;
            $dims = $isPortrait ? '720x1280' : '1280x720';
            $url = preg_replace('/_\d+x\d+/', '_' . $dims, $url);
        }

        return $url;
    }

    /**
     * Detect video provider from URL.
     *
     * Mirrors the JS detectProvider() in editor.jsx so that blocks created
     * programmatically (e.g. wp_insert_post) without a provider attribute
     * still render correctly.
     */
    public static function detectProvider(string $url): string
    {
        if (preg_match('#(?:youtube\.com|youtu\.be)/#i', $url)) {
            return 'youtube';
        }
        if (preg_match('#vimeo\.com/#i', $url)) {
            return 'vimeo';
        }
        return '';
    }

    /**
     * Extract video ID from URL.
     */
    public static function extractVideoId(string $url, string $provider): string
    {
        if ($provider === 'youtube') {
            if (
                preg_match(
                    '/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|shorts\/|watch\?v=|watch\?.+&v=))([\w-]{11})/',
                    $url,
                    $matches,
                )
            ) {
                return $matches[1];
            }
        }
        if ($provider === 'vimeo') {
            if (preg_match('/vimeo\.com\/(?:video\/)?(\d+)/', $url, $matches)) {
                return $matches[1];
            }
        }
        return '';
    }

    /**
     * Orchestrate the full block render.
     *
     * Phase 1: Early return for empty URL.
     * Phase 2: Extract attributes.
     * Phase 3: Build view data (pure computation).
     * Phase 4: Modal side effects (if display_mode is modal/modal-only).
     * Phase 5: Accessibility attributes.
     * Phase 6: Return HTML string (no echo).
     */
    public static function render(array $attributes, string $content, object $block, ?UIModal $uiModal = null): string
    {
        // Phase 1 - Early return
        if (empty($attributes['url'])) {
            return $content;
        }

        // Phase 2 - Extract attributes into local vars
        $url = $attributes['url'];
        $provider = $attributes['provider'] ?? '' ?: self::detectProvider($url);
        $video_title = $attributes['videoTitle'] ?? '';
        $play_icon_style = $attributes['playIconStyle'] ?? 'dark';
        $play_icon_x = $attributes['playIconX'] ?? 50;
        $play_icon_y = $attributes['playIconY'] ?? 50;
        $click_behavior = $attributes['clickBehavior'] ?? 'poster';
        $display_mode = $attributes['displayMode'] ?? 'inline';

        // Phase 3 - Build view data (pure computation)
        $video_id = self::extractVideoId($url, $provider);

        // Poster resolution chain
        $has_inner_blocks = count($block->inner_blocks) > 0;
        $oembed = null;

        if ($has_inner_blocks) {
            $poster_html = $content;
            $poster_style = '';
        } else {
            $oembed = self::getCachedOembedData($url);
            // Detect domain-level embedding restrictions (e.g. Vimeo domain_status_code: 403)
            if ($oembed && isset($oembed->domain_status_code) && $oembed->domain_status_code >= 400) {
                $oembed = null;
            }
            if ($oembed && !empty($oembed->thumbnail_url)) {
                $oembed_width = (int) ($oembed->width ?? 0);
                $oembed_height = (int) ($oembed->height ?? 0);
                $thumbnail_url = self::upgradeThumbnailUrl(
                    $oembed->thumbnail_url,
                    $provider,
                    $oembed_width,
                    $oembed_height,
                );

                $escaped_thumb = esc_url($thumbnail_url);
                $escaped_title = esc_attr($oembed->title ?? '');
                $poster_html = <<<HTML
                <img class="sitchco-video__poster-img" src="{$escaped_thumb}" alt="{$escaped_title}" loading="lazy">
                HTML;
                $poster_style =
                    $oembed_width && $oembed_height
                        ? sprintf(' style="aspect-ratio: %s / %s"', esc_attr($oembed_width), esc_attr($oembed_height))
                        : '';
            } else {
                $poster_html = '<div class="sitchco-video__placeholder-poster"></div>';
                $poster_style = '';
            }
        }

        // Detect oEmbed failure (no InnerBlocks and oEmbed returned nothing)
        $oembed_failed = !$has_inner_blocks && $oembed === null;

        // Wrapper attributes
        $wrapper_attrs = [
            'class' => 'sitchco-video',
            'data-url' => $url,
            'data-provider' => $provider,
            'data-display-mode' => $display_mode,
            'data-play-icon-style' => $play_icon_style,
            'data-play-icon-x' => $play_icon_x,
            'data-play-icon-y' => $play_icon_y,
            'data-click-behavior' => $click_behavior,
            'data-video-id' => $video_id,
            'data-video-title' => $video_title,
        ];

        // Phase 4 - Modal side effects (skip when oEmbed failed — no player to show in modal)
        if (($display_mode === 'modal' || $display_mode === 'modal-only') && !$oembed_failed) {
            $modal_id = $attributes['modalId'] ?? '';
            if (empty($modal_id)) {
                $modal_id = sanitize_title($video_title) ?: $video_id;
            }
            if (empty($modal_id)) {
                $display_mode = 'inline';
            }

            // Always resolve oEmbed data for modal dialog content (even if InnerBlocks used for page poster)
            $modal_oembed = $oembed ?? self::getCachedOembedData($url);
            $thumb_url =
                $modal_oembed && !empty($modal_oembed->thumbnail_url)
                    ? self::upgradeThumbnailUrl(
                        $modal_oembed->thumbnail_url,
                        $provider,
                        (int) ($modal_oembed->width ?? 0),
                        (int) ($modal_oembed->height ?? 0),
                    )
                    : '';
            $aspect_w = $modal_oembed && !empty($modal_oembed->width) ? $modal_oembed->width : 16;
            $aspect_h = $modal_oembed && !empty($modal_oembed->height) ? $modal_oembed->height : 9;

            // Determine if oEmbed thumbnail was used as the page poster (for adaptive loading state in JS)
            $has_oembed_poster = !$has_inner_blocks ? 'true' : 'false';

            // Build modal thumbnail image HTML
            $thumb_img = '';
            if ($thumb_url) {
                $escaped_url = esc_url($thumb_url);
                $escaped_w = esc_attr($aspect_w);
                $escaped_h = esc_attr($aspect_h);
                $thumb_img = <<<HTML
                <img src="{$escaped_url}" alt="" class="sitchco-video__modal-poster-img" width="{$escaped_w}" height="{$escaped_h}">
                HTML;
            }

            // Build modal player content HTML
            $escaped_url_attr = esc_attr($url);
            $escaped_provider = esc_attr($provider);
            $escaped_video_id = esc_attr($video_id);
            $escaped_has_oembed = esc_attr($has_oembed_poster);
            $escaped_aspect_w = esc_attr($aspect_w);
            $escaped_aspect_h = esc_attr($aspect_h);
            $modal_content = <<<HTML
            <div class="sitchco-video__modal-player" data-url="{$escaped_url_attr}" data-provider="{$escaped_provider}" data-video-id="{$escaped_video_id}" data-has-oembed-poster="{$escaped_has_oembed}" style="--aspect-w: {$escaped_aspect_w}; --aspect-h: {$escaped_aspect_h}; aspect-ratio: {$escaped_aspect_w} / {$escaped_aspect_h}">{$thumb_img}<div class="sitchco-video__spinner"></div></div>
            HTML;

            // Queue modal for wp_footer rendering via UIModal
            if ($uiModal === null) {
                // Defensive fallback — UIModal should be injected via VideoBlock::uiModal()
                $uiModal = $GLOBALS['SitchcoContainer']->get(UIModal::class);
            }
            $modalData = new ModalData($modal_id, $video_title, $modal_content, 'video');
            $uiModal->loadModal($modalData);

            // Modal-only: render nothing on page
            if ($display_mode === 'modal-only') {
                return '';
            }

            // Modal mode: use normalized ID (ModalData may prefix digit-leading IDs with "modal-")
            $wrapper_attrs['data-modal-id'] = $modalData->id();
        }

        // oEmbed failure: return link fallback (before play button / accessibility / normal render)
        if ($oembed_failed) {
            // Modal-only with no oEmbed: nothing to render
            if ($display_mode === 'modal-only') {
                return '';
            }

            $wrapper_attrs['data-video-unavailable'] = 'true';
            $wrapper_attributes = get_block_wrapper_attributes($wrapper_attrs);

            $provider_label = $provider === 'youtube' ? 'YouTube' : 'Vimeo';
            $escaped_url = esc_url($url);
            $escaped_label = esc_html(sprintf('Watch on %s', $provider_label));

            return sprintf(
                '<div %s><a class="sitchco-video__fallback-link" href="%s" target="_blank" rel="noopener noreferrer"><div class="sitchco-video__placeholder-poster"></div><span class="sitchco-video__fallback-label">%s</span></a></div>',
                $wrapper_attributes,
                $escaped_url,
                $escaped_label,
            );
        }

        $play_button = self::buildPlayButton(
            $provider,
            $play_icon_style,
            $play_icon_x,
            $play_icon_y,
            $video_title,
            $click_behavior,
        );

        // Phase 5 - Accessibility attributes (ACCS-03)
        if ($click_behavior === 'poster') {
            $wrapper_attrs['role'] = 'button';
            $wrapper_attrs['tabindex'] = '0';
            $wrapper_attrs['aria-label'] = sprintf('Play video: %s', $video_title);
        }

        // Phase 6 - Return HTML string
        $wrapper_attributes = get_block_wrapper_attributes($wrapper_attrs);

        return sprintf(
            '<div %s><div class="sitchco-video__poster"%s>%s</div>%s</div>',
            $wrapper_attributes,
            $poster_style,
            $poster_html,
            $play_button,
        );
    }

    /**
     * Build the play button HTML with SVG icon.
     *
     * In poster click mode, the wrapper div is the interactive element (role="button"),
     * so the play icon is rendered as a presentational <span> to avoid nested interactive
     * elements (ARIA 1.2 violation). In icon click mode, the play icon is the sole
     * interactive element and renders as a <button>.
     *
     * @param string     $provider        Video provider (youtube, vimeo, etc.)
     * @param string     $play_icon_style Icon style variant (dark/light)
     * @param int|float  $play_icon_x     Horizontal position percentage
     * @param int|float  $play_icon_y     Vertical position percentage
     * @param string     $video_title     Video title for accessibility label
     * @param string     $click_behavior  Click behavior mode (poster or icon)
     */
    private static function buildPlayButton(
        string $provider,
        string $play_icon_style,
        int|float $play_icon_x,
        int|float $play_icon_y,
        string $video_title,
        string $click_behavior,
    ): string {
        // Play icon SVG via sprite <use>
        $icon_name = $provider === 'youtube' ? 'youtube-play' : 'generic-play';
        $icon_width = '68';
        $icon_height = $provider === 'youtube' ? '48' : '68';

        $escaped_icon_width = esc_attr($icon_width);
        $escaped_icon_height = esc_attr($icon_height);
        $escaped_icon_name = esc_attr($icon_name);
        $svg = <<<HTML
        <svg class="sitchco-video__play-icon-svg" aria-hidden="true" width="{$escaped_icon_width}" height="{$escaped_icon_height}" viewBox="0 0 {$escaped_icon_width} {$escaped_icon_height}"><use href="#icon-{$escaped_icon_name}"></use></svg>
        HTML;

        $svg = apply_filters(VideoBlock::hookName('play_icon_svg'), $svg, $provider, $play_icon_style);

        $style = sprintf(
            'position:absolute;left:%s%%;top:%s%%;transform:translate(-50%%,-50%%)',
            esc_attr($play_icon_x),
            esc_attr($play_icon_y),
        );

        if ($click_behavior === 'poster') {
            return sprintf(
                '<span class="sitchco-video__play-button sitchco-video__play-button--%s" aria-hidden="true" style="%s">%s</span>',
                esc_attr($play_icon_style),
                $style,
                $svg,
            );
        }

        return sprintf(
            '<button type="button" class="sitchco-video__play-button sitchco-video__play-button--%s" aria-label="%s" style="%s">%s</button>',
            esc_attr($play_icon_style),
            esc_attr(sprintf('Play video: %s', $video_title)),
            $style,
            $svg,
        );
    }
}
