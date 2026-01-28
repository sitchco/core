<?php
/**
 * Expected:
 * @var array $context
 * @var ContainerInterface $container
 */

use Psr\Container\ContainerInterface;
use Sitchco\Modules\SvgSprite\Rotation;
use Sitchco\Modules\SvgSprite\SvgSprite;
use Sitchco\Utils\Block;

$name = $context['fields']['icon_name'] ?? 'unknown';
$rotation = $context['fields']['icon_rotation'] ?? 0;
$round_background = $context['fields']['round_background'] ?? false;

// Get background color from block attributes
$block = $context['block'] ?? [];
$background_color = null;

if (!empty($block['backgroundColor'])) {
    $background_color = 'var(--wp--preset--color--' . $block['backgroundColor'] . ')';
} elseif (!empty($block['style']['color']['background'])) {
    $background_color = $block['style']['color']['background'];
}

// Extract padding from block style
$padding = $block['style']['spacing']['padding'] ?? [];

$style_parts = [];

if ($background_color) {
    $style_parts[] = '--sitchco-icon-background: ' . $background_color;
}

// Convert padding to CSS variables
foreach (['top', 'right', 'bottom', 'left'] as $side) {
    if (!empty($padding[$side])) {
        $style_parts[] = '--sitchco-icon-padding-' . $side . ': ' . Block::cssVarValue($padding[$side]);
    }
}

$attributes = [];

if ($style_parts) {
    $attributes['style'] = implode('; ', $style_parts) . ';';
}

if ($round_background) {
    $attributes['class'] = 'is-round-background';
}

return Block::wrapperElement(
    $container->get(SvgSprite::class)->renderIcon($name, Rotation::tryFrom($rotation)),
    $attributes,
    $context['fields']['icon_link'] ?: [],
    'span',
    true,
);
