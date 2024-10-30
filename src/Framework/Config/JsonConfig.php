<?php

namespace Sitchco\Framework\Config;

use Sitchco\Framework\Core\Registry;

class JsonConfig
{
    private $registry;

    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
        if (wp_get_environment_type() === 'local' && is_admin()) {
            add_action('admin_init', [$this, 'saveModuleReference']);
        }
        add_filter('sitchco/modules/active', [$this, 'getActiveModules']);
        add_action('sitchco/after_save_permalinks', fn() => $this->saveModuleReference(true));
    }

    public function saveModuleReference($force = false): void
    {
        $configPath = get_stylesheet_directory() . '/config/.full-reference.json';
        if ($force || !file_exists($configPath)) {
            wp_mkdir_p(dirname($configPath));
            $modules = $this->registry->getFullList();
            file_put_contents($configPath, json_encode($modules, JSON_PRETTY_PRINT));
        }
    }

    public function getActiveModules($modules): array
    {
        // TODO: Consider implementing two separate lists for modules: 'activatedModules' and 'deactivatedModules'.
        // This approach may seem more complex initially, but it offers the advantage of allowing new default features
        //   introduced by the platform to be seamlessly integrated into existing projects without requiring manual
        //   intervention to enable these features.
        $configPath = get_stylesheet_directory() . '/config/modules.json';
        if (!file_exists($configPath)) {
            wp_mkdir_p(dirname($configPath));
            file_put_contents($configPath, json_encode($modules, JSON_PRETTY_PRINT));
        } else {
            $modules = json_decode(file_get_contents($configPath), true);
        }
        return $modules;
    }
}