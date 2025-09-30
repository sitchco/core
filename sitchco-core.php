<?php
/**
 * Plugin Name: Sitchco Core
 * Plugin URI: https://sitch.co
 * Description: Core framework for Sitchco WordPress projects.
 * Version: 0.0.1
 * Author: Situation
 * Author URI: https://sitch.co
 */

use Sitchco\Framework\Bootstrap;

if (!defined('ABSPATH')) {
    exit(); // Exit if accessed directly.
}

const SITCHCO_CORE_VERSION = '0.0.1';
const SITCHCO_CORE_DIR = __DIR__;
const SITCHCO_CORE_CONFIG_DIR = SITCHCO_CORE_DIR;

const SITCHCO_CORE_TEMPLATES_DIR = SITCHCO_CORE_DIR . '/templates';

const SITCHCO_CORE_TESTS_DIR = SITCHCO_CORE_DIR . '/tests';
const SITCHCO_CORE_FIXTURES_DIR = SITCHCO_CORE_TESTS_DIR . '/fixtures';
const SITCHCO_CORE_ASSETS_DIR = SITCHCO_CORE_DIR . '/assets';

const SITCHCO_CONFIG_FILENAME = 'sitchco.config.php';
const SITCHCO_DEV_HOT_FILE = '.vite.hot';

add_action('plugins_loaded', static function () {
    new Bootstrap();
});
