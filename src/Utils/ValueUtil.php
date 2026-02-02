<?php

namespace Sitchco\Utils;

class ValueUtil
{
    /**
     * Checks if a value is meaningfully empty while preserving valid falsy values like 0.
     *
     * By default, considers these as empty: null, '', false, []
     * Preserves as valid: 0, 0.0, "0"
     *
     * @param mixed $value The value to check.
     * @param bool $preserveFalse If true, false is considered a valid value (not empty).
     * @return bool True if the value is empty, false otherwise.
     */
    public static function isEmptyValue(mixed $value, bool $preserveFalse = false): bool
    {
        if ($value === null || $value === '' || $value === []) {
            return true;
        }
        if ($value === false && !$preserveFalse) {
            return true;
        }
        return false;
    }
}
