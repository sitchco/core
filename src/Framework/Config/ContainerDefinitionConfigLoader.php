<?php

namespace Sitchco\Framework\Config;

use Sitchco\Utils\ArrayUtil;

/**
 * Class ContainerConfigLoader
 * Loads
 * Integrates with the Registry to manage module activation and paths.
 * @package Sitchco\Framework\Config
 */
class ContainerDefinitionConfigLoader implements ConfigLoader
{

    public function load(): array
    {
        $paths_raw = array_merge(apply_filters('sitchco/container_definition_paths', []), $this->getContainerDefinitionPaths());
        $paths = array_unique(array_map('trailingslashit', array_filter($paths_raw)));
        $configs = array_filter(array_map(function ($path) {
            $file = $path . 'container.php';
            return file_exists($file) ? include_once($file) : false;
        }, $paths));

        return ArrayUtil::mergeRecursiveDistinct(...$configs);
    }

    /**
     *  Default config directories
     *
     * @return array<string> array of container definitions paths.
     */
    protected function getContainerDefinitionPaths(): array
    {
        return [
            get_template_directory() . '/config',
            get_stylesheet_directory() . '/config',
        ];
    }
}