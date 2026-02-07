<?php

namespace Sitchco\Framework;

use Sitchco\Support\FilePath;
use Sitchco\Utils\Logger;

/**
 * Block Manifest Generator.
 * Discovers blocks in a given base path and generates a sitchco.blocks.json manifest file.
 * Used primarily in local development to auto-detect when blocks are added or removed.
 */
class BlockManifestGenerator
{
    /**
     * Generate a block manifest for the given base path.
     * Discovers all modules and their blocks, then writes sitchco.blocks.json.
     *
     * @param FilePath $basePath The base directory to search (e.g., core plugin root or theme root)
     * @return array The generated manifest data
     */
    public function generate(FilePath $basePath): array
    {
        $blocks = $this->discoverBlocks($basePath);

        $manifest = [
            'version' => BlockManifestRegistry::MANIFEST_VERSION,
            'hash' => $this->calculateHash($blocks),
            'blocks' => $blocks,
        ];

        $this->writeManifest($basePath, $manifest);

        return $manifest;
    }

    /**
     * Check if a manifest needs regeneration by comparing hashes.
     * Only relevant in local environment.
     *
     * @param FilePath $basePath The base directory containing the manifest
     * @return bool True if manifest is missing or stale
     */
    public function shouldRegenerate(FilePath $basePath): bool
    {
        $manifestPath = $basePath->append(BlockManifestRegistry::FILENAME);

        if (!$manifestPath->exists()) {
            return true;
        }

        try {
            $jsonContent = file_get_contents($manifestPath->value());
            if ($jsonContent === false) {
                return true;
            }
            $existingManifest = json_decode($jsonContent, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($existingManifest)) {
                return true;
            }

            $existingHash = $existingManifest['hash'] ?? '';
            $currentBlocks = $this->discoverBlocks($basePath);
            $currentHash = $this->calculateHash($currentBlocks);

            return $existingHash !== $currentHash;
        } catch (\Throwable $e) {
            Logger::error(
                sprintf(
                    'Sitchco Blocks Error: Failed to check manifest staleness at "%s". Error: %s',
                    $manifestPath->value(),
                    $e->getMessage(),
                ),
            );
            return true;
        }
    }

    /**
     * Ensure manifests are fresh for all provided base paths.
     * Checks each path and regenerates if stale.
     *
     * @param array<FilePath> $basePaths Array of FilePath objects to check
     * @return void
     */
    public function ensureFreshManifests(array $basePaths): void
    {
        foreach ($basePaths as $basePath) {
            if ($this->shouldRegenerate($basePath)) {
                $this->generate($basePath);
            }
        }
    }

    /**
     * Discover all blocks in the given base path.
     * Searches for modules with blocks folders and maps block names to relative paths.
     *
     * @param FilePath $basePath The base directory to search
     * @return array<string, string> Associative array of block name => relative path
     */
    private function discoverBlocks(FilePath $basePath): array
    {
        $modulesPath = $basePath->append('modules');
        if (!$modulesPath->isDir()) {
            return [];
        }

        $moduleDirs = array_filter($modulesPath->glob('*'), fn(FilePath $dir) => $dir->isDir());

        // Map each module to its blocks, then merge all together
        $allBlocks = array_map(
            fn(FilePath $moduleDir) => $this->discoverModuleBlocks($moduleDir, $basePath),
            $moduleDirs,
        );

        // Merge all module blocks into a single array
        $blocks = !empty($allBlocks) ? array_merge(...$allBlocks) : [];

        ksort($blocks);
        return $blocks;
    }

    /**
     * Discover all blocks within a single module directory.
     *
     * @param FilePath $moduleDir The module directory to search
     * @param FilePath $basePath The base path for calculating relative paths
     * @return array<string, string> Associative array of block name => relative path
     */
    private function discoverModuleBlocks(FilePath $moduleDir, FilePath $basePath): array
    {
        $blocksPath = $moduleDir->append('blocks');
        if (!$blocksPath->isDir()) {
            return [];
        }

        $blockDirs = array_filter($blocksPath->glob('*'), fn(FilePath $dir) => $dir->isDir());

        // Map each block directory to its metadata, filter out nulls, then merge
        $blockMetadata = array_map(
            fn(FilePath $blockDir) => $this->parseBlockMetadata($blockDir, $basePath),
            $blockDirs,
        );

        // Filter out null values and merge all block arrays
        $validBlocks = array_filter($blockMetadata);

        return !empty($validBlocks) ? array_merge(...$validBlocks) : [];
    }

    /**
     * Parse block.json and return block name => path mapping.
     * Returns null if block.json doesn't exist or is invalid.
     *
     * @param FilePath $blockDir The block directory containing block.json
     * @param FilePath $basePath The base path for calculating relative paths
     * @return array<string, string>|null Single-item array with block name => path, or null if invalid
     */
    private function parseBlockMetadata(FilePath $blockDir, FilePath $basePath): ?array
    {
        $blockJsonPath = $blockDir->append('block.json');
        if (!$blockJsonPath->exists()) {
            return null;
        }

        try {
            $blockJson = json_decode(file_get_contents($blockJsonPath->value()), true);
            if (is_array($blockJson) && isset($blockJson['name'])) {
                $relativePath = untrailingslashit($blockDir->relativeTo($basePath));
                return [$blockJson['name'] => $relativePath];
            }
        } catch (\Throwable $e) {
            Logger::warning(
                sprintf(
                    'Sitchco Blocks Warning: Failed to parse block.json at "%s". Error: %s',
                    $blockJsonPath->value(),
                    $e->getMessage(),
                ),
            );
        }

        return null;
    }

    /**
     * Calculate a hash of the block list to detect additions/removals.
     * Hash is based on the concatenated list of block paths.
     * Assumes blocks are already sorted by discoverBlocks().
     *
     * @param array<string, string> $blocks Associative array of block name => relative path
     * @return string MD5 hash of the block list
     */
    private function calculateHash(array $blocks): string
    {
        if (empty($blocks)) {
            return '';
        }

        // Blocks already sorted by discoverBlocks(), convert to hash string
        $blockStrings = array_map(fn($name, $path) => "$name:$path", array_keys($blocks), $blocks);

        return md5(implode('|', $blockStrings));
    }

    /**
     * Write the manifest to a JSON file.
     *
     * @param FilePath $basePath The base directory where manifest should be written
     * @param array $manifest The manifest data to write
     * @return void
     */
    private function writeManifest(FilePath $basePath, array $manifest): void
    {
        $manifestPath = $basePath->append(BlockManifestRegistry::FILENAME);

        try {
            $jsonContent = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($jsonContent === false) {
                throw new \Exception('JSON encode error: ' . json_last_error_msg());
            }

            $result = file_put_contents($manifestPath->value(), $jsonContent);
            if ($result === false) {
                throw new \Exception('Failed to write file');
            }
        } catch (\Throwable $e) {
            Logger::error(
                sprintf(
                    'Sitchco Blocks Error: Failed to write manifest file "%s". Error: %s',
                    $manifestPath->value(),
                    $e->getMessage(),
                ),
            );
        }
    }
}
