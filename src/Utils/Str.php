<?php

namespace Sitchco\Utils;

use Illuminate\Support\Pluralizer;

/**
 * Utility class for string manipulation.
 */
class Str
{
    /**
     * Pluralize a given word.
     *
     * @param string $word The word to pluralize.
     *
     * @return string The plural form of the given word.
     */
    public static function plural(string $word): string
    {
        return Pluralizer::plural($word);
    }

    /**
     * Convert a plural word to its singular form.
     *
     * @param string $word The word to singularize.
     *
     * @return string The singular form of the given word.
     */
    public static function singular(string $word): string
    {
        return Pluralizer::singular($word);
    }

    /**
     * Convert a symbol name to camelCase.
     *
     * @param string $symbol_name The symbol name to convert.
     *
     * @return string The camelCase formatted string.
     */
    public static function toCamelCase(string $symbol_name): string
    {
        return lcfirst(static::toPascalCase($symbol_name));
    }

    /**
     * Convert a symbol name to PascalCase.
     *
     * @param string $symbol_name The symbol name to convert.
     *
     * @return string The PascalCase formatted string.
     */
    public static function toPascalCase(string $symbol_name): string
    {
        return str_replace('_', '', ucwords($symbol_name, '_'));
    }

    /**
     * Convert a symbol name to snake_case.
     *
     * @param string $symbol_name The symbol name to convert.
     *
     * @return string The snake_case formatted string.
     */
    public static function toSnakeCase(string $symbol_name): string
    {
        return ltrim(strtolower(preg_replace('/[A-Z]/', '_$0', $symbol_name)), '_');
    }
}