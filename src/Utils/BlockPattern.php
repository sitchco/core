<?php

namespace Sitchco\Utils;

/**
 * Class BlockPattern
 * @package Sitchco\Utils
 */
class BlockPattern
{
    /**
     * Registers a block pattern in WordPress.
     *
     * @param string       $name The unique name of the block pattern.
     * @param string|array $args Either a path to a JSON file containing block pattern data
     *                           or an associative array with pattern details.
     *
     * @return void
     */
    public static function register(string $name, string|array $args): void
    {
        if (!function_exists('register_block_pattern')) {
            return;
        }

        if (is_string($args)) {
            $file_path = $args;
            if (!file_exists($file_path)) {
                return;
            }
            $args = json_decode(file_get_contents($file_path), true);

            // Check if decoding was successful and the required keys are present
            if (json_last_error() !== JSON_ERROR_NONE || !isset($args['title'], $args['content'])) {
                return;
            }
        }

        /**
         * Expected keys in $args:
         * - title (required)
         * - description (optional)
         * - categories (optional)
         * - content (required)
         * - keywords (optional)
         * - viewportWidth (optional)
         *
         * @see https://developer.wordpress.org/reference/functions/register_block_pattern/
         */
        register_block_pattern("sitchco/$name", $args);
    }
}
