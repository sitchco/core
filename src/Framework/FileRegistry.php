<?php

namespace Sitchco\Framework;

use Sitchco\Support\FilePath;
use Sitchco\Utils\ArrayUtil;
use Sitchco\Utils\Cache;
use Sitchco\Utils\Logger;
use Sitchco\Utils\Hooks;

/**
 * Abstract Registry Base Class.
 * Provides common functionality for loading, merging, and caching files from multiple locations.
 *
 * Subclasses must define the following constants:
 * - FILENAME: The file to search for in base paths
 * - PATH_FILTER_HOOK: The filter hook name for adding additional paths
 * - CACHE_KEY: The cache key for storing merged results
 */
abstract class FileRegistry
{
    /** @var array<FilePath>|null Ordered list of base paths to search (null until initialized) */
    protected ?array $basePaths = null;

    /**
     * Parse a file and return its contents.
     * Should throw an exception on failure.
     *
     * @param FilePath $filePath Path to the file to parse
     * @return mixed The parsed file data (will be validated as array by loadFile)
     * @throws \Throwable on parse failure
     */
    abstract protected function parseFile(FilePath $filePath): mixed;

    /**
     * Get the default value to return when data is not found or invalid.
     * Override in subclasses to customize the default.
     *
     * @return array The default value
     */
    protected function getDefaultData(): array
    {
        return [];
    }

    /**
     * Load data from merged files, optionally extracting a specific key.
     *
     * @param string|null $key Optional key to extract from the merged data
     * @param array $default Default value to return if key is not found or data is invalid
     * @return array The merged data, a specific section, or the default value
     */
    public function load(?string $key = null, array $default = []): array
    {
        // If a key is provided, validate it
        if ($key !== null) {
            $key = trim($key);
            if (empty($key)) {
                Logger::warning(sprintf('%s: Invalid key provided to load(). Key: %s', static::class, $key));
                return $default;
            }
        }

        $mergedData = $this->loadAndCacheMergedData();

        // If no data was loaded, return the default (or class-specific default if no key)
        if (!is_array($mergedData)) {
            return $key === null ? $this->getDefaultData() : $default;
        }

        // If no key specified, return all data
        if ($key === null) {
            return $mergedData;
        }

        // Extract specific key
        if (array_key_exists($key, $mergedData)) {
            return is_array($mergedData[$key]) ? $mergedData[$key] : $default;
        }

        return $default;
    }

    /**
     * Load and parse a file from the given FilePath with consistent error handling.
     * Returns the parsed data or null if the file cannot be loaded.
     *
     * @param FilePath $filePath Path to the file to load
     * @return array|null Parsed file data or null on failure
     */
    protected function loadFile(FilePath $filePath): ?array
    {
        try {
            $data = $this->parseFile($filePath);
        } catch (\Throwable $e) {
            Logger::error(
                sprintf(
                    '%s: Failed to load/parse file "%s". Error: %s',
                    static::class,
                    $filePath->value(),
                    $e->getMessage(),
                ),
            );

            return null;
        }

        if (!is_array($data)) {
            Logger::error(sprintf('%s: File did not return a valid array: %s', static::class, $filePath->value()));

            return null;
        }

        return $data;
    }

    /**
     * Optional hook to normalize/transform loaded data before merging.
     * Override in concrete classes if needed.
     *
     * @param array $data The loaded data
     * @return array The normalized data
     */
    protected function normalizeData(array $data): array
    {
        return $data;
    }

    /**
     * Gets the base paths where files are located.
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
     * Clears the cache for this registry.
     */
    public function clearCache(): void
    {
        Cache::forget(static::CACHE_KEY);
    }

    /**
     * Loads, merges, and caches file contents.
     * Handles retrieving the entire merged data from cache or file system.
     * @return array|null The fully merged data array, or null if no files found/loaded.
     */
    protected function loadAndCacheMergedData(): ?array
    {
        return Cache::remember(static::CACHE_KEY, function () {
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
        $additionalPathsRaw = apply_filters(Hooks::name(static::PATH_FILTER_HOOK), []);
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
     * Finds files in base paths, loads/parses them, and merges the results recursively.
     * @return array|null Merged array, empty array if files found but were empty/invalid, null if no files found.
     */
    private function loadAndMergeFiles(): ?array
    {
        if (!isset($this->basePaths)) {
            Logger::error(
                sprintf(
                    'Sitchco Registry Error: Base paths not initialized before loading files for %s.',
                    static::class,
                ),
            );

            return null;
        }

        $dataArrays = [];
        $foundAnyFile = false;

        foreach ($this->basePaths as $basePath) {
            $filePath = $basePath->append(static::FILENAME);
            if ($filePath->isFile() && is_readable($filePath->value())) {
                $foundAnyFile = true;
                $fileData = $this->loadFile($filePath);

                if (is_array($fileData)) {
                    $dataArrays[] = $fileData;
                } else {
                    Logger::warning(
                        sprintf('Sitchco Registry Warning: File did not return valid data: %s', $filePath->value()),
                    );
                }
            }
        }

        if (!$foundAnyFile) {
            return null;
        }

        if (empty($dataArrays)) {
            return [];
        }

        // Normalize each loaded data array
        $dataArrays = array_map([$this, 'normalizeData'], $dataArrays);

        // Merge all data arrays - later entries (child theme) override earlier ones (core)
        return ArrayUtil::mergeRecursiveDistinct(...$dataArrays);
    }
}
