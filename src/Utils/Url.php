<?php

namespace Sitchco\Utils;

/**
 * Class Url
 * @package Sitchco\Utils
 */
class Url
{
    /**
     * Checks if a given URL is external.
     *
     * @param string $url The URL to check.
     *
     * @return bool True if the URL is external, false otherwise.
     */
    public static function isExternal(string $url): bool
    {
        if ($url === '' || str_starts_with($url, '#')) {
            return false;
        }

        $components = parse_url($url);
        if ($components === false) {
            return false; // Malformed URL
        }

        // Handle relative URLs like "/about"
        if (!isset($components['host']) && isset($components['path']) && str_starts_with($components['path'], '/')) {
            return false;
        }

        $home = parse_url(home_url());
        if (!isset($home['host']) || !isset($components['host'])) {
            return true;
        }

        return self::normalizeHost($components['host']) !== self::normalizeHost($home['host']);
    }

    /**
     * Normalize a hostname by removing 'www.' and converting to lowercase.
     *
     * @param string $host The host to normalize.
     *
     * @return string Normalized host.
     */
    private static function normalizeHost(string $host): string
    {
        return str_replace('www.', '', strtolower($host));
    }

    /**
     * Decode a URL-safe Base64 encoded string.
     *
     * @param string $input A Base64 encoded string.
     *
     * @return string Decoded string.
     */
    public static function safeB64Decode(string $input): string
    {
        $input = str_pad($input, strlen($input) + (4 - strlen($input) % 4) % 4, '=', STR_PAD_RIGHT);
        return base64_decode(strtr($input, '-_', '+/')) ?: '';
    }

    /**
     * Encode a string using URL-safe Base64.
     *
     * @param string $input The string to encode.
     *
     * @return string URL-safe Base64 encoded string.
     */
    public static function safeB64Encode(string $input): string
    {
        return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
    }
}
