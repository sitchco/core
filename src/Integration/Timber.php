<?php

namespace Sitchco\Integration;

use Sitchco\Framework\Core\Module;

class Timber extends Module
{
    public function __construct()
    {
        if (class_exists('Timber\Timber')) {
            \Timber\Timber::init();
        }
    }

}