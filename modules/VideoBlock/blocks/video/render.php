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

$wrapper_attributes = get_block_wrapper_attributes([
    'class' => 'sitchco-video',
    'data-url' => $attributes['url'],
    'data-provider' => $attributes['provider'] ?? '',
    'data-display-mode' => $attributes['displayMode'] ?? 'inline',
    'data-play-icon-style' => $attributes['playIconStyle'] ?? 'dark',
    'data-play-icon-x' => $attributes['playIconX'] ?? 50,
    'data-play-icon-y' => $attributes['playIconY'] ?? 50,
    'data-click-behavior' => $attributes['clickBehavior'] ?? 'poster',
]);

echo "<div $wrapper_attributes>$content</div>";
