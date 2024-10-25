<?php

namespace Sitchco\Framework\Core;

use Sitchco\Framework\Adapters\FilesystemAdapter;
use Sitchco\Integration\Wordpress\Cleanup;

class Bootstrap
{
    public function __construct()
    {
        $this->initializeCore();
        $this->initializeIntegrations();
    }

    protected function initializeCore()
    {
        $registry = new Registry();
        new FilesystemAdapter($registry);
    }

    protected function initializeIntegrations()
    {
        // Initialize integrations (WP Rocket, Yoast, etc.)
        Cleanup::init();
    }
}