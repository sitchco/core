<?php

namespace Sitchco\Utils;

/**
 * class BlockPattern
 * @package Sitchco\Utils
 */
class BlockPattern
{
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
         * $args:
         *  - title (required)
         *  - description
         *  - categories
         *  - content (required)
         *  - keywords
         *  - viewportWidth
         *
         * https://developer.wordpress.org/reference/functions/register_block_pattern/
         */
        register_block_pattern("sitchco/$name", $args);
    }
}
