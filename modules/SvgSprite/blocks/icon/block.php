<?php
/**
 * Expected:
 * @var array $context
 */

use Sitchco\Modules\SvgSprite\Rotation;
use Sitchco\Modules\SvgSprite\SvgSprite;
use Sitchco\Utils\Block;

$Module = $GLOBALS['SitchcoContainer']->get(SvgSprite::class); /* @var SvgSprite $Module */

$name = $context['fields']['icon_name'] ?? 'unknown';

$rotation = $context['fields']['icon_rotation'] ?? 0;

$content = $Module->renderIcon($name, Rotation::tryFrom($rotation));

$context['render'] = Block::wrapperElement($content, [], $context['fields']['icon_link'] ?: []);
