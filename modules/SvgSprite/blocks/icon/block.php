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

return Block::wrapperElement(
    $container->get(SvgSprite::class)->renderIcon($name, Rotation::tryFrom($rotation)),
    [],
    $context['fields']['icon_link'] ?: [],
    'span',
);
