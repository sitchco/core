<?php

namespace Sitchco\Modules;

use Sitchco\Framework\Module;

use Sitchco\Support\DateTime;
use Sitchco\Utils\ArrayUtil;
use Sitchco\Utils\Block;
use Timber\Timber;
use Sitchco\Utils\TimberUtil;
use WP_Block;
use Traversable;

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
            $functions['inner_blocks'] = [
                'callable' => [Block::class, 'renderInnerBlocksTag'],
                'is_safe' => ['html'],
            ];
            $functions['render_block'] = [
                'callable' => 'render_block',
                'is_safe' => ['html'],
            ];
            $functions['block_wrapper_attributes'] = [
                'callable' => 'get_block_wrapper_attributes',
                'is_safe' => ['html'],
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
        $context = static::setupContext(...func_get_args());

        // Parent theme context inclusion
        $context = static::loadBlockContext($context, $block['path']);

        // Child theme context inclusion
        $relative = Block::relativeBlockPath($block['path']);
        $child_path = get_stylesheet_directory() . '/modules/' . $relative;
        $context = static::loadBlockContext($context, $child_path);

        $metadata = Block::getBlockMetadata($block['path'] ?? '');
        $context = static::mergeMetadataContext($context, $metadata);
        $context = static::normalizeInnerBlocksContext($context);

        // Auto-inject helper variables for templates
        $context['inner_blocks'] = $block['innerBlocks'] ?? [];
        $context['wrapper_attributes'] = $block['wrapper_attributes'] ?? [];

        if ($context['render'] ?? false) {
            echo $context['render'];
            return;
        }
        $template_path = trailingslashit(basename($block['path'])) . 'block.twig';
        $blockNameParts = explode('/', $block['name']);
        $blockName = array_pop($blockNameParts);
        echo TimberUtil::compileWithContext($template_path, $context, "block/$blockName");
    }

    protected static function setupContext(
        array $block,
        string $content = '',
        bool $is_preview = false,
        int $post_id = 0,
        WP_Block $wp_block = null,
    ): array {
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
        return $context;
    }

    protected static function mergeMetadataContext(array $context, array $metadata): array
    {
        $innerBlocksDefaults = $metadata['innerBlocksConfig'] ?? [];
        if (isset($metadata['allowedBlocks']) && !isset($innerBlocksDefaults['allowedBlocks'])) {
            $innerBlocksDefaults['allowedBlocks'] = $metadata['allowedBlocks'];
        }
        if (empty($innerBlocksDefaults)) {
            return $context;
        }
        $existingInnerBlocksConfig = $context['innerBlocksConfig'] ?? [];
        if (is_array($existingInnerBlocksConfig)) {
            $innerBlocksDefaults = array_merge($innerBlocksDefaults, $existingInnerBlocksConfig);
        }
        $context['innerBlocksConfig'] = $innerBlocksDefaults;
        if (!isset($context['allowedBlocks']) && isset($innerBlocksDefaults['allowedBlocks'])) {
            $context['allowedBlocks'] = $innerBlocksDefaults['allowedBlocks'];
        }
        if (!isset($context['innerBlocksTemplate']) && isset($innerBlocksDefaults['template'])) {
            $context['innerBlocksTemplate'] = $innerBlocksDefaults['template'];
        }
        return $context;
    }

    protected static function loadBlockContext(array $context, $path): array
    {
        $context_file = trailingslashit($path) . 'block.php';
        $container = $GLOBALS['SitchcoContainer'];
        if (file_exists($context_file)) {
            include $context_file;
        }
        return $context;
    }

    protected static function normalizeInnerBlocksContext(array $context): array
    {
        $config = $context['innerBlocksConfig'] ?? [];
        if (!is_array($config)) {
            $config = [];
        }

        $allowedBlocks = ArrayUtil::normalizeIterable($context['allowedBlocks'] ?? null);
        if ($allowedBlocks !== null) {
            $config['allowedBlocks'] = $allowedBlocks;
        }

        $template = ArrayUtil::normalizeIterable($context['innerBlocksTemplate'] ?? null, preserveKeys: false);
        if ($template !== null) {
            $config['template'] = $template;
        }

        if ($config) {
            $context['innerBlocksConfig'] = $config;
        }

        return $context;
    }
}
