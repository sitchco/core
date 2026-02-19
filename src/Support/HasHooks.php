<?php

namespace Sitchco\Support;

use Sitchco\Utils\Hooks;

trait HasHooks
{
    /**
     * Set a descriptive name for the class to use in building action/filter hooks
     */
    public const HOOK_SUFFIX = '';

    /**
     * Set a prefix building action/filter hooks
     *
     * Implemented class needs to set:
     *
     * public const HOOK_PREFIX = '';
     */

    /**
     * @param string ...$name_parts
     * @return HookName
     */
    public static function hookName(...$name_parts): string
    {
        $prefix = defined('static::HOOK_PREFIX') ? static::HOOK_PREFIX : '';
        if (!empty($name_parts) && $name_parts[0] === static::HOOK_SUFFIX) {
            array_shift($name_parts);
        }
        return Hooks::name($prefix, static::HOOK_SUFFIX, ...$name_parts);
    }
}
