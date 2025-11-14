<?php

namespace Sitchco\Framework;

use DI\ContainerBuilder;
use DI\DependencyException;
use DI\NotFoundException;
use Exception;
use Sitchco\Tests\Fakes\TestFileRegistry;
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

        if (defined('WP_TESTS_CONFIG_FILE_PATH') || getenv('WP_PHPUNIT__DIR')) {
            add_filter(
                Hooks::name(ConfigRegistry::PATH_FILTER_HOOK),
                fn($paths) => [...$paths, SITCHCO_CORE_TESTS_DIR],
            );
            add_filter(
                Hooks::name(BlockManifestRegistry::PATH_FILTER_HOOK),
                fn($paths) => [...$paths, SITCHCO_CORE_TESTS_DIR],
            );
            // Add fixtures directory for TestFileRegistry
            add_filter(
                Hooks::name(TestFileRegistry::PATH_FILTER_HOOK),
                fn($paths) => [...$paths, SITCHCO_CORE_FIXTURES_DIR],
            );
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
        $containerDefinitions = $configRegistry->load('container');
        if (!empty($containerDefinitions)) {
            $builder->addDefinitions($containerDefinitions);
        }
        $GLOBALS['SitchcoContainer'] = $container = $builder->build();
        $container->set(ConfigRegistry::class, $configRegistry);

        $blockManifestRegistry = $container->get(BlockManifestRegistry::class);
        $blockManifestRegistry->ensureFreshManifests();

        $moduleConfigs = $configRegistry->load('modules');
        $registry = $container->get(ModuleRegistry::class);
        $registry->activateModules($moduleConfigs);
    }
}
