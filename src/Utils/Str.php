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
    public static function truncate(string $text, int $length = 48, string $append = '...'): string
    {
        if (strlen($text) > $length) {
            $text = substr($text, 0, $length);
            $text = self::cutUsingLast(' ', $text, 'left', false);
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
    public static function cutUsingLast(
        string $character,
        string $string,
        string $side = 'left',
        bool $keepCharacter = true,
    ): string|false {
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
    public static function placeholderText(string $paramStr = '5 long decorate link'): string
    {
        $params = explode(' ', $paramStr);
        $baseUrl = 'http://loripsum.net/api/';
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
     * @param array|string $attributes Attributes for the tag.
     * @return string The wrapped content.
     */
    public static function wrapElement(string $content, string $tag, array|string $attributes = []): string
    {
        if (is_array($attributes)) {
            $attributes = ArrayUtil::toAttributes($attributes);
        }
        return sprintf('<%1$s%2$s>%3$s</%1$s>', $tag, $attributes ? ' ' . $attributes : '', $content);
    }

    /**
     * Formats a numeric amount as a currency string.
     *
     * Uses PHP's NumberFormatter (ext-intl) for full locale-aware output when
     * available, and falls back to a simple symbol + number_format_i18n() approach
     * on hosts without ext-intl.
     *
     * @param float|int $amount  The numeric amount.
     * @param array     $options {
     *   @type string      $currency ISO 4217 currency code. Default "USD".
     *   @type string|null $locale   BCP-47 locale (e.g. "en_US"). Default null
     *                               (uses WordPress locale or "en_US").
     *   @type int|null    $decimals Force a fixed number of fraction digits.
     *                               Default null (use locale/currency default).
     * }
     * @return string Formatted currency string.
     */
    public static function formatCurrency(float|int $amount, array $options = []): string
    {
        $opts = array_merge(
            [
                'currency' => 'USD',
                'locale' => null,
                'decimals' => null,
            ],
            $options,
        );

        if (class_exists('NumberFormatter')) {
            $locale = $opts['locale'] ?? (function_exists('get_locale') ? get_locale() : 'en_US');
            $fmt = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
            if ($opts['decimals'] !== null) {
                $fmt->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $opts['decimals']);
                $fmt->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $opts['decimals']);
            }
            $result = $fmt->formatCurrency((float) $amount, strtoupper($opts['currency']));
            if ($result !== false) {
                return $result;
            }
        }
        return self::formatCurrencyFallback((float) $amount, $opts['currency'], $opts['decimals']);
    }

    /**
     * Formats a low/high pair as a currency range string with a single leading
     * symbol and a configurable separator (e.g. "$25-75" or "$25 – 75").
     *
     * When $low equals $high, returns a single formatted amount instead of a range.
     *
     * @param float|int $low     The low end of the range.
     * @param float|int $high    The high end of the range.
     * @param array     $options {
     *   @type string      $currency  ISO 4217 currency code. Default "USD".
     *   @type string|null $locale    BCP-47 locale. Default null.
     *   @type int|null    $decimals  Force a fixed number of fraction digits.
     *                                Default null.
     *   @type string      $separator String placed between the two amounts.
     *                                Default "-".
     * }
     * @return string Formatted currency range string.
     */
    public static function formatCurrencyRange(float|int $low, float|int $high, array $options = []): string
    {
        $opts = array_merge(
            [
                'currency' => 'USD',
                'locale' => null,
                'decimals' => null,
                'separator' => '-',
            ],
            $options,
        );

        $formatOpts = [
            'currency' => $opts['currency'],
            'locale' => $opts['locale'],
            'decimals' => $opts['decimals'],
        ];

        if ($low == $high) {
            return self::formatCurrency($low, $formatOpts);
        }

        [$symbol, $lowNumeric] = self::splitCurrencySymbol(self::formatCurrency($low, $formatOpts));
        [, $highNumeric] = self::splitCurrencySymbol(self::formatCurrency($high, $formatOpts));

        return $symbol . $lowNumeric . $opts['separator'] . $highNumeric;
    }

    /**
     * Splits a formatted currency string into its symbol and bare numeric portion.
     *
     * Works for both prefix locales (e.g. "$25.50" -> ["$", "25.50"]) and suffix
     * locales (e.g. "25,50 €" -> ["€", "25,50"]).
     */
    private static function splitCurrencySymbol(string $formatted): array
    {
        preg_match('/^(\D*)/u', $formatted, $pre);
        preg_match('/(\D*)$/u', $formatted, $suf);
        $prefix = $pre[1] ?? '';
        $suffix = $suf[1] ?? '';
        $numeric = substr($formatted, strlen($prefix), strlen($formatted) - strlen($prefix) - strlen($suffix));
        $symbolRaw = $prefix !== '' ? $prefix : $suffix;
        $symbol = preg_replace('/^[\s\x{00A0}]+|[\s\x{00A0}]+$/u', '', $symbolRaw);
        return [$symbol, $numeric];
    }

    /**
     * Fallback currency formatter for environments without ext-intl.
     */
    private static function formatCurrencyFallback(float $amount, string $currency, ?int $decimals = null): string
    {
        $code = strtoupper($currency);
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'CAD' => 'CA$',
        ];
        $symbol = $symbols[$code] ?? $code . ' ';
        return $symbol . number_format_i18n($amount, $decimals ?? 2);
    }

    /**
     * Normalize color value to RGB format
     *
     * @param string $hex Hex color (#RGB or #RRGGBB) or RGB string
     * @return string Comma-separated RGB values (e.g., "255, 0, 0")
     */
    public static function hexToRGB(string $hex): string
    {
        if (str_starts_with($hex, '#')) {
            $hex = ltrim($hex, '#');

            if (strlen($hex) == 3) {
                // 3-digit hex: #RGB
                sscanf($hex, '%1x%1x%1x', $r, $g, $b);
                $r = $r * 17; // Convert to 0-255 range (e.g., F -> FF)
                $g = $g * 17;
                $b = $b * 17;
            } else {
                // 6-digit hex: #RRGGBB
                sscanf($hex, '%02x%02x%02x', $r, $g, $b);
            }

            return "$r, $g, $b";
        }

        // Already RGB format
        preg_match('/(?<=\()(\d+,\s*\d+,\s*\d+)/', $hex, $matches);
        return $matches[0] ?? '';
    }
}
