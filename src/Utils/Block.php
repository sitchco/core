<?php

declare(strict_types=1);

namespace Sitchco\Utils;

/**
 * Class Component
 * @package Sitchco\Utils
 */
class Block
{
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
}
