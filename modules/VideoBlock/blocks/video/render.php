<?php

/**
 * Server-side render template for sitchco/video block.
 *
 * @var array    $attributes Block attributes
 * @var string   $content    InnerBlocks content (serialized HTML)
 * @var WP_Block $block      Block instance
 */

use Sitchco\Modules\VideoBlock\VideoBlock;
use Sitchco\Modules\VideoBlock\VideoBlockRenderer;

$videoBlock = $GLOBALS['SitchcoContainer']->get(VideoBlock::class);
$output = VideoBlockRenderer::render($attributes, $content, $block, $videoBlock->uiModal());

if ($output !== '') {
    echo $output;
}
