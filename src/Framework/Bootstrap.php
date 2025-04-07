<?php

namespace Sitchco\Framework;

use DI\ContainerBuilder;
use DI\DependencyException;
use DI\NotFoundException;
use Exception;
use Sitchco\Framework\Config\ContainerDefinitionConfigLoader;
use Sitchco\Framework\Config\ModuleConfigLoader;
use Sitchco\Utils\Hooks;
use Timber\Loader;

class Bootstrap
{

    public function __construct()
    {
        add_action('after_setup_theme', [$this, 'initialize'], 5);

        if (wp_get_environment_type() === 'local') {
            add_filter('timber/cache/mode', fn() => Loader::CACHE_NONE);
        }

        if (defined('WP_TESTS_CONFIG_FILE_PATH')) {

            add_filter(Hooks::name('config_paths', 'modules'), function(array $paths) {
                $paths[] = SITCHCO_CORE_FIXTURES_DIR;
                return $paths;
            });
        }
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