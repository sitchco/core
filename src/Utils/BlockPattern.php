<?php

namespace Sitchco\Utils;

/**
 * class BlockPattern
 * @package Sitchco\Utils
 */
class BlockPattern
{
    public static function register(string $name, array $args): void
    {
        if (!function_exists('register_block_pattern')) {
            return;
        }

        /**
         * $args:
         *  - title
         *  - description
         *  - categories
         *  - content
         *  - keywords
         *  - viewportWidth
         *
         * https://developer.wordpress.org/reference/functions/register_block_pattern/
         */
        register_block_pattern("sitchco/$name", $args);
    }
}
