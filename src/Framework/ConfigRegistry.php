<?php

namespace Sitchco\Framework;

use Sitchco\Support\FilePath;

/**
 * Configuration Registry Service.
 * Loads a single configuration file (sitchco.config.php) from defined locations
 * (Core defined path, additional filtered paths, parent theme, child theme),
 * merges their contents recursively, caches the result, and provides access
 * to specific configuration sections (top-level keys) within the merged array.
 */
class ConfigRegistry extends FileRegistry
{
    /** @var string Filename to search for in base paths */
    public const FILENAME = SITCHCO_CONFIG_FILENAME;

    /** @var string Filter hook for adding additional config paths */
    public const PATH_FILTER_HOOK = 'config_paths';

    /** @var string Cache key for merged configuration */
    public const CACHE_KEY = 'sitchco_config';

    protected function parseFile(FilePath $filePath): mixed
    {
        return include $filePath->value();
    }

    /**
     * Beyond the base empty check, require that every theme whose sitchco.config.php currently
     * exists on disk actually contributed to the merge. If a config file is present now but its
     * directory isn't among the resolved base paths, it was unreadable when paths were resolved
     * (the classic mid-deploy state), so the merge is missing modules it should have — caching
     * it would silently disable those modules site-wide until the next object-cache flush.
     *
     * This deliberately keys off files present on disk rather than a hardcoded theme list, so it
     * stays correct when modules come from filtered paths instead of the active theme (e.g. the
     * test harness).
     */
    protected function isMergedDataCacheable(?array $merged): bool
    {
        if (!parent::isMergedDataCacheable($merged)) {
            return false;
        }

        $contributing = array_map(fn(FilePath $fp) => $fp->value(), $this->getBasePaths());
        foreach ([get_template_directory(), get_stylesheet_directory()] as $themeDir) {
            $dir = FilePath::create($themeDir);
            if ($dir->append(static::FILENAME)->isFile() && !in_array($dir->value(), $contributing, true)) {
                return false;
            }
        }

        return true;
    }

    protected function normalizeData(array $data): array
    {
        $normalized = [];
        foreach ($data as $key => $value) {
            if (is_numeric($key)) {
                if (is_scalar($value) || is_null($value)) {
                    $normalized[$value] = true;
                }
            } else {
                $normalized[$key] = is_array($value) ? $this->normalizeData($value) : $value;
            }
        }

        return $normalized;
    }
}
