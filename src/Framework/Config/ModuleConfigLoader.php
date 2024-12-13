<?php

namespace Sitchco\Framework\Config;

use Sitchco\Framework\Core\Registry;
use Sitchco\Utils\ArrayUtil;

/**
 * Class JsonModuleConfigLoader
 * Loads the configuration of modules using JSON files.
 * Integrates with the Registry to manage module activation and paths.
 * @package Sitchco\Framework\Config
 */
class ModuleConfigLoader implements ConfigLoader
{

    public function __construct()
    {
    }

    /**
     * Retrieves the active modules by merging configurations from multiple JSON files.
     * It collects module configurations from specified paths and merges them with the existing modules
     *
     * @return array<string, array<string, bool>|bool> The merged list of active modules.
     */
    public function load(): array
    {
        $paths_raw = array_merge(apply_filters('sitchco/module_paths', []), $this->getModulePaths());
        $paths = array_unique(array_map('trailingslashit', array_filter($paths_raw)));
        $configs = array_filter(array_map(function ($path) {
            $file = $path . 'modules.json';
            return file_exists($file) ? json_decode(file_get_contents($file), true) : false;
        }, $paths));

        return ArrayUtil::mergeRecursiveDistinct(...$configs);
    }

    /**
     *  Default config directories
     *
     * @return array<string> array of module paths.
     */
    protected function getModulePaths(): array
    {
        return [
            get_template_directory() . '/config',
            get_stylesheet_directory() . '/config',
        ];
    }
}