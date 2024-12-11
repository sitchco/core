<?php

namespace Sitchco\Integration;

use Sitchco\Framework\Core\AbstractModule;

class Timber extends AbstractModule
{
    public const NAME = 'timber';
    public const CATEGORY = 'core';
    public const DEFAULT = true;

    public function __construct()
    {
        add_action('setup_theme', [$this, 'init']);
    }

    public function init(): void
    {
        if (class_exists('Timber\Timber')) {
            \Timber\Timber::init();
        }
    }
}