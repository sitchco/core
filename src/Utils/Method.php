<?php

namespace Sitchco\Utils;

/**
 * Class Method
 *
 * Provides utility methods for working with class method names.
 *
 * @package Sitchco\Utils
 */
class Method
{
    /**
     * Get the actual method name from an object based on the provided name and prefix.
     *
     * This method first checks if a method exists on the object with a name constructed
     * by combining the prefix and the provided name (separated by an underscore). If not found,
     * it converts the method name to camelCase and checks again.
     *
     * @param object $object The object to inspect for the method.
     * @param string $name The base name of the method.
     * @param string $prefix The prefix to use when constructing the method name. Defaults to 'get'.
     *
     * @return string|false The method name if found, or false if the method does not exist.
     */
    public static function getMethodName($object, $name, $prefix = 'get')
    {
        $method_name = $prefix ? sprintf('%s_%s', $prefix, $name) : $name;
        if (method_exists($object, $method_name)) {
            return $method_name;
        }
        $camel_case_method_name = Str::toCamelCase($method_name);
        if (method_exists($object, $camel_case_method_name)) {
            return $camel_case_method_name;
        }
        return false;
    }
}