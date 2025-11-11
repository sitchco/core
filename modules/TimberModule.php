<?php

namespace Sitchco\Modules;

use Sitchco\Framework\Module;

use Sitchco\Support\DateTime;
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
    protected static array $blockMetadataCache = [];

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
                'callable' => [self::class, 'renderInnerBlocksTag'],
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

        $metadata = static::getBlockMetadata($block['path'] ?? '');
        if ($metadata) {
            $innerBlocksDefaults = $metadata['innerBlocksConfig'] ?? [];
            if (isset($metadata['allowedBlocks']) && !isset($innerBlocksDefaults['allowedBlocks'])) {
                $innerBlocksDefaults['allowedBlocks'] = $metadata['allowedBlocks'];
            }
            if ($innerBlocksDefaults) {
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
            }
        }
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

    public static function renderInnerBlocksTag(array $config = []): string
    {
        if (isset($config['allowed']) && !isset($config['allowedBlocks'])) {
            $config['allowedBlocks'] = $config['allowed'];
        }
        if (isset($config['template_lock']) && !isset($config['templateLock'])) {
            $config['templateLock'] = $config['template_lock'];
        }

        $attributeStrategies = [
            'allowedBlocks' => 'json',
            'template' => 'json',
            'templateLock' => 'string',
            'orientation' => 'string',
            'renderAppender' => 'bool',
            'templateInsertUpdatesSelection' => 'bool',
            'layout' => 'json',
            'align' => 'string',
            'className' => 'string',
        ];

        $attributes = [];
        foreach ($attributeStrategies as $key => $strategy) {
            if (!array_key_exists($key, $config)) {
                continue;
            }
            $formattedValue = static::formatInnerBlocksAttribute($config[$key], $strategy);
            if ($formattedValue !== null) {
                $attributes[$key] = $formattedValue;
            }
        }

        if (isset($config['attributes']) && is_array($config['attributes'])) {
            foreach ($config['attributes'] as $key => $value) {
                $formattedValue = static::formatInnerBlocksAttribute($value);
                if ($formattedValue !== null) {
                    $attributes[$key] = $formattedValue;
                }
            }
        }

        if (!$attributes) {
            return '<InnerBlocks />';
        }

        $parts = [];
        foreach ($attributes as $key => $value) {
            $parts[] = sprintf('%s="%s"', esc_attr($key), esc_attr($value));
        }

        return '<InnerBlocks ' . implode(' ', $parts) . ' />';
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

        $allowedBlocks = static::normalizeIterable($context['allowedBlocks'] ?? null);
        if ($allowedBlocks !== null) {
            $config['allowedBlocks'] = $allowedBlocks;
        }

        $template = static::normalizeIterable($context['innerBlocksTemplate'] ?? null, preserveKeys: false);
        if ($template !== null) {
            $config['template'] = $template;
        }

        if ($config) {
            $context['innerBlocksConfig'] = $config;
        }

        return $context;
    }

    protected static function normalizeIterable(mixed $value, bool $preserveKeys = true): ?array
    {
        if ($value === null) {
            return null;
        }
        if (is_array($value)) {
            return $preserveKeys ? $value : array_values($value);
        }
        if ($value instanceof Traversable) {
            return iterator_to_array($value, $preserveKeys);
        }
        return null;
    }

    protected static function getBlockMetadata(string $path): array
    {
        if (!$path) {
            return [];
        }
        if (array_key_exists($path, static::$blockMetadataCache)) {
            return static::$blockMetadataCache[$path];
        }
        $metadataFile = trailingslashit($path) . 'block.json';
        if (!is_readable($metadataFile)) {
            static::$blockMetadataCache[$path] = [];
            return static::$blockMetadataCache[$path];
        }
        $raw = file_get_contents($metadataFile);
        if (!is_string($raw)) {
            static::$blockMetadataCache[$path] = [];
            return static::$blockMetadataCache[$path];
        }
        $decoded = json_decode($raw, true);
        static::$blockMetadataCache[$path] = is_array($decoded) ? $decoded : [];
        return static::$blockMetadataCache[$path];
    }

    protected static function formatInnerBlocksAttribute(mixed $value, string $strategy = 'auto'): ?string
    {
        if ($value === null) {
            return null;
        }

        switch ($strategy) {
            case 'json':
                $encoded = wp_json_encode($value);
                if ($encoded === false) {
                    return null;
                }
                return $encoded;
            case 'bool':
                return $value ? 'true' : 'false';
            case 'string':
                return (string) $value;
            case 'auto':
            default:
                if (is_bool($value)) {
                    return $value ? 'true' : 'false';
                }
                if (is_array($value) || is_object($value)) {
                    $encoded = wp_json_encode($value);
                    return $encoded === false ? null : $encoded;
                }
                return (string) $value;
        }
    }
}
