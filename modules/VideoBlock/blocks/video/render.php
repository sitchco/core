<?php

/**
 * Server-side render template for sitchco/video block.
 *
 * @var array    $attributes Block attributes
 * @var string   $content    InnerBlocks content (serialized HTML)
 * @var WP_Block $block      Block instance
 */

use Sitchco\Modules\UIModal\ModalData;
use Sitchco\Modules\UIModal\ModalType;
use Sitchco\Modules\UIModal\UIModal;
use Sitchco\Utils\Cache;

if (empty($attributes['url'])) {
    echo $content;
    return;
}

/**
 * Fetch oEmbed data with transient caching.
 *
 * Uses WP_oEmbed::get_data() for structured data (not wp_oembed_get() which returns HTML).
 * Caches results for 30 days. Caches empty string for failures to avoid retrying on every load.
 */
if (!function_exists('sitchco_video_get_cached_oembed_data')) {
    function sitchco_video_get_cached_oembed_data(string $url): ?object
    {
        $cache_key = 'sitchco_voembed_' . md5($url);
        $result = Cache::rememberTransient(
            $cache_key,
            function () use ($url) {
                return _wp_oembed_get_object()->get_data($url, []) ?: '';
            },
            30 * DAY_IN_SECONDS,
        );

        return $result ?: null;
    }
}

/**
 * Upgrade oEmbed thumbnail URL to high-resolution variant.
 *
 * YouTube returns hqdefault.jpg (480x360 with letterbox bars) — upgrade to maxresdefault.jpg (1280x720).
 * Vimeo returns tiny thumbnails (295x166) — rewrite CDN dimensions to 1280x720.
 */
if (!function_exists('sitchco_video_upgrade_thumbnail_url')) {
    function sitchco_video_upgrade_thumbnail_url(string $url, string $provider): string
    {
        if ($provider === 'youtube') {
            $url = preg_replace('#/hqdefault\.jpg$#', '/maxresdefault.jpg', $url);
        }

        if ($provider === 'vimeo') {
            $url = preg_replace('/_\d+x\d+/', '_1280x720', $url);
        }

        return $url;
    }
}

/**
 * Extract video ID from URL.
 */
if (!function_exists('sitchco_video_extract_id')) {
    function sitchco_video_extract_id(string $url, string $provider): string
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
}

$url = $attributes['url'];
$provider = $attributes['provider'] ?? '';
$video_title = $attributes['videoTitle'] ?? '';
$play_icon_style = $attributes['playIconStyle'] ?? 'dark';
$play_icon_x = $attributes['playIconX'] ?? 50;
$play_icon_y = $attributes['playIconY'] ?? 50;
$click_behavior = $attributes['clickBehavior'] ?? 'poster';
$display_mode = $attributes['displayMode'] ?? 'inline';

// Extract video ID
$video_id = sitchco_video_extract_id($url, $provider);

// Poster resolution chain
$has_inner_blocks = count($block->inner_blocks) > 0;

if ($has_inner_blocks) {
    $poster_html = $content;
    $poster_style = '';
} else {
    $oembed = sitchco_video_get_cached_oembed_data($url);
    if ($oembed && !empty($oembed->thumbnail_url)) {
        $thumbnail_url = sitchco_video_upgrade_thumbnail_url($oembed->thumbnail_url, $provider);

        $poster_html = sprintf(
            '<img class="sitchco-video__poster-img" src="%s" alt="%s" loading="lazy">',
            esc_url($thumbnail_url),
            esc_attr($oembed->title ?? ''),
        );
        $poster_style =
            $oembed->width && $oembed->height
                ? sprintf(' style="aspect-ratio: %s / %s"', esc_attr($oembed->width), esc_attr($oembed->height))
                : '';
    } else {
        $poster_html = '<div class="sitchco-video__placeholder-poster"></div>';
        $poster_style = '';
    }
}

