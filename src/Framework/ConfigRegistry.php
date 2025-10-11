<?php

namespace Sitchco\Framework;

use Sitchco\Utils\ArrayUtil;
use Sitchco\Utils\Hooks;

/**
 * Configuration Registry Service.
 * Loads a single configuration file (sitchco.config.php) from defined locations
 * (Core defined path, additional filtered paths, parent theme, child theme),
 * merges their contents recursively, caches the result, and provides access
 * to specific configuration sections (top-level keys) within the merged array.
 * Caching is disabled in 'local' environment, enabled via transient otherwise.
 */
class ConfigRegistry
{
    /** @var array|null Cache for the fully merged config within the current request */
    private ?array $requestCache = null;

    /** @var array<string>|null Ordered list of base paths to search (null until initialized) */
    public readonly ?array $basePaths;

    /** @var string Current WP environment type */
    private string $environmentType;

    /** @var bool Whether persistent object caching is enabled for this instance */
    private bool $objectCacheEnabled;

    /** @var int Default TTL for cached items in seconds (e.g., 1 day) */
    private const CACHE_TTL = DAY_IN_SECONDS;

    /** @var string Group name for WP Object Cache */
    private const CACHE_GROUP = 'sitchco_cfg_group';

    /** @var string Cache key for the merged configuration */
    private const CACHE_KEY = 'sitchco_config';

    /** @var string Filter hook for adding additional config paths */
    public const PATH_FILTER_HOOK = 'config_paths';

    /**
     * Constructor.
     *
     * @param string|null $environmentType Override environment type (mainly for testing). Defaults to
     *                                     wp_get_environment_type().
     */
    public function __construct(?string $environmentType = null)
    {
        $this->environmentType = $environmentType ?? wp_get_environment_type();
        $this->objectCacheEnabled = $this->environmentType !== 'local';
    }

    /**
     * Retrieves a specific configuration section (top-level key) from the merged configuration file.
     * Loads and merges `sitchco.config.php` from all registered locations,
     * caches the result, and returns the array associated with the specified key.
     *
     * @param string $configName The top-level key in the configuration array (e.g., 'modules', 'container').
     *                           This used to be the filename base, now it's the array key.
     * @param array  $default    Default value to return if the key is not found in the merged config.
     *
     * @return array The requested configuration array section, or the default value.
     */
    public function load(string $configName, array $default = []): array
    {
        $configName = trim($configName);
        if (empty($configName)) {
            error_log("Sitchco Config Error: Invalid configName provided to load(). Name: {$configName}");

            return $default;
        }

        $mergedConfig = $this->loadAndCacheMergedConfig();

        if (is_array($mergedConfig) && array_key_exists($configName, $mergedConfig)) {
            return is_array($mergedConfig[$configName]) ? $mergedConfig[$configName] : $default;
        }

        return $default;
    }

    /**
     * Loads, merges, and caches the configuration file contents.
     * Handles retrieving the *entire* merged configuration from cache or file system.
     * @return array|null The fully merged configuration array, or null if no files found/loaded.
     */
    private function loadAndCacheMergedConfig(): ?array
    {
        if (is_array($this->requestCache)) {
            return $this->requestCache;
        }

        $cachedValue = false;
        if ($this->objectCacheEnabled) {
            $cachedValue = wp_cache_get(self::CACHE_KEY, self::CACHE_GROUP);
        }
        if (is_array($cachedValue)) {
            $this->requestCache = $cachedValue;

            return $cachedValue;
        }

        if (!isset($this->basePaths)) {
            $this->initializeBasePaths();
        }

        $mergedConfig = $this->loadAndMergeFiles();

        if (is_array($mergedConfig) && $this->objectCacheEnabled) {
            wp_cache_set(self::CACHE_KEY, $mergedConfig, self::CACHE_GROUP, self::CACHE_TTL);
        }

        $this->requestCache = $mergedConfig;

        return $this->requestCache;
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

        $this->basePaths = array_values(
            // Re-index
            array_filter(
                // only directories
                array_unique(
                    // Remove duplicates
                    array_map(
                        // Add trailing slashes
                        'trailingslashit',
                        array_filter($potentialPaths, fn($path) => is_string($path) && !empty($path)),
                    ),
                ),
                'is_dir',
            ),
        );
    }

    /**
     * Finds sitchco.config.php in base paths, loads/parses them, and merges the results recursively.
     * @return array|null Merged array, empty array if files found but were empty/invalid, null if no files found.
     */
    private function loadAndMergeFiles(): ?array
    {
        if (!isset($this->basePaths)) {
            error_log('Sitchco Config Error: Base paths not initialized before loading files.');

            return null;
        }

        $configs = [];
        $foundAnyFile = false;

        foreach ($this->basePaths as $path) {
            $filePath = $path . SITCHCO_CONFIG_FILENAME;
            if (file_exists($filePath) && is_readable($filePath)) {
                $foundAnyFile = true;
                $configData = null;
                try {
                    $configData = include $filePath;
                } catch (\Throwable $e) {
                    error_log(
                        sprintf(
                            'Sitchco Config Error: Failed to load/parse PHP config file "%s". Error: %s',
                            $filePath,
                            $e->getMessage(),
                        ),
                    );

                    continue;
                }

                if (is_array($configData)) {
                    $configs[] = $configData;
                } else {
                    error_log(sprintf('Sitchco Config Warning: Config file did not return an array: %s', $filePath));
                }
            }
        }

        if (!$foundAnyFile) {
            return null;
        }

        if (empty($configs)) {
            return [];
        }
        $configs = array_map([$this, 'normalizeConfig'], $configs);

        return ArrayUtil::mergeRecursiveDistinct(...$configs);
    }

    private function normalizeConfig($config): array
    {
        if (!is_array($config)) {
            return [];
        }

        $normalized = [];
        foreach ($config as $key => $value) {
            if (is_numeric($key)) {
                if (is_scalar($value) || is_null($value)) {
                    $normalized[$value] = true;
                }
            } else {
                $normalized[$key] = is_array($value) ? $this->normalizeConfig($value) : $value;
            }
        }

        return $normalized;
    }
}
