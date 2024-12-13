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
    protected array $modules = [
        Cleanup::class,
        SearchRewrite::class,
        BackgroundEventManager::class,
        Timber::class
    ];

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
        $builder = new ContainerBuilder();
        $builder->addDefinitions(SITCHCO_CORE_SRC_DIR . '/container-config.php');
        $ContainerDefinitionsLoader = new ContainerDefinitionConfigLoader();
        $builder->addDefinitions($ContainerDefinitionsLoader->load());
        $GLOBALS['SitchcoContainer'] = $container = $builder->build();
        $Registry = $container->get(Registry::class);
        $Registry->addModules($this->modules);
        $ModuleLoader = new ModuleConfigLoader();
        $Registry->activateModules($ModuleLoader->load());
    }
}