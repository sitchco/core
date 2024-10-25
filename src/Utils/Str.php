<?php

namespace Sitchco\Utils;

use Illuminate\Support\Pluralizer;

class Str
{
    public static function plural($word): string
    {
        return Pluralizer::plural($word);
    }

    public static function singular($word): string
    {
        return Pluralizer::singular($word);
    }
}