<?php

namespace Sitchco\Framework;

use Sitchco\Support\FilePath;

class ModuleAssets
{
    public readonly FilePath $modulePath;

    public readonly ?FilePath $productionBuildPath;
    public readonly ?FilePath $devBuildPath;
    public readonly string $devBuildUrl;
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
        if ($this->isDevServer) {
            $devBuildUrl = file_get_contents($this->devBuildPath->append(SITCHCO_DEV_HOT_FILE));
            $port = parse_url($devBuildUrl, PHP_URL_PORT) ?: 5173;
            $this->devBuildUrl = "https://{$_SERVER['HTTP_HOST']}:$port";
        }
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
        wp_register_script($handle, $src, $deps);
        if ($this->isDevServer) {
            wp_register_script_module($handle, $src, $deps);
        }
    }

    public function enqueueScript(string $handle, string $src = '', array $deps = []): void
    {
        if (!$this->isDevServer) {
            wp_enqueue_script($handle, $src, $deps);
            return;
        }
        $this->enqueueViteClient();
        // fetch registered dependencies
        $registered = wp_scripts()->registered[$handle] ?? null;
        if ($registered) {
            $deps = $registered->deps;
        }
        foreach ($deps as $dep) {
            wp_enqueue_script($dep);
            wp_enqueue_script_module($dep);
        }
        wp_enqueue_script_module($handle, $src, $deps);
    }

    public function registerStyle(string $handle, string $src, array $deps = [], $media = 'all'): void
    {
        wp_register_style($handle, $src, $deps, null, $media);
    }

    public function enqueueStyle(string $handle, string $src, array $deps = [], $media = 'all'): void
    {
        if ($this->isDevServer) {
            $this->enqueueViteClient();
        }
        wp_enqueue_style($handle, $src, $deps, null, $media);
    }

    public function enqueueBlockStyle(string $handle, array $args = []): void
    {
        if ($this->isDevServer) {
            $this->enqueueViteClient();
        }
        wp_enqueue_block_style($handle, $args);
    }

    public function assetUrl(string $relativePath): string
    {
        $assetPath = $this->modulePath->append($relativePath);
        if ($this->isDevServer) {
            return $this->devBuildUrl . '/' . $assetPath->relativeTo($this->productionBuildPath);
        }
        $buildAssetPath = $this->buildAssetPath($assetPath);
        return $buildAssetPath ? $buildAssetPath->url() : '';
    }

    private function enqueueViteClient(): void
    {
        $namespace = $this->productionBuildPath->name();
        wp_enqueue_script_module("$namespace/vite-client", $this->devBuildUrl . '/@vite/client', [], null);
    }
}
