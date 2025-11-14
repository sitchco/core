<?php

namespace Sitchco\Framework;

use Sitchco\Support\FilePath;

/**
 * Block Manifest Registry Service.
 * Loads block manifest files (sitchco.blocks.json) from defined locations
 * (Core plugin, additional filtered paths, parent theme, child theme),
 * merges their contents recursively, caches the result, and provides access
 * to the merged block definitions.
 */
class BlockManifestRegistry extends FileRegistry
{
    /** @var string Filename to search for in base paths */
    public const FILENAME = 'sitchco.blocks.json';

    /** @var string Filter hook for adding additional manifest paths */
    public const PATH_FILTER_HOOK = 'blocks_manifest_paths';

    /** @var string Cache key for merged manifest */
    public const CACHE_KEY = 'sitchco_blocks_manifest';

    /** @var string Version for manifest files */
    public const MANIFEST_VERSION = '1.0';

    /** @var string Current WP environment type */
    private string $environmentType;

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

    protected function getDefaultData(): array
    {
        return ['version' => self::MANIFEST_VERSION, 'blocks' => []];
    }

    protected function parseFile(FilePath $filePath): mixed
    {
        $jsonContent = file_get_contents($filePath->value());
        if ($jsonContent === false) {
            throw new \Exception('Failed to read file');
        }
        $manifestData = json_decode($jsonContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('JSON decode error: ' . json_last_error_msg());
        }

        return $manifestData;
    }
}
