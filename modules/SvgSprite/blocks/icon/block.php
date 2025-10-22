<?php
/**
 * Expected:
 * @var array $context
 */

use Sitchco\Modules\SvgSprite\SvgSprite;

$Module = $GLOBALS['SitchcoContainer']->get(SvgSprite::class); /* @var SvgSprite $Module */

$context['svg'] = $Module->renderIcon($context['fields']['icon_name'], $context['is_preview'] ?? true);
