<?php

namespace Sitchco\Framework\Adapters;

use Sitchco\Framework\Core\Registry;

class FilesystemAdapter
{
    private $registry;

    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
        if (wp_get_environment_type() === 'local' && is_admin()) {
            add_action('admin_init', [$this, 'saveModuleReference']);
        }
        add_filter('sitchco/modules/active', [$this, 'activate']);
        // TODO: hook into save permalinks and force regeneration of full reference.
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

    public function activate($modules): array
    {
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