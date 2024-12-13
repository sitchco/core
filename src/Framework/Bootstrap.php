<?php

namespace Sitchco\Framework;

use Sitchco\Framework\Config\JsonModuleConfigLoader;
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
        add_action('after_setup_theme', function() {
            $Registry = Registry::getInstance();
            $Registry->addModules($this->modules);
            $Loader = new JsonModuleConfigLoader();
            $Registry->activateModules($Loader->getModuleConfigs());
        }, 99);
    }
}