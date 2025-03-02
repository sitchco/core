<?php

namespace Sitchco\Utils;

use Illuminate\Support\Pluralizer;

/**
 * Class Str
 *
 * @package Sitchco\Utils
 *
 * TODO: deep dive into Illuminate to see what other types of string manipulation we can leverage
 */
class Str
{
    /**
     * Pluralizes a given word.
     *
     * @param string $word The word to pluralize.
     * @return string The plural form of the given word.
     */
    public static function plural(string $word): string
    {
        return Pluralizer::plural($word);
    }

    /**
     * Converts a plural word to its singular form.
     *
     * @param string $word The word to singularize.
     * @return string The singular form of the given word.
     */
    public static function singular(string $word): string
    {
        return Pluralizer::singular($word);
    }

    /**
     * Converts a string to camelCase.
     *
     * @param string $symbolName The string to convert.
     * @return string The camelCase formatted string.
     */
    public static function toCamelCase(string $symbolName): string
    {
        return lcfirst(static::toPascalCase($symbolName));
    }

    /**
     * Converts a string to PascalCase.
     *
     * @param string $symbolName The string to convert.
     * @return string The PascalCase formatted string.
     */
    public static function toPascalCase(string $symbolName): string
    {
        return str_replace('_', '', ucwords($symbolName, '_'));
    }

    /**
     * Converts a string to snake_case.
     *
     * @param string $symbolName The string to convert.
     * @return string The snake_case formatted string.
     */
    public static function toSnakeCase(string $symbolName): string
    {
        return ltrim(strtolower(preg_replace('/[A-Z]/', '_$0', $symbolName)), '_');
    }

    /**
     * Truncates a string to a specified length, rounded to the nearest word.
     *
     * @param string $text The text to truncate.
     * @param int $length The maximum length.
     * @param string $append The text to append if truncated.
     * @return string The truncated text.
     */
    public static function truncate(string $text, int $length = 48, string $append = "..."): string
    {
        if (strlen($text) > $length) {
            $text = substr($text, 0, $length);
            $text = self::cutUsingLast(" ", $text, "left", false);
            return $text . $append;
        }
        return $text;
    }

    /**
     * Cuts a string from the last occurrence of a character.
     *
     * @param string $character The character to search for.
     * @param string $string The string to cut.
     * @param string $side 'left' to keep left portion, 'right' to keep right portion.
     * @param bool $keepCharacter Whether to include the character in the result.
     * @return string|false The cut string or false on failure.
     */
    public static function cutUsingLast(string $character, string $string, string $side = 'left', bool $keepCharacter = true): string|false
    {
        $offset = $keepCharacter ? 1 : 0;
        $wholeLength = strlen($string);
        $rightLength = strlen(strrchr($string, $character)) - 1;
        $leftLength = $wholeLength - $rightLength - 1;

        return match ($side) {
            'left' => substr($string, 0, $leftLength + $offset),
            'right' => substr($string, -($rightLength + $offset)),
            default => false,
        };
    }

    /**
     * Sanitizes a string to be used as a key.
     *
     * @param string $label The string to sanitize.
     * @return string The sanitized key.
     */
    public static function sanitizeKey(string $label): string
    {
        return str_replace('-', '_', sanitize_title($label));
    }

    /**
     * Extracts the first paragraph from an HTML string.
     *
     * @param string $html The HTML content.
     * @return string The first paragraph.
     */
    public static function getFirstParagraph(string $html): string
    {
        $start = strpos($html, '<p>');
        $end = strpos($html, '</p>', $start);
        return substr($html, $start, $end - $start + 4);
    }

    /**
     * Retrieves the last specified number of words from a string.
     *
     * @param string $string The input string.
     * @param int $count The number of words to retrieve.
     * @return string The last words in the string.
     */
    public static function getLastWords(string $string, int $count): string
    {
        $arr = explode(' ', $string);
        $frag = array_slice($arr, -$count, $count);
        return trim(ucwords(implode(' ', $frag)));
    }

    /**
     * Generates Lorem Ipsum placeholder text.
     *
     * @link http://loripsum.net
     * @param string $paramStr Parameters for Lorem Ipsum API.
     * @return string The placeholder text.
     */
    public static function placeholderText(string $paramStr = "5 long decorate link"): string
    {
        $params = explode(' ', $paramStr);
        $baseUrl = "http://loripsum.net/api/";
        return wp_remote_retrieve_body(wp_remote_get($baseUrl . implode('/', $params)));
    }

    /**
     * Wraps content in an anchor tag with attributes.
     *
     * @param string $content The content to wrap.
     * @param string|null $url The URL to link to.
     * @param array $attributes Additional attributes for the anchor tag.
     * @return string The wrapped content.
     */
    public static function wrapLink(string $content, ?string $url, array $attributes = []): string
    {
        if ($url) {
            $attributes['href'] = $url;
        }
        return static::wrapElement($content, 'a', $attributes);
    }

    /**
     * Wraps content in a specified HTML tag with attributes.
     *
     * @param string $content The content to wrap.
     * @param string $tag The HTML tag to use.
     * @param array $attributes Attributes for the tag.
     * @return string The wrapped content.
     */
    public static function wrapElement(string $content, string $tag, array $attributes = []): string
    {
        $attributes = ArrayUtil::toAttributes($attributes);
        return sprintf(
            '<%1$s%2$s>%3$s</%1$s>',
            $tag,
            $attributes ? ' ' . $attributes : '',
            $content
        );
    }
}
