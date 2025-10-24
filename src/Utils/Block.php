<?php

declare(strict_types=1);

namespace Sitchco\Utils;

/**
 * Class Component
 * @package Sitchco\Utils
 */
class Block
{
    public static function wrapperElement(
        string $content,
        array $attributes,
        bool $isPreview,
        ?array $link = null,
        string $tag = 'div',
    ): string {
        if ($isPreview) {
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

    public static function wrapperAttributes(array $attributes, bool $isPreview = false): string
    {
        $attributes['style'] = ArrayUtil::toCSSProperties($attributes['style'] ?? []);
        return $isPreview
            ? ArrayUtil::toAttributes($attributes)
            : get_block_wrapper_attributes(array_filter($attributes));
    }
}
