<?php

namespace Sitchco\Modules;

use Sitchco\Framework\Module;

use Timber\Timber;
use Sitchco\Utils\TimberUtil;

/**
 * class Timber
 * @package Sitchco\Integration
 */
class TimberModule extends Module
{
    public function init(): void
    {
        if (class_exists('Timber\Timber')) {
            Timber::init();
        }
        add_filter('timber/locations', function ($paths) {
            $paths[] = [SITCHCO_CORE_TEMPLATES_DIR];

            return $paths;
        });
        add_filter('timber/twig/functions', function ($functions) {
            $functions['include_with_context'] = [
                'callable' => [TimberUtil::class, 'includeWithContext'],
            ];
            return $functions;
        });
    }

    /**
     * Gathers context and renders Twig template for block.
     * Used within block.json
     *
     * @param array $block Full block attributes and settings
     * @return void
     */
    public static function blockRenderCallback(array $block): void
    {
        $context = Timber::context();
        $context['post'] = Timber::get_post();
        $context['block'] = $block;
        $context['fields'] = get_fields();
        // Parent theme context inclusion
        $context = static::loadBlockContext($context, $block['path']);
        // Child theme context inclusion
        $path_parts = explode('/modules/', $block['path']);
        if (isset($path_parts[1])) {
            $child_path = get_stylesheet_directory() . '/modules/' . $path_parts[1];
            $context = static::loadBlockContext($context, $child_path);
        }

        $template_path = trailingslashit(basename($block['path'])) . 'block.twig';
        $blockNameParts = explode('/', $block['name']);
        $blockName = array_pop($blockNameParts);
        echo TimberUtil::compileWithContext($template_path, $context, "block/$blockName");
    }

    protected static function loadBlockContext(array $context, $path): array
    {
        $context_file = trailingslashit($path) . 'block.php';
        if (file_exists($context_file)) {
            include $context_file;
        }
        return $context;
    }
}
