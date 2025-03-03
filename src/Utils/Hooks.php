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

    public static function add_eager_action(string $hook_name, callable $callback, ...$args): void
    {
        if (did_action($hook_name)) {
            $callback(...$args);
        } else {
            add_action($hook_name, fn() => $callback(...$args));
        }
    }
}
