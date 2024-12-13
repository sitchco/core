<?php
/**
 * Plugin Name: Sitchco Core
 */

use Sitchco\Framework\Bootstrap;

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

const SITCHCO_CORE_DIR = __DIR__;
const SITCHCO_CORE_SRC_DIR = SITCHCO_CORE_DIR . '/src';

add_action('plugins_loaded', static function () {
    new Bootstrap();
});
