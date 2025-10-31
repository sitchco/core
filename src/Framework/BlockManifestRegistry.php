<?php

namespace Sitchco\Framework;

use Sitchco\Support\FilePath;
use Sitchco\Utils\ArrayUtil;
use Sitchco\Utils\Cache;
use Sitchco\Utils\Hooks;

/**
 * Block Manifest Registry Service.
 * Loads block manifest files (sitchco.blocks.json) from defined locations
 * (Core plugin, additional filtered paths, parent theme, child theme),
 * merges their contents recursively, caches the result, and provides access
 * to the merged block definitions.
 */
class BlockManifestRegistry
{
    /** @var array<FilePath>|null Ordered list of base paths to search (null until initialized) */
    public readonly ?array $basePaths;

    /** @var string Current WP environment type */
    private string $environmentType;

    /** @var string Filter hook for adding additional manifest paths */
    public const PATH_FILTER_HOOK = 'blocks_manifest_paths';

    /** @var string Filename for block manifest */
    public const MANIFEST_FILENAME = 'sitchco.blocks.json';

    /** @var string Version for manifest files */
    public const MANIFEST_VERSION = '1.0';

    /** @var string Cache key for merged manifest */
    private const CACHE_KEY = 'sitchco_blocks_manifest';

    /**
     * Constructor.
     *
     * @param BlockManifestGenerator $generator Generator for creating manifests
     * @param string|null $environmentType Override environment type (mainly for testing). Defaults to
     *                                     wp_get_environment_type().
     */
    public function __construct(private BlockManifestGenerator $generator, ?string $environmentType = null)
    {
        $this->environmentType = $environmentType ?? wp_get_environment_type();
    }

    /**
     * Retrieves the merged block manifest.
     * Loads and merges `sitchco.blocks.json` from all registered locations,
     * caches the result, and returns the complete merged manifest array.
     *
     * @return array The merged block manifest with structure:
     *               [
     *                 'version' => '1.0',
     *                 'blocks' => [
     *                   'block-name' => 'relative/path/to/block',
     *                   ...
     *                 ]
     *               ]
     */
    public function load(): array
    {
        $mergedManifest = $this->loadAndCacheMergedManifest();

        return is_array($mergedManifest) ? $mergedManifest : ['version' => self::MANIFEST_VERSION, 'blocks' => []];
    }

    /**
     * Gets the base paths where manifests are located.
     * Useful for generators that need to know where to write manifests.
     *
     * @return FilePath[] List of base directory paths as FilePath objects
     */
    public function getBasePaths(): array
    {
        if (!isset($this->basePaths)) {
            $this->initializeBasePaths();
        }

        return $this->basePaths ?? [];
    }

    /**
     * Clears the manifest cache.
     * Useful when manifests are regenerated.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Ensures block manifests are fresh by regenerating them if stale.
     * Only operates in 'local' environment - in other environments this is a no-op.
     * Checks each base path's manifest for staleness and regenerates if needed.
     *
     * @return void
     */
    public function ensureFreshManifests(): void
    {
        // Only regenerate in local environment
        if ($this->environmentType !== 'local') {
            return;
        }

        $basePaths = $this->getBasePaths();
        $this->generator->ensureFreshManifests($basePaths);
        $this->clearCache();
    }

    /**
     * Loads, merges, and caches the manifest file contents.
     * Handles retrieving the entire merged manifest from cache or file system.
     * @return array|null The fully merged manifest array, or null if no files found/loaded.
     */
    private function loadAndCacheMergedManifest(): ?array
    {
        return Cache::remember(self::CACHE_KEY, function () {
            if (!isset($this->basePaths)) {
                $this->initializeBasePaths();
            }

            return $this->loadAndMergeFiles();
        });
    }

    /**
     * Initializes the base paths using the Core Constant, Filter Hook, and Theme Directories.
     * Order: Core -> Filtered -> Parent Theme -> Child Theme.
     */
    private function initializeBasePaths(): void
    {
        $potentialPaths = [];
        if (defined('SITCHCO_CORE_CONFIG_DIR')) {
            $potentialPaths[] = SITCHCO_CORE_CONFIG_DIR;
        }
        $additionalPathsRaw = apply_filters(Hooks::name(self::PATH_FILTER_HOOK), []);
        if (is_array($additionalPathsRaw)) {
            $potentialPaths = array_merge($potentialPaths, $additionalPathsRaw);
        }
        $potentialPaths = array_merge($potentialPaths, [get_template_directory(), get_stylesheet_directory()]);

        // Filter to valid string paths
        $validPaths = array_filter($potentialPaths, fn($path) => is_string($path) && !empty($path));

        // Convert to FilePath objects and filter to existing directories only
        $filePathObjects = array_map(fn($path) => FilePath::create($path), $validPaths);
        $existingDirs = array_filter($filePathObjects, fn(FilePath $fp) => $fp->isDir());

        // Remove duplicates by comparing normalized paths
        $uniquePaths = [];
        $seen = [];
        foreach ($existingDirs as $filePath) {
            $normalized = $filePath->value();
            if (!in_array($normalized, $seen, true)) {
                $uniquePaths[] = $filePath;
                $seen[] = $normalized;
            }
        }

        $this->basePaths = array_values($uniquePaths);
    }

    /**
     * Finds sitchco.blocks.json in base paths, loads/parses them, and merges the results recursively.
     * @return array|null Merged array, empty array if files found but were empty/invalid, null if no files found.
     */
    private function loadAndMergeFiles(): ?array
    {
        if (!isset($this->basePaths)) {
            error_log('Sitchco Blocks Error: Base paths not initialized before loading files.');

            return null;
        }

        $manifests = [];
        $foundAnyFile = false;

        foreach ($this->basePaths as $basePath) {
            $manifestFile = $basePath->append(self::MANIFEST_FILENAME);
            if ($manifestFile->isFile() && is_readable($manifestFile->value())) {
                $foundAnyFile = true;
                $manifestData = null;
                try {
                    $jsonContent = file_get_contents($manifestFile->value());
                    if ($jsonContent === false) {
                        throw new \Exception('Failed to read file');
                    }
                    $manifestData = json_decode($jsonContent, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new \Exception('JSON decode error: ' . json_last_error_msg());
                    }
                } catch (\Throwable $e) {
                    error_log(
                        sprintf(
                            'Sitchco Blocks Error: Failed to load/parse JSON manifest file "%s". Error: %s',
                            $manifestFile->value(),
                            $e->getMessage(),
                        ),
                    );

                    continue;
                }

                if (is_array($manifestData)) {
                    $manifests[] = $manifestData;
                } else {
                    error_log(
                        sprintf(
                            'Sitchco Blocks Warning: Manifest file did not contain valid JSON array: %s',
                            $manifestFile->value(),
                        ),
                    );
                }
            }
        }

        if (!$foundAnyFile) {
            return null;
        }

        if (empty($manifests)) {
            return [];
        }

        // Merge manifests - later manifests (child theme) override earlier ones (core)
        return ArrayUtil::mergeRecursiveDistinct(...$manifests);
    }
}
