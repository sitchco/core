<?php

namespace Sitchco\Framework;

use DI\ContainerBuilder;
use DI\DependencyException;
use DI\NotFoundException;
use Exception;
use Sitchco\Framework\Core\ConfigRegistry;
use Sitchco\Framework\Core\ModuleRegistry;
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
            add_filter(Hooks::name(ConfigRegistry::PATH_FILTER_HOOK), function (array $paths) {
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
        $configRegistry = new ConfigRegistry();
        $builder = new ContainerBuilder();
        $containerDefinitions = $configRegistry->loadPhpConfig('container');
        if (!empty($containerDefinitions)) {
            $builder->addDefinitions($containerDefinitions);
        }
        $GLOBALS['SitchcoContainer'] = $container = $builder->build();
        $container->set(ConfigRegistry::class, $configRegistry);
        $moduleConfigs = $configRegistry->loadPhpConfig('modules');
        $registry = $container->get(ModuleRegistry::class);
        $registry->activateModules($moduleConfigs);
    }
}
