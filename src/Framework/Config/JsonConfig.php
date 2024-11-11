<?php

namespace Sitchco\Framework\Config;

use Sitchco\Framework\Core\Registry;
use Sitchco\Utils\ArrayUtil;

class JsonConfig
{
    private $registry;

    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
        if (wp_get_environment_type() === 'local' && is_admin()) {
            add_action('admin_init', [$this, 'saveModuleReference']);
        }
        add_filter('sitchco/modules/activate', [$this, 'getActiveModules'], 5, 2);
        add_action('sitchco/after_save_permalinks', fn() => $this->saveModuleReference(true));
        add_filter('sitchco/module_paths', [$this, 'setModulePaths'], 50);
    }

    public function saveModuleReference($force = false): void
    {
        $uploadDir = wp_upload_dir();
        $configPath = $uploadDir['basedir'] . '/sitchco/module-list.json';
        if ($force || ! file_exists($configPath)) {
            wp_mkdir_p(dirname($configPath));
            $modules = $this->registry->getFullList();
            file_put_contents($configPath, json_encode($modules, JSON_PRETTY_PRINT));
        }
    }

    public function getActiveModules($modules, $fullList): array
    {
        $paths = array_unique(array_map('trailingslashit', array_filter(apply_filters('sitchco/module_paths', []))));
        $configs = array_filter(array_map(function ($path) {
            $file = $path . 'modules.json';

            return file_exists($file) ? json_decode(file_get_contents($file), true) : false;
        }, $paths));
        return ArrayUtil::mergeRecursiveDistinct($modules, ...$configs);
    }

    public function setModulePaths($paths): array
    {
        return array_merge($paths, [
            get_template_directory() . '/config',
            get_stylesheet_directory() . '/config',
        ]);
    }
}