<?php

namespace Sitchco\Modules;

use Sitchco\Framework\Module;

/**
 * class Timber
 * @package Sitchco\Integration
 */
class Timber extends Module
{
    public function init(): void
    {
        if (class_exists('Timber\Timber')) {
            \Timber\Timber::init();
        }
        add_filter('timber/locations', function ($paths) {
            $paths[] = [SITCHCO_CORE_TEMPLATES_DIR];

            return $paths;
        });
    }
}
