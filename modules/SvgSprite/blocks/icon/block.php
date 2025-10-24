<?php
/**
 * Expected:
 * @var array $context
 */

use Sitchco\Modules\SvgSprite\SvgSprite;
use Sitchco\Utils\Block;

$Module = $GLOBALS['SitchcoContainer']->get(SvgSprite::class); /* @var SvgSprite $Module */

$name = $context['fields']['icon_name'];

$rotation = $context['fields']['icon_rotation'] ?? 0;

$transform = $rotation ? "rotate({$rotation}deg)" : null;

$context['attrs'] = Block::wrapperAttributes(
    [
        'class' => "sitchco-icon sitchco-icon--{$name}",
        'style' => ['--sitchco-icon-transform' => $transform],
    ],
    $context['is_preview'],
);

$context['svg'] = $Module->renderIcon($name, $context['is_preview'] ?? true);
