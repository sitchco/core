<?php
/**
 * Expected:
 * @var array $context
 * @var ContainerInterface $container
 * @var array $wrapper
 */

use Psr\Container\ContainerInterface;
use Sitchco\Modules\SvgSprite\Rotation;
use Sitchco\Modules\SvgSprite\SvgSprite;
use Sitchco\Utils\Block;

$name = $context['fields']['icon_name'] ?? 'unknown';

$rotation = $context['fields']['icon_rotation'] ?? 0;

$wrapper['link'] = $context['fields']['icon_link'] ?: [];

return $container->get(SvgSprite::class)->renderIcon($name, Rotation::tryFrom($rotation));
