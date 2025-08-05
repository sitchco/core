<?php

namespace Sitchco\ModuleExtension;

use Sitchco\Framework\Module;
use Sitchco\Support\FilePath;
use Sitchco\Utils\Acf;

class AcfPathsModuleExtension implements ModuleExtension
{
    /**
     * @var Module[]
     */
    protected array $modules;

    /**
     * Initialize ACF JSON support.
     * Hooks into:
     *   - 'acf/settings/load_json' to add module-specific load paths.
     *   - 'acf/json/save_paths' to override save paths when a module JSON file exists.
     * @param array $modules
     * @return void
     */
    public function extend(array $modules): void
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
        if (!isset($post['key'])) {
            return $paths;
        }

        $modulePaths = $this->getModuleJsonPaths();
        // Use the field group key as provided (it should already include the "group_" prefix).
        $foundJsonPath = Acf::findJsonFile($modulePaths, $post['key']);

        return $foundJsonPath ? [$foundJsonPath] : $paths;
    }

    /**
     * Retrieve unique acf-json directories from active modules.
     * Iterates over all active modules (which may reside in an MU plugin, parent theme,
     * child theme, or elsewhere) and returns an array of unique directories
     * that contain an "acf-json" folder or custom paths defined by the module instance.
     * @return FilePath[] List of acf-json directories.
     */
    protected function getModuleJsonPaths(): array
    {
        $filteredModules = Acf::findModulesWithJsonPath($this->modules);
        return array_map(fn($m) => $m->path('acf-json'), $filteredModules);
    }
}