// Play icon SVG via sprite <use>
$icon_name = $provider === 'youtube' ? 'youtube-play' : 'generic-play';
$icon_width = '68';
$icon_height = $provider === 'youtube' ? '48' : '68';
$svg = sprintf(
    '<svg class="sitchco-video__play-icon-svg" aria-hidden="true" width="%s" height="%s" viewBox="0 0 %s %s"><use href="#icon-%s"></use></svg>',
    $icon_width,
    $icon_height,
    $icon_width,
    $icon_height,
    esc_attr($icon_name),
);

// Play button (ACCS-01, ACCS-02)
$play_button = sprintf(
    '<button class="sitchco-video__play-button sitchco-video__play-button--%s" aria-label="%s" style="position:absolute;left:%s%%;top:%s%%;transform:translate(-50%%,-50%%)">%s</button>',
    esc_attr($play_icon_style),
    esc_attr(sprintf('Play video: %s', $video_title)),
    esc_attr($play_icon_x),
    esc_attr($play_icon_y),
    $svg,
);

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

// Modal and modal-only display mode branching
if ($display_mode === 'modal' || $display_mode === 'modal-only') {
    $modal_id = $attributes['modalId'] ?? '';
    if (empty($modal_id)) {
        $modal_id = sanitize_title($video_title);
    }

    // Always resolve oEmbed data for modal dialog content (even if InnerBlocks used for page poster)
    $modal_oembed = $oembed ?? sitchco_video_get_cached_oembed_data($url);
    $thumb_url =
        $modal_oembed && !empty($modal_oembed->thumbnail_url)
            ? sitchco_video_upgrade_thumbnail_url($modal_oembed->thumbnail_url, $provider)
            : '';
    $aspect_w = $modal_oembed && !empty($modal_oembed->width) ? $modal_oembed->width : 16;
    $aspect_h = $modal_oembed && !empty($modal_oembed->height) ? $modal_oembed->height : 9;

    // Determine if oEmbed thumbnail was used as the page poster (for adaptive loading state in JS)
    $has_oembed_poster = !$has_inner_blocks ? 'true' : 'false';

    // Build modal dialog content HTML
    $thumb_img = $thumb_url
        ? sprintf(
            '<img src="%s" alt="" class="sitchco-video__modal-poster-img" width="%s" height="%s">',
            esc_url($thumb_url),
            esc_attr($aspect_w),
            esc_attr($aspect_h),
        )
        : '';

    $modal_content = sprintf(
        '<div class="sitchco-video__modal-player" data-url="%s" data-provider="%s" data-video-id="%s" data-has-oembed-poster="%s" style="aspect-ratio: %s / %s">%s<div class="sitchco-video__spinner"></div></div>',
        esc_attr($url),
        esc_attr($provider),
        esc_attr($video_id),
        esc_attr($has_oembed_poster),
        esc_attr($aspect_w),
        esc_attr($aspect_h),
        $thumb_img,
    );

    // Queue modal for wp_footer rendering via UIModal
    $uiModal = $GLOBALS['SitchcoContainer']->get(UIModal::class);
    $uiModal->loadModal(new ModalData($modal_id, $video_title, $modal_content, ModalType::VIDEO));

    // Modal-only: render nothing on page
    if ($display_mode === 'modal-only') {
        return;
    }

    // Modal mode: add modal trigger data attribute to wrapper
    $wrapper_attrs['data-modal-id'] = $modal_id;
}

// ACCS-03: poster click mode adds role, tabindex, and aria-label to wrapper
if ($click_behavior === 'poster') {
    $wrapper_attrs['role'] = 'button';
    $wrapper_attrs['tabindex'] = '0';
    $wrapper_attrs['aria-label'] = sprintf('Play video: %s', $video_title);
}

$wrapper_attributes = get_block_wrapper_attributes($wrapper_attrs);

printf(
    '<div %s><div class="sitchco-video__poster"%s>%s</div>%s</div>',
    $wrapper_attributes,
    $poster_style,
    $poster_html,
    $play_button,
);
