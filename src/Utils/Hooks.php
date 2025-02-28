<?php

namespace Sitchco\Utils;

/**
 * Class Hooks
 * @package Sitchco\Utils
 */
class Hooks
{
    /** @var string The root namespace for hooks. */
    public const ROOT = 'sitchco';

    /**
     * Generates a namespaced hook name by joining the given parts with a forward slash.
     *
     * @param string ...$parts Additional segments to append to the root namespace.
     *
     * @return string The fully qualified hook name.
     */
    public static function name(string ...$parts): string
    {
        return implode('/', array_filter([self::ROOT, ...$parts]));
    }
}
