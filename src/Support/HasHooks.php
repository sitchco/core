<?php

namespace Sitchco\Support;

use Sitchco\Utils\Hooks;

trait HasHooks
{
    /**
     * Set a descriptive name for the class to use in building action/filter hooks
     */
    public const HOOK_NAME = '';

    /**
     * Set a prefix building action/filter hooks
     *
     * Implemented class needs to set:
     *
     * public const HOOK_PREFIX = '';
     */

    /**
     * @param string ...$name_parts
     * @return string
     */
    protected static function hookName(...$name_parts): string
    {
        $prefix = defined('static::HOOK_PREFIX') ? static::HOOK_PREFIX : '';
        return Hooks::name($prefix, static::HOOK_NAME, ...$name_parts);
    }


}