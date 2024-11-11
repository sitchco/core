<?php

namespace Sitchco\Utils;

class ArrayUtil
{
    /**
     * Recursively merges arrays, overwriting values instead of combining them into arrays.
     *
     * @param array ...$arrays
     * @return array
     */
    public static function mergeRecursiveDistinct(array ...$arrays): array
    {
        $merged = [];
        foreach ($arrays as $array) {
            foreach ($array as $key => $value) {
                if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                    $merged[$key] = self::mergeRecursiveDistinct($merged[$key], $value);
                } else {
                    $merged[$key] = $value;
                }
            }
        }
        return $merged;
    }
}