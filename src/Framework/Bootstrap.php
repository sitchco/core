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
        add_action('init', [$this, 'setupAssets'], 5);
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
        $containerDefinitions = $configRegistry->load('container');
        if (!empty($containerDefinitions)) {
            $builder->addDefinitions($containerDefinitions);
        }
        $GLOBALS['SitchcoContainer'] = $container = $builder->build();
        $container->set(ConfigRegistry::class, $configRegistry);
        $moduleConfigs = $configRegistry->load('modules');
        $registry = $container->get(ModuleRegistry::class);
        $registry->activateModules($moduleConfigs);
    }

    public function setupAssets(): void
    {
        wp_register_script(
            'sitchco/core',
            SITCHCO_CORE_ASSETS_DIR . '/scripts/main.mjs',
            ['wp-hooks']
        );
        add_filter('body_class', fn($classes) => array_merge($classes, ['sd-app-loading']));
        add_action('wp_head', function () {
?>
        <script>
            window.onload = function() {
                document.body.classList.remove('sd-app-loading');
            }
        </script>
        <noscript><style>body.sd-app-loading { opacity: 1; } </style></noscript>
<?php
        });
    }

}
