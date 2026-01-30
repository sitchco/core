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
$background_size = $context['fields']['background_size'] ?? '';

// Get background color from block attributes
$block = $context['block'] ?? [];
$background_color = null;

if (!empty($block['backgroundColor'])) {
    $background_color = 'var(--wp--preset--color--' . $block['backgroundColor'] . ')';
} elseif (!empty($block['style']['color']['background'])) {
    $background_color = $block['style']['color']['background'];
}

$style = [];

if ($background_color) {
    $style['--sitchco-icon-background'] = $background_color;
}

$classes = [];

if ($round_background) {
    $classes[] = 'is-round-background';
}
if ($background_size) {
    $classes[] = 'background-size-' . $background_size;
}

$content = $container->get(SvgSprite::class)->renderIcon($name, Rotation::tryFrom($rotation), $classes, $style);

return Block::wrapperElement($content, $context['fields']['icon_link'] ?: [], 'span', true);
