<?php

namespace Sitchco\Framework;

use Sitchco\Support\FilePath;

class ModuleAssets
{
    public readonly FilePath $modulePath;

    public readonly ?FilePath $productionBuildPath;
    public readonly ?FilePath $devBuildPath;
    public readonly bool $isDevServer;


    private static array $manifestCache = [];

    public function __construct(FilePath $modulePath)
    {
        $this->modulePath = $modulePath;
        $this->productionBuildPath = $this->modulePath->findAncestor(SITCHCO_CONFIG_FILENAME);
        if (wp_get_environment_type() !== 'local') {
            $this->isDevServer = false;
            return;
        }
        $this->devBuildPath = $this->productionBuildPath->findAncestor(SITCHCO_DEV_HOT_FILE);
        $this->isDevServer = $this->devBuildPath && $this->devBuildPath->exists();
    }

    public function buildAssetPath(FilePath $assetPath): ?FilePath
    {
        $buildPath = $this->productionBuildPath?->append('dist');
        $manifestPath = $buildPath->append('.vite/manifest.json');
        if (!$manifestPath->exists()) {
            return null;
        }
        $manifestKey = $manifestPath->value();
        if (!isset($this->manifestCache[$manifestKey])) {
            static::$manifestCache[$manifestKey] = json_decode(file_get_contents($manifestPath), true);
        }
        $manifest = static::$manifestCache[$manifestKey];
        $assetKey = $assetPath->relativeTo($this->productionBuildPath);
        if (!isset($manifest[$assetKey])) {
            return null;
        }
        return $buildPath->append($manifest[$assetKey]['file']);
    }

    public function registerScript(string $handle, string $src, array $deps = []): void
    {
        if (!$this->isDevServer) {
            wp_register_script($handle, $src, $deps);
            return;
        }
        wp_register_script_module($handle, $src, $deps);
    }

    public function enqueueScript(string $handle, string $src = '', array $deps = []): void
    {
        if (!$this->isDevServer) {
            wp_enqueue_script($handle, $src, $deps);
            return;
        }
        wp_enqueue_script_module(
            'vite-client',
            SITCHCO_DEV_SERVER_URL . '/@vite/client',
            [],
            null
        );
        foreach ($deps as $dep) {
            wp_enqueue_script($dep);
            wp_enqueue_script_module($dep);
        }
        wp_enqueue_script_module($handle, $src, $deps);
    }

    public function assetUrl(string $relativePath): string
    {
        $assetPath = $this->modulePath->append($relativePath);
        if ($this->isDevServer) {
            return SITCHCO_DEV_SERVER_URL . '/' . $assetPath->relativeTo($this->productionBuildPath);
        }
        $buildAssetPath = $this->buildAssetPath($assetPath);
        return $buildAssetPath ? $buildAssetPath->url() : '';
    }
}
