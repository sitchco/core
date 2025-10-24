<?php
/**
 * Expected:
 * @var array $context
 */

use Sitchco\Modules\SvgSprite\SvgSprite;
use Sitchco\Utils\Block;
use Sitchco\Utils\Str;

$Module = $GLOBALS['SitchcoContainer']->get(SvgSprite::class); /* @var SvgSprite $Module */

$name = $context['fields']['icon_name'] ?? 'unknown';

$rotation = $context['fields']['icon_rotation'] ?? 0;

$transform = $rotation ? "rotate({$rotation}deg)" : null;

$content = $Module->renderIcon($name, $context['is_preview'] ?? true);

$content = Str::wrapElement($content, 'span', [
    'class' => "sitchco-icon sitchco-icon--{$name}",
    'style' => ['--sitchco-icon-transform' => $transform],
]);

$context['render'] = Block::wrapperElement($content, [], $context['is_preview'], $context['fields']['icon_link']);
