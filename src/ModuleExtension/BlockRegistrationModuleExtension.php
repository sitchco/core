<?php

namespace Sitchco\ModuleExtension;

use Sitchco\Framework\BlockManifestRegistry;
use Sitchco\Framework\Module;
use Sitchco\Support\FilePath;
use Sitchco\Utils\Block;

/**
 * Class BlockRegistrationModuleExtension
 * This extension discovers and registers Gutenberg blocks for modules using a centralized
 * block manifest (sitchco.blocks.json). The manifest is loaded from multiple locations
 * (core, parent theme, child theme) and merged, providing a single source of truth for
 * all block definitions.
 *
 * @package Sitchco\ModuleExtension
 */
class BlockRegistrationModuleExtension implements ModuleExtension
{
    /**
     * @var Module[]
     */
    protected array $modules;

    /**
     * @var FilePath[]
     */
    protected array $moduleBlocksPaths = [];

    /**
     * Constructor.
     *
     * @param BlockManifestRegistry $manifestRegistry
     */
    public function __construct(private BlockManifestRegistry $manifestRegistry) {}

    /**
     * Extend the modules by registering their Gutenberg blocks.
     *
     * @param Module[] $modules
     *
     * @return void
     */
    public function extend(array $modules): void
    {
        $this->modules = $modules;
        add_action('init', [$this, 'init']);
        add_filter('timber/locations', [$this, 'addModuleBlocksPaths']);
    }

    public function init(): void
    {
        // Load the merged block manifest
        $manifest = $this->manifestRegistry->load();
        $manifestBlocks = $manifest['blocks'] ?? [];

        // Get base paths to resolve relative paths in manifest
        $basePaths = $this->manifestRegistry->getBasePaths();

        foreach ($this->modules as $module) {
            $blocksPath = $module->blocksPath();

            // Skip modules without a blocks folder.
            if (!$blocksPath->isDir()) {
                continue;
            }
            $this->moduleBlocksPaths[] = $blocksPath;

            // Find blocks that belong to this module
            $moduleBlocks = [];
            $blocksPathStr = $blocksPath->value();

            foreach ($manifestBlocks as $blockName => $relativePath) {
                // Try to resolve the relative path against each base path
                foreach ($basePaths as $basePath) {
                    $fullPath = $basePath->append($relativePath)->value();

                    // Check if this block path belongs to the current module
                    if (str_starts_with($fullPath, $blocksPathStr)) {
                        // Extract the relative directory within the module's blocks folder
                        $relativeDir = substr($fullPath, strlen($blocksPathStr));
                        $moduleBlocks[$blockName] = $relativeDir;
                        break; // Found the base path for this block, move to next block
                    }
                }
            }

            // Only process modules that have blocks
            if (empty($moduleBlocks)) {
                continue;
            }

            $module->filterBlockAssets($moduleBlocks);

            // Register each block using register_block_type which accepts a directory containing block.json.
            foreach ($moduleBlocks as $blockName => $relativeDir) {
                // Rebuild the full path using the base blocks directory and the relative directory.
                $fullPath = $blocksPath->append($relativeDir)->value();
                register_block_type($fullPath);
            }
        }
    }

    public function addModuleBlocksPaths(array $paths): array
    {
        foreach ($this->moduleBlocksPaths as $blocksPath) {
            $relative = Block::relativeBlockPath($blocksPath);
            if (empty($relative)) {
                continue;
            }
            $paths[] = array_filter(
                [
                    // child theme override for block template path
                    get_stylesheet_directory() . '/modules/' . $relative,
                    // default block template path
                    $blocksPath->value(),
                ],
                'is_dir',
            );
        }
        return $paths;
    }
}
