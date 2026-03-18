<?php

/**
 * Server-side render template for sitchco/video block.
 *
 * @var array    $attributes Block attributes
 * @var string   $content    InnerBlocks content (serialized HTML)
 * @var WP_Block $block      Block instance
 */

use Sitchco\Modules\VideoBlock\VideoBlockRenderer;

$output = $GLOBALS['SitchcoContainer']->get(VideoBlockRenderer::class)->render($attributes, $content, $block);

if ($output !== '') {
    echo $output;
}
