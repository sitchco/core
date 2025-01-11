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

    public static function arrayMapAssoc(callable $callback, array $array): array
    {
        return array_map(function($key) use ($callback, $array){
            return $callback($key, $array[$key]);
        }, array_keys($array));
    }

    public static function arrayMapFlat(callable $callback, array $array): array
    {
        return array_merge(...array_map($callback, $array));
    }

}