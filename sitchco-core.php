<?php
/**
 * Plugin Name: Sitchco Core
 */

use Sitchco\Framework\Bootstrap;

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

const SITCHCO_CORE_DIR = __DIR__;
const SITCHCO_CORE_CONFIG_DIR = SITCHCO_CORE_DIR;

const SITCHCO_CORE_TEMPLATES_DIR = SITCHCO_CORE_DIR . '/templates';
const SITCHCO_CORE_FIXTURES_DIR = SITCHCO_CORE_DIR . '/tests/fixtures';

const SITCHCO_CONFIG_FILENAME = 'sitchco.config.php';

add_action('plugins_loaded', static function () {
    new Bootstrap();
});
