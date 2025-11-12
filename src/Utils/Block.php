<?php

declare(strict_types=1);

namespace Sitchco\Utils;

use Sitchco\Support\FilePath;

/**
 * Class Component
 * @package Sitchco\Utils
 */
class Block
{
    public static function relativeBlockPath(string $blocksPath): string
    {
        $pathParts = explode(DIRECTORY_SEPARATOR, $blocksPath);
        $relativeParts = array_splice($pathParts, -3);
        return implode(DIRECTORY_SEPARATOR, $relativeParts);
    }

    public static function isPreview(): bool
    {
        if (is_admin() && acf_is_block_editor()) {
            return true;
        }
        if (wp_doing_ajax() && $_POST['action'] === 'acf/ajax/fetch-block') {
            return !empty($_POST['query']['preview']);
        }
        return false;
    }

    public static function wrapperElement(
        string $content,
        array $attributes,
        ?array $link = null,
        string $tag = 'div',
    ): string {
        if (static::isPreview()) {
            return $content;
        }
        if ($link) {
            $linkParts = Acf::linkToElParts($link, $attributes, $content);
            extract($linkParts); // replaces $content and $attributes;
            $tag = 'a';
        }
        $attributes = static::wrapperAttributes($attributes);
        return Str::wrapElement($content, $tag, $attributes);
    }

    public static function wrapperAttributes(array $attributes): string
    {
        return static::isPreview()
            ? ArrayUtil::toAttributes($attributes)
            : get_block_wrapper_attributes(array_filter($attributes));
    }

    public static function formatInnerBlocksAttribute(mixed $value, string $strategy = 'auto'): ?string
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

    public static function getBlockMetadata(string $path): array
    {
        static $blockMetadataCache = [];

        if (!$path) {
            return [];
        }
        if (array_key_exists($path, $blockMetadataCache)) {
            return $blockMetadataCache[$path];
        }
        $metadataFile = trailingslashit($path) . 'block.json';
        if (!is_readable($metadataFile)) {
            $blockMetadataCache[$path] = [];
            return $blockMetadataCache[$path];
        }
        $raw = file_get_contents($metadataFile);
        if (!is_string($raw)) {
            $blockMetadataCache[$path] = [];
            return $blockMetadataCache[$path];
        }
        $decoded = json_decode($raw, true);
        $blockMetadataCache[$path] = is_array($decoded) ? $decoded : [];
        return $blockMetadataCache[$path];
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
}
