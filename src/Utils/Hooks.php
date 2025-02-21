<?php

namespace Sitchco\Utils;

class Hooks
{
    const ROOT = 'sitchco';

    public static function name(...$parts): string
    {
        return implode('/', array_filter([static::ROOT, ...$parts]));
    }
}