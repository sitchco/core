<?php

namespace Sitchco\Modules;

use Sitchco\Framework\Module;

use Sitchco\Support\DateTime;
use Timber\Timber;
use Sitchco\Utils\TimberUtil;
use WP_Block;

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
        add_filter('timber/meta/transform_value', '__return_true');
        add_filter('acf/format_value/type=date_picker', [$this, 'transformDatePicker'], 20);
        add_filter('acf/format_value/type=date_time_picker', [$this, 'transformDatePicker'], 20);
    }

    /**
     * Transform ACF date picker field
     * @param mixed $value
     * @return DateTime|string
     */
    public static function transformDatePicker(mixed $value): DateTime|string
    {
        if (!$value instanceof \DateTimeImmutable) {
            return $value;
        }
        return new DateTime($value);
    }

    /**
     * Gathers context and renders Twig template for block.
     * Used within block.json
     *
     * @param array $block Full block attributes and settings
     * @param string $content
     * @param bool $is_preview
     * @param int $post_id
     * @param null $wp_block
     * @return void
     */
    public static function blockRenderCallback(
        array $block,
        string $content = '',
        bool $is_preview = false,
        int $post_id = 0,
        WP_Block $wp_block = null,
    ): void {
        $context = Timber::context();
        $context['post'] = $post_id ? Timber::get_post($post_id) : Timber::get_post();
        $context['fields'] = get_fields();
        $context['is_preview'] = $is_preview;
        $context['content'] = $content;

        if ($wp_block instanceof WP_Block) {
            $childBlocks = iterator_to_array($wp_block->inner_blocks);
            $block['innerBlocks'] = array_map(static fn(WP_Block $child) => $child->parsed_block, $childBlocks);
        } elseif (!isset($block['innerBlocks']) && $content) {
            $block['innerBlocks'] = parse_blocks($content);
        }
        $context['block'] = $block;

        // Parent theme context inclusion
        $context = static::loadBlockContext($context, $block['path']);
        // Child theme context inclusion
        $path_parts = explode('/modules/', $block['path']);
        if (isset($path_parts[1])) {
            $child_path = get_stylesheet_directory() . '/modules/' . $path_parts[1];
            $context = static::loadBlockContext($context, $child_path);
        }
        if ($context['render'] ?? false) {
            echo $context['render'];
            return;
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
