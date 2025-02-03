<?php

namespace Sitchco\Utils;

/**
 * class Method
 * @package Sitchco\Utils
 */
class Method
{
    public static function getMethodName($object, $name, $prefix = 'get')
    {
        $method_name = $prefix ? sprintf('%s_%s', $prefix, $name) : $name;
        if (method_exists($object, $method_name)) {
            return $method_name;
        }
        $camel_case_method_name = static::toCamelCase($method_name);
        if (method_exists($object, $camel_case_method_name)) {
            return $camel_case_method_name;
        }
        return false;
    }

    public static function toCamelCase($symbol_name): string
    {
        return lcfirst(static::toPascalCase($symbol_name));
    }

    public static function toPascalCase($symbol_name): string
    {
        return str_replace('_', '', ucwords($symbol_name, '_'));
    }

    public static function toSnakeCase($symbol_name): string
    {
        return ltrim(strtolower(preg_replace('/[A-Z]/', '_$0', $symbol_name)), '_');
    }
}