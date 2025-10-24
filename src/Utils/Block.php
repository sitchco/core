<?php

declare(strict_types=1);

namespace Sitchco\Utils;

/**
 * Class Component
 * @package Sitchco\Utils
 */
class Block
{
    public static function wrapperAttributes(array $attributes, bool $isPreview = false): string
    {
        $attributes['style'] = ArrayUtil::toCSSProperties($attributes['style']);
        return $isPreview
            ? ArrayUtil::toAttributes($attributes)
            : get_block_wrapper_attributes(array_filter($attributes));
    }
}
