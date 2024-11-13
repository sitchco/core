<?php

namespace Sitchco\Framework\Config;

use Sitchco\Framework\Core\Registry;
use Sitchco\Utils\ArrayUtil;

/**
 * Class JsonConfig
 * Handles the configuration of modules using JSON files.
 * Integrates with the Registry to manage module activation and paths.
 * @package Sitchco\Framework\Config
 */
class JsonConfig
{
    /**
     * @var Registry Instance of the Registry for managing modules.
     */
    private $registry;

    /**
     * JsonConfig constructor.
     * Initializes the JsonConfig by setting up necessary hooks and filters.
     *
     * @param Registry $registry The Registry instance for managing modules.
     */
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

    /**
     * Saves the module reference to a JSON configuration file.
     * Creates or updates the 'module-list.json' file in the uploads directory.
     * This file contains the list of all registered modules and their features.
     *
     * @param bool $force Whether to force saving the module reference even if the file exists.
     *
     * @return void
     */
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

    /**
     * Retrieves the active modules by merging configurations from multiple JSON files.
     * This method is hooked into the 'sitchco/modules/activate' filter.
     * It collects module configurations from specified paths and merges them with the existing modules.
     *
     * @param array<string, array<string, bool>|bool> $modules  The initially active modules.
     * @param array<string, array<string, bool>|bool> $fullList The full list of registered modules.
     *
     * @return array<string, array<string, bool>|bool> The merged list of active modules.
     */
    public function getActiveModules($modules, $fullList): array
    {
        $paths = array_unique(array_map('trailingslashit', array_filter(apply_filters('sitchco/module_paths', []))));
        $configs = array_filter(array_map(function ($path) {
            $file = $path . 'modules.json';

            return file_exists($file) ? json_decode(file_get_contents($file), true) : false;
        }, $paths));

        return ArrayUtil::mergeRecursiveDistinct($modules, ...$configs);
    }

    /**
     * Sets the module paths by merging default config directories with existing paths.
     * This method is hooked into the 'sitchco/module_paths' filter.
     * It ensures that the configuration directories within the parent and child theme are included.
     *
     * @param array<string> $paths The existing array of module paths.
     *
     * @return array<string> The updated array of module paths.
     */
    public function setModulePaths($paths): array
    {
        return array_merge($paths, [
            get_template_directory() . '/config',
            get_stylesheet_directory() . '/config',
        ]);
    }
}