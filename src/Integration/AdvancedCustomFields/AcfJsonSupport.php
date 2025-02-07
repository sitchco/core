<?php

namespace Sitchco\Integration\AdvancedCustomFields;

use Sitchco\Framework\Core\Module;
use Sitchco\Framework\Core\Registry;

/**
 * Class AcfJsonSupport
 *
 * This module automatically registers ACF JSON load paths for active modules and
 * overrides the save paths so that field groups loaded from a module’s acf-json folder
 * are saved back to that same folder.
 *
 * It hooks into:
 *   - 'acf/settings/load_json' to add module-specific load paths.
 *   - 'acf/json/save_paths' to override save paths when a module JSON file exists.
 */
class AcfJsonSupport extends Module
{
    /**
     * @var Registry The registry of active modules.
     */
    protected Registry $registry;

    /**
     * Constructor.
     *
     * @param Registry $registry The registry of active modules.
     */
    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Initialize ACF JSON support.
     *
     * Hooks into:
     *   - 'acf/settings/load_json' to add module-specific load paths.
     *   - 'acf/json/save_paths' to override save paths when a module JSON file exists.
     *
     * @return void
     */
    public function init(): void
    {
        add_filter('acf/settings/load_json', [$this, 'addModuleJsonPaths']);
        add_filter('acf/json/save_paths', [$this, 'setModuleJsonSavePaths'], 10, 2);
    }

    /**
     * Add module-specific acf-json directories to the ACF JSON load paths.
     *
     * @param array $paths Existing ACF JSON load paths.
     * @return array The merged array of unique load paths.
     */
    public function addModuleJsonPaths(array $paths): array
    {
        $modulePaths = $this->getModuleJsonPaths();
        return array_unique(array_merge($paths, $modulePaths));
    }

    /**
     * Override the ACF JSON save paths.
     *
     * If a field group JSON file exists in a module's acf-json folder, this method
     * returns that folder as the sole save path so that updates are saved there.
     *
     * @param array $paths The current array of save paths.
     * @param array $post  The settings for the item being saved (e.g. field group).
     * @return array An array containing the chosen save path, or the original paths if no match is found.
     */
    public function setModuleJsonSavePaths(array $paths, array $post): array
    {
        if (!isset($post['key'])) {
            return $paths;
        }

        // Use the field group key as provided (it should already include the "group_" prefix).
        $fieldGroupKey = sanitize_text_field($post['key']);
        $modulePaths   = $this->getModuleJsonPaths();

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
     *
     * Iterates over all active modules (which may reside in an MU plugin, parent theme,
     * child theme, or elsewhere) and returns an array of unique directories that contain
     * an "acf-json" folder.
     *
     * @return array List of acf-json directories.
     */
    protected function getModuleJsonPaths(): array
    {
        $paths = [];
        $activeModules = $this->registry->getActiveModules();

        foreach ($activeModules as $moduleInstance) {
            try {
                $reflection = new \ReflectionClass($moduleInstance);
            } catch (\ReflectionException $e) {
                continue;
            }

            $moduleDir  = dirname($reflection->getFileName());
            $acfJsonDir = trailingslashit($moduleDir) . 'acf-json';
            if (is_dir($acfJsonDir)) {
                $paths[] = $acfJsonDir;
            }
        }

        return array_unique($paths);
    }
}
