<?php

namespace Sitchco\Utils;

use Sitchco\Support\HookName;

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
     * @return string The fully qualified hook name value object.
     */
    public static function name(string ...$parts): string
    {
        return (new HookName( ...$parts))->value();
    }

    public static function callOrAddAction(string $hook_name, callable $callback, int $priority = 10, ...$args): void
    {
        add_action($hook_name, fn() => $callback(...$args), $priority);
        if (did_action($hook_name)) {
            $callback(...$args);
        }
    }
}
