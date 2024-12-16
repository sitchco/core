<?php

namespace Sitchco\Framework;

use DI\ContainerBuilder;
use DI\DependencyException;
use DI\NotFoundException;
use Exception;
use Sitchco\Framework\Config\ContainerDefinitionConfigLoader;
use Sitchco\Framework\Config\ModuleConfigLoader;
use Sitchco\Framework\Core\Registry;
use Sitchco\Integration\BackgroundEventManager;
use Sitchco\Integration\Timber;
use Sitchco\Integration\Wordpress\Cleanup;
use Sitchco\Integration\Wordpress\SearchRewrite;

class Bootstrap
{

    public function __construct()
    {
        add_action('after_setup_theme', [$this, 'initialize'], 99);
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     * @throws Exception
     */
    public function initialize(): void
    {
        $Builder = new ContainerBuilder();
        $ContainerDefinitionLoader = new ContainerDefinitionConfigLoader($Builder);
        $ContainerDefinitionLoader->load();
        $GLOBALS['SitchcoContainer'] = $container = $Builder->build();
        $container->get(ModuleConfigLoader::class)->load();
    }
}