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
