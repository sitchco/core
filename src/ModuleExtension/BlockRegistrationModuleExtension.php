<?php

namespace Sitchco\ModuleExtension;

use Sitchco\Framework\Core\Module;

/**
 * Class BlockRegistrationModuleExtension
 * This extension checks for a blocks folder in each module. If a blocks-config.php file exists,
 * it loads it; otherwise it globs the folders (verifying that each contains a block.json file),
 * generates a configuration array mapping block names to their directory names (relative to the blocks folder),
 * writes it to blocks-config.php for future use, and then registers the blocks using register_block_type.
 * This provides a hybrid approach that automates block discovery during development while
 * ensuring performance in production.
 *
 * @package Sitchco\ModuleExtension
 */
class BlockRegistrationModuleExtension implements ModuleExtension
{
    protected array $modules;

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
    }

    public function init(): void
    {
        foreach ($this->modules as $module) {
            // Ensure the module provides its base path.
            if (!method_exists($module, 'getModuleBasePath')) {
                continue;
            }

            $basePath = $module->getModuleBasePath();
            $blocksDir = $basePath . 'blocks/';

            // Skip modules without a blocks folder.
            if (!is_dir($blocksDir)) {
                continue;
            }

            $configFile = $blocksDir . 'blocks-config.php';
            if (file_exists($configFile)) {
                // Load the previously generated configuration mapping.
                $blocksConfig = include $configFile;
            } else {
                // Otherwise, glob the blocks folder and build the configuration mapping.
                $blocksConfig = [];
                $directories = glob($blocksDir . '*', GLOB_ONLYDIR);
                if ($directories !== false) {
                    foreach ($directories as $dir) {
                        $blockJsonPath = trailingslashit($dir) . 'block.json';
                        if (file_exists($blockJsonPath)) {
                            // Use the directory name as the block identifier.
                            $blockName = basename($dir);
                            // Store only the relative directory name.
                            $blocksConfig[$blockName] = $blockName;
                        }
                    }
                }

                // Write the configuration array to a PHP file for future use.
                $export = var_export($blocksConfig, true);
                $phpContent = "<?php\n\nreturn " . $export . ";\n";
                file_put_contents($configFile, $phpContent);
            }

            // Register each block using register_block_type which accepts a directory containing block.json.
            foreach ($blocksConfig as $blockName => $relativeDir) {
                // Rebuild the full path using the base blocks directory and the relative directory.
                $fullPath = $blocksDir . $relativeDir;
                register_block_type($fullPath);
            }
        }
    }
}
