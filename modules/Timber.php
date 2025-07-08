<?php

namespace Sitchco\Modules;

use Sitchco\Framework\Module;

use Timber\Timber as TimberLib;
use Sitchco\Utils\Timber as TimberUtils;

/**
 * class Timber
 * @package Sitchco\Integration
 */
class Timber extends Module
{
    public function init(): void
    {
        if (class_exists('Timber\Timber')) {
            TimberLib::init();
        }
        add_filter('timber/locations', function ($paths) {
            $paths[] = [SITCHCO_CORE_TEMPLATES_DIR];

            return $paths;
        });
        add_filter('timber/twig/functions', function ($functions) {
            $functions['include_with_context'] = [
                'callable' => [TimberUtils::class, 'includeWithContext'],
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
    static public function blockRenderCallback(array $block): void
    {
        $context = TimberLib::context();
        $context['post'] = TimberLib::get_post();
        $context['block']  = $block;
        $context['fields'] = get_fields();
        // Parent theme context inclusion
        $context = static::loadBlockContext($context, $block['path']);
        // Child theme context inclusion
        $path_parts = explode('/modules/', $block['path']);
        if (isset($path_parts[1])) {
            $child_path = get_stylesheet_directory() . '/modules/' . $path_parts[1];
            $context = static::loadBlockContext($context, $child_path);
        }

        $template_path = trailingslashit($block['path']) . 'block.twig';

        if (!file_exists($template_path)) {
            trigger_error("Twig template $template_path does not exist", E_USER_WARNING);
            return;
        }

        $blockNameParts = explode('/', $block['name']);
        $blockName = array_pop($blockNameParts);
        echo TimberUtils::compileWithContext($template_path, $context, "block/$blockName");
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
