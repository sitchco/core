<?php

namespace Sitchco\Support;

use Sitchco\Framework\Module;

/**
 * Class AcfJsonSupport
 * This module automatically registers ACF JSON load paths for active modules and
 * overrides the save paths so that field groups loaded from a module’s acf-json folder
 * are saved back to that same folder.
 * It hooks into:
 *   - 'acf/settings/load_json' to add module-specific load paths.
 *   - 'acf/json/save_paths' to override save paths when a module JSON file exists.
 */
class AcfJsonSupport
{

    /**
     * @var array<Module>
     */
    protected array $modules;

    /**
     * Initialize ACF JSON support.
     * Hooks into:
     *   - 'acf/settings/load_json' to add module-specific load paths.
     *   - 'acf/json/save_paths' to override save paths when a module JSON file exists.
     * @return void
     */
    public function init(array $modules): void
    {
        $this->modules = $modules;
        add_filter('acf/settings/load_json', [$this, 'addModuleJsonPaths']);
        add_filter('acf/json/save_paths', [$this, 'setModuleJsonSavePaths'], 10, 2);
    }

    /**
     * Add module-specific acf-json directories to the ACF JSON load paths.
     *
     * @param array $paths Existing ACF JSON load paths.
     *
     * @return array The merged array of unique load paths.
     */
    public function addModuleJsonPaths(array $paths): array
    {
        $modulePaths = $this->getModuleJsonPaths();

        return array_unique(array_merge($paths, $modulePaths));
    }

    /**
     * Override the ACF JSON save paths.
     * If a field group JSON file exists in a module's acf-json folder, this method
     * returns that folder as the sole save path so that updates are saved there.
     *
     * @param array $paths The current array of save paths.
     * @param array $post  The settings for the item being saved (e.g. field group).
     *
     * @return array An array containing the chosen save path, or the original paths if no match is found.
     */
    public function setModuleJsonSavePaths(array $paths, array $post): array
    {
        if (! isset($post['key'])) {
            return $paths;
        }

        // Use the field group key as provided (it should already include the "group_" prefix).
        $fieldGroupKey = sanitize_text_field($post['key']);
        $modulePaths = $this->getModuleJsonPaths();

        foreach ($modulePaths as $jsonPath) {
            $possibleFile = trailingslashit($jsonPath) . $fieldGroupKey . '.json';
            if (file_exists($possibleFile)) {
                return [$jsonPath];
            }
        }

        return $paths;
    }

    /**
     * Retrieve unique acf-json directories from active modules.
     * Iterates over all active modules (which may reside in an MU plugin, parent theme,
     * child theme, or elsewhere) and returns an array of unique directories
     * that contain an "acf-json" folder or custom paths defined by the module instance.
     * @return array List of acf-json directories.
     */
    protected function getModuleJsonPaths(): array
    {
        $paths = [];
        foreach ($this->modules as $moduleInstance) {
            $paths = array_merge($paths, $moduleInstance->getAcfJsonPaths());
        }

        return array_unique($paths);
    }
}
