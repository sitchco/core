<?php

namespace Sitchco\Framework\Config;

use Sitchco\Utils\ArrayUtil;

abstract class ConfigLoader
{
    /**
     * Loads and merges config files from path list and passes concrete class to apply
     *
     * @return void
     */
    public function load(): array
    {
        $paths_raw = array_merge(
            [SITCHCO_CORE_CONFIG_DIR],
            apply_filters("sitchco/config_paths/{$this->getHookName()}", []),
            $this->getConfigDirectoryPaths()
        );
        $paths = array_unique(array_map('trailingslashit', array_filter($paths_raw)));
        $configs = array_filter(array_map(function ($path) {
            $file = $path . $this->getConfigFileName();
            return file_exists($file) ? $this->loadFile($file) : false;
        }, $paths));

        $merged_config = ArrayUtil::mergeRecursiveDistinct(...$configs);
        $this->applyLoadedConfig($merged_config);
        return $merged_config;
    }

    /**
     * Concrete classes apply the loaded config data for its specific purpose
     *
     * @param array $config
     * @return void
     */
    abstract protected function applyLoadedConfig(array $config): void;

    /**
     * Concrete classes load the contents of a file based on the implemented file type
     *
     * @param string $file
     * @return array
     */
    abstract protected function loadFile(string $file): array;

    /**
     * Used to generate the filter hook to extend the list of config directory paths
     *
     * @return string
     */
    abstract protected function getHookName(): string;

    /**
     * The filename to load in each config directory path
     *
     * @return string
     */
    abstract protected function getConfigFileName(): string;

    /**
     * Default theme config directories
     *
     * @return array<string> array of module paths.
     */
    protected function getConfigDirectoryPaths(): array
    {
        return [
            get_template_directory() . '/config',
            get_stylesheet_directory() . '/config',
        ];
    }
}