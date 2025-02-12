<?php

namespace Sitchco\Utils;

class Hooks
{
    public static function name(...$parts): string
    {
        return implode('/', array_filter(['sitchco', ...$parts]));
    }
}