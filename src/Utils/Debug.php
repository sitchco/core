<?php

namespace Sitchco\Utils;

use Kint\Kint;

class Debug
{
    public static function log($value, $force = false): void
    {
        if ($force || wp_get_environment_type() !== 'production') {
            error_log(stripcslashes(json_encode($value, JSON_PRETTY_PRINT)));
        }
    }

    public static function dump($d, $return = false)
    {
        if (wp_get_environment_type() === 'production') {
            return;
        }
        static::log($d);
        if (! did_action('wp_body_open')) {
            return;
        }
        if (class_exists('Kint\Kint')) {
            Kint::$display_called_from = false;
            Kint::$expanded = true;
            Kint::$return = true;
            if ($return) {
                return Kint::dump($d);
            } else {
                echo Kint::dump($d);
            }
        } else {
            if ($return) {
                return '<pre>' . print_r($d, true) . '</pre>';
            } else {
                echo '<pre>' . print_r($d, true) . '</pre>';
            }
        }
    }
}