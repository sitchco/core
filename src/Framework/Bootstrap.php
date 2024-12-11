<?php

namespace Sitchco\Framework;

use Sitchco\Framework\Config\JsonConfig;
use Sitchco\Framework\Core\Registry;
use Sitchco\Integration\BackgroundEventManager;
use Sitchco\Integration\Timber;
use Sitchco\Integration\Wordpress\Cleanup;
use Sitchco\Integration\Wordpress\SearchRewrite;

class Bootstrap
{
    protected array $modules = [
        Cleanup::class,
        SearchRewrite::class,
        BackgroundEventManager::class,
        Timber::class
    ];

    public function __construct()
    {
        new JsonConfig(Registry::add($this->modules));
    }
}