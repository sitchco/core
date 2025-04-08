<?php

namespace Sitchco\Framework\Core;

use Sitchco\Utils\ArrayUtil;
use Sitchco\Utils\Hooks;

/**
 * Unified Configuration Registry Service.
 * Loads, merges, and caches configuration files (PHP or JSON)
 * from standard locations (core plugin, additional filtered paths, parent theme, child theme).
 * Caching is disabled in 'local' environment, enabled via transient otherwise.
 */
class ConfigRegistry
{
    /** @var array Cache for loaded configs within the current request */
    private array $requestCache = [];
    /** @var array<string>|null Ordered list of base paths to search (null until initialized) */
    private ?array $basePaths = null;
    /** @var string Current WP environment type */
    private string $environmentType;
    /** @var bool Whether persistent object caching is enabled for this instance */
    private bool $objectCacheEnabled;
    /** @var int Default TTL for cached items in seconds (e.g., 1 day) */
    private const CACHE_TTL = DAY_IN_SECONDS;
    /** @var string Filter hook for adding additional config paths */
    public const PATH_FILTER_HOOK = 'additional_config_paths';

    /**
     * Constructor.
     *
     * @param string|null $environmentType Override environment type (mainly for testing). Defaults to
     *                                     wp_get_environment_type().
     */
    public function __construct(?string $environmentType = null)
    {
        $this->environmentType = $environmentType ?? wp_get_environment_type();
        // We disable object caching in the 'local' environment to ensure that changes to config files are immediately reflected.
        $this->objectCacheEnabled = $this->environmentType !== 'local';
    }

    /**
     * Loads, merges, and caches a PHP configuration file (.php).
     * Expects the PHP file to return an array.
     *
     * @param string $configName The base name of the config file (e.g., 'modules', 'container').
     * @param array  $default    Default value if no files found or files are invalid.
     *
     * @return array The merged configuration array.
     */
    public function loadPhpConfig(string $configName, array $default = []): array
    {
        return $this->load('php', $configName, $default);
    }

    /**
     * Loads, merges, and caches a JSON configuration file (.json).
     *
     * @param string $configName The base name of the config file (e.g., 'settings').
     * @param array  $default    Default value if no files found or files are invalid.
     *
     * @return array The merged configuration array.
     */
    public function loadJsonConfig(string $configName, array $default = []): array
    {
        return $this->load('json', $configName, $default);
    }

    /**
     * Core loading logic.
     *
     * @param string $type       'php' or 'json'.
     * @param string $configName Base name of the config file.
     * @param array  $default    Default value.
     *
     * @return array Merged configuration.
     */
    private function load(string $type, string $configName, array $default): array
    {
        $type = strtolower($type);
        $configName = trim($configName);
        if (empty($configName) || !in_array($type, ['php', 'json'])) {
            error_log(
                "Sitchco Config Error: Invalid type or configName provided to load(). Type: {$type}, Name: {$configName}"
            );

            return $default;
        }

        // We only initialize base paths when needed to avoid unnecessary work if no config is loaded.
        if ($this->basePaths === null) {
            $this->initializeBasePaths();
        }

        // We use a request cache to avoid redundant file reads within the same request.
        $cacheKey = "sitchco_cfg_{$type}_{$configName}";
        if (isset($this->requestCache[$cacheKey])) {
            return $this->requestCache[$cacheKey];
        }

        // We use object caching (transients) to persist config data between requests, improving performance.
        $cachedValue = false;
        if ($this->objectCacheEnabled) {
            $cachedValue = get_transient($cacheKey);
        }
        if (is_array($cachedValue)) {
            $this->requestCache[$cacheKey] = $cachedValue;

            return $cachedValue;
        }

        $mergedConfig = $this->loadAndMergeFiles($type, $configName);
        $finalResult = $mergedConfig === null ? $default : $mergedConfig;

        if ($this->objectCacheEnabled) {
            set_transient($cacheKey, $finalResult, self::CACHE_TTL);
        }

        $this->requestCache[$cacheKey] = $finalResult;

        return $finalResult;
    }

    /**
     * Initializes the base paths using the "Sandwich" filter approach.
     */
    private function initializeBasePaths(): void
    {
        $core_paths =
            defined('SITCHCO_CORE_CONFIG_DIR') && is_dir(SITCHCO_CORE_CONFIG_DIR)
                ? [trailingslashit(SITCHCO_CORE_CONFIG_DIR)]
                : [];

        // We allow additional paths to be added via a filter hook, providing flexibility for extending config locations.
        $additional_paths_raw = apply_filters(Hooks::name(self::PATH_FILTER_HOOK), []);
        $additional_paths = is_array($additional_paths_raw) ? array_map('trailingslashit', $additional_paths_raw) : [];
        $theme_paths = array_map('trailingslashit', [
            get_template_directory() . '/config',
            get_stylesheet_directory() . '/config',
        ]);

        // The order of paths is important: core, then additional, then parent theme, then child theme.
        // This ensures that child theme configs override parent theme configs, which override core configs.
        $combined_paths = array_merge($core_paths, $additional_paths, $theme_paths);
        $this->basePaths = array_values(array_unique(array_filter($combined_paths, 'is_dir')));
    }

    /**
     * Finds files in base paths, loads/parses them, and merges the results.
     *
     * @param string $type       'php' or 'json'.
     * @param string $configName Base name of the config file.
     *
     * @return array|null Merged array, empty array if files found but were empty/invalid, null if no files found.
     */
    private function loadAndMergeFiles(string $type, string $configName): ?array
    {
        if ($this->basePaths === null) {
            error_log('Sitchco Config Error: Base paths not initialized before loading files.');

            return null;
        }
        $fileNameSuffix = '.' . $type;
        $configs = [];
        $foundAnyFile = false;

        foreach ($this->basePaths as $path) {
            $filePath = $path . $configName . $fileNameSuffix;
            if (file_exists($filePath) && is_readable($filePath)) {
                $foundAnyFile = true;
                $configData = null;
                try {
                    if ($type === 'php') {
                        $configData = include $filePath;
                    } elseif ($type === 'json') {
                        $jsonData = file_get_contents($filePath);
                        if ($jsonData === false) {
                            throw new \RuntimeException('Failed to read file.');
                        }
                        $configData = json_decode($jsonData, true, 512, JSON_THROW_ON_ERROR);
                        if (!is_array($configData)) {
                            throw new \RuntimeException('JSON did not decode to an array.');
                        }
                    }
                } catch (\Throwable $e) {
                    error_log(
                        sprintf(
                            'Sitchco Config Error: Failed to load/parse %s config file "%s". Error: %s',
                            strtoupper($type),
                            $filePath,
                            $e->getMessage()
                        )
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

        return ArrayUtil::mergeRecursiveDistinct(...$configs);
    }
}
