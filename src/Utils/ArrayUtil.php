<?php

namespace Sitchco\Utils;

/**
 * Class ArrayUtil
 * @package Sitchco\Utils
 */
class ArrayUtil
{
    /**
     * Recursively merges arrays, overwriting values instead of combining them into arrays.
     *
     * @param array ...$arrays Arrays to merge.
     * @return array The merged array.
     */
    public static function mergeRecursiveDistinct(array ...$arrays): array
    {
        $merged = [];
        foreach ($arrays as $array) {
            foreach ($array as $key => $value) {
                $merged[$key] =
                    is_array($value) && isset($merged[$key]) && is_array($merged[$key])
                        ? self::mergeRecursiveDistinct($merged[$key], $value)
                        : $value;
            }
        }
        return $merged;
    }

    /**
     * Applies a callback function to each key-value pair in an associative array.
     *
     * @param callable $callback Function to apply to each key-value pair.
     * @param array $array The input array.
     * @return array The transformed array.
     */
    public static function arrayMapAssoc(callable $callback, array $array): array
    {
        return array_map(fn($key) => $callback($key, $array[$key]), array_keys($array));
    }

    /**
     * Applies a callback to each element and flattens the result into a single array.
     *
     * @param callable $callback Function to apply to each element.
     * @param array $array The input array.
     * @return array The flattened array.
     */
    public static function arrayMapFlat(callable $callback, array $array): array
    {
        return array_merge(...array_map($callback, $array));
    }

    /**
     * Converts an indexed array into an associative array using a column value as the key.
     *
     * @param array $array The input array.
     * @param string $column The column name to use as keys.
     * @return array The associative array.
     */
    public static function arrayToAssocByColumn(array $array, string $column): array
    {
        return array_combine(array_column($array, $column), $array);
    }

    /**
     * Converts an array into an HTML list.
     *
     * @param array $contentArr The array of list items.
     * @param array $args Additional attributes for the list.
     * @return string The generated HTML list.
     */
    public static function convertToList(array $contentArr, array $args = []): string
    {
        $args = array_merge(
            [
                'list_type' => 'ul',
                'list_class' => '',
                'item_class' => '',
                'attr' => [],
            ],
            $args,
        );

        $attrs = self::toAttributes($args['attr']);
        $listClass = !empty($args['list_class']) ? " class=\"{$args['list_class']}\"" : '';

        $listItems = array_map(fn($item) => "<li class=\"{$args['item_class']}\">$item</li>", $contentArr);

        return "<{$args['list_type']}{$listClass} {$attrs}>" . implode('', $listItems) . "</{$args['list_type']}>";
    }

    /**
     * Converts an associative array to an HTML attribute string.
     *
     * @param array $arr The associative array of attributes.
     * @param string $glue The separator for multiple values.
     * @return string The attribute string.
     */
    public static function toAttributes(array $arr, string $glue = ' '): string
    {
        return implode(
            ' ',
            array_map(
                function ($key, $value) use ($glue) {
                    if (is_array($value)) {
                        $value = $key == 'style' ? static::toCSSProperties($value) : implode($glue, $value);
                    }
                    return sprintf('%s="%s"', $key, $value);
                },
                array_keys($arr),
                $arr,
            ),
        );
    }

    public static function toCSSProperties(array $arr): string
    {
        return implode(
            '',
            array_filter(
                array_map(
                    function ($value, $key) {
                        if (empty($value)) {
                            return '';
                        }
                        if (is_array($value)) {
                            $value = implode(' ', $value);
                        }
                        return "$key: $value;";
                    },
                    $arr,
                    array_keys($arr),
                ),
            ),
        );
    }

    /**
     * Computes the difference between two arrays and returns the changes.
     *
     * @param array $args The modified array.
     * @param array $defaults The original array.
     * @return array|string The difference array or an empty string if no differences exist.
     */
    public static function diff(array $args, array $defaults): array|string
    {
        if ($args === $defaults) {
            return '';
        }
        return array_diff_assoc($args, $defaults);
    }

    /**
     * Recursively converts all values in an array to strings.
     *
     * @param array $array The input array.
     * @return array The array with all values converted to strings.
     */
    public static function stringify(array $array): array
    {
        return array_map(fn($value) => is_array($value) ? self::stringify($value) : (string) $value, $array);
    }

    /**
     * Recursively converts numeric strings in an array to their numeric types.
     *
     * @param array $array The input array.
     * @return array The array with numeric values typecasted.
     */
    public static function numerify(array $array): array
    {
        return array_map(
            fn($value) => is_array($value) ? self::numerify($value) : (is_numeric($value) ? $value + 0 : $value),
            $array,
        );
    }
}
