<?php

namespace Sitchco\Utils;

use Kint\Kint;

/**
 * Class Debug
 * @package Sitchco\Utils
 */
class Debug
{
    /**
     * Log a value to error log if not in production or if forced.
     *
     * @param mixed $value The value to log.
     * @param bool $force Whether to force the logging regardless of environment.
     * @return void
     */
    public static function log(mixed $value, bool $force = false): void
    {
        if ($force || wp_get_environment_type() !== 'production') {
            error_log(stripcslashes(json_encode($value, JSON_PRETTY_PRINT)));
        }
    }

    /**
     * Dump a value in a readable format.
     * It uses Kint if available, otherwise defaults to print_r.
     *
     * @param mixed $d The value to dump.
     * @param bool $return Whether to return the output or echo it.
     * @return string|null
     */
    public static function dump(mixed $d, bool $return = false): ?string
    {
        // Early return if in production environment.
        if (wp_get_environment_type() === 'production') {
            return null;
        }

        // Log the value
        self::log($d);

        // If wp_body_open action has not been triggered, return early.
        if (!did_action('wp_body_open')) {
            return null;
        }

        // Use Kint for dumping if available
        if (class_exists(Kint::class)) {
            Kint::$display_called_from = false;
            Kint::$expanded = true;
            Kint::$return = $return;

            return Kint::dump($d);
        }

        // Fallback to print_r if Kint is not available
        $output = '<pre>' . print_r($d, true) . '</pre>';
        return $return ? $output : print($output);
    }
}
