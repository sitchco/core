<?php

/**
 * Server-side render template for sitchco/video block.
 *
 * @var array    $attributes Block attributes
 * @var string   $content    InnerBlocks content (serialized HTML)
 * @var WP_Block $block      Block instance
 */

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
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached ?: null;
        }
        $oembed = _wp_oembed_get_object()->get_data($url, []);
        set_transient($cache_key, $oembed ?: '', 30 * DAY_IN_SECONDS);
        return $oembed ?: null;
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
$has_inner_blocks = !empty(trim($content));

if ($has_inner_blocks) {
    $poster_html = $content;
    $poster_style = '';
} else {
    $oembed = sitchco_video_get_cached_oembed_data($url);
    if ($oembed && !empty($oembed->thumbnail_url)) {
        $poster_html = sprintf(
            '<img class="sitchco-video__poster-img" src="%s" alt="%s" width="%s" height="%s" loading="lazy">',
            esc_url($oembed->thumbnail_url),
            esc_attr($oembed->title ?? ''),
            esc_attr($oembed->width ?? ''),
            esc_attr($oembed->height ?? ''),
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
$icon_name = $provider === 'youtube' ? "youtube-play-{$play_icon_style}" : "generic-play-{$play_icon_style}";
$icon_height = $provider === 'youtube' ? '48' : '68';
$svg = sprintf(
    '<svg class="sitchco-video__play-icon-svg" aria-hidden="true" width="68" height="%s"><use href="#icon-%s"></use></svg>',
    $icon_height,
    esc_attr($icon_name),
);

// Play button (ACCS-01, ACCS-02)
$play_button = sprintf(
    '<button class="sitchco-video__play-button" aria-label="%s" style="position:absolute;left:%s%%;top:%s%%;transform:translate(-50%%,-50%%)">%s</button>',
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
