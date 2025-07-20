<?php

namespace Sitchco\ModuleExtension;

use Sitchco\Framework\Module;
use Sitchco\Support\FilePath;

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
    /**
     * @var Module[]
     */
    protected array $modules;

    /**
     * @var FilePath[]
     */
    protected array $moduleBlocksPaths = [];

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
        foreach ($this->modules as $module) {
            $blocksPath = $module->path('blocks');

            // Skip modules without a blocks folder.
            if (!$blocksPath->isDir()) {
                continue;
            }
            $this->moduleBlocksPaths[] = $blocksPath;

            $configFilePath = $blocksPath->append('blocks-config.php');
            if ($configFilePath->isFile()) {
                // Load the previously generated configuration mapping.
                $blocksConfig = include $configFilePath;
            } else {
                // Otherwise, glob the blocks folder and build the configuration mapping.
                $blocksConfig = [];
                $directories = glob($blocksPath . '*', GLOB_ONLYDIR);
                if ($directories !== false) {
                    foreach ($directories as $dir) {
                        $blockJsonPath = (new FilePath($dir))->append('block.json');
                        if ($blockJsonPath->isFile()) {
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
                file_put_contents($configFilePath, $phpContent);
            }

            // Register each block using register_block_type which accepts a directory containing block.json.
            foreach ($blocksConfig as $blockName => $relativeDir) {
                // Rebuild the full path using the base blocks directory and the relative directory.
                $fullPath = $blocksPath->append($relativeDir)->value();
                register_block_type($fullPath);
            }
        }
    }

    public function addModuleBlocksPaths(array $paths): array
    {
        foreach ($this->moduleBlocksPaths as $blocksPath) {
            $pathParts = explode('/modules/', $blocksPath->value());
            $paths[] = [
                // child theme override for block template path
                get_stylesheet_directory() . '/modules/' . $pathParts[1],
                // default block template path
                $blocksPath->value()
            ];
            $paths[] = [];
        }
        return $paths;
    }
}
