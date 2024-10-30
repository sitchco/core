<?php
/**
 * Plugin Name: Sitchco Core
 */

use Sitchco\Framework\Bootstrap;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
if(class_exists('Timber\Timber')) {
    Timber\Timber::init();
}

add_action( 'plugins_loaded', static function() {
    new Bootstrap();
});
