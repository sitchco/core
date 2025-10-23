<?php

namespace Sitchco\Framework;

use Sitchco\Support\FilePath;
use Sitchco\Support\HookName;
use Sitchco\Utils\Hooks;

class ModuleAssets
{
    const BLOCK_METADATA_FIELDS = ['script', 'editorScript', 'viewScript', 'style', 'editorStyle', 'viewStyle'];

    public readonly FilePath $moduleAssetsPath;
    public readonly FilePath $blocksPath;

    public readonly string $namespace;

    public readonly ?FilePath $productionBuildPath;
    public readonly ?FilePath $devBuildPath;
    public readonly string $devBuildUrl;
    public readonly bool $isDevServer;

    private static array $manifestCache = [];

    public function __construct(Module $module, $devServerFile = SITCHCO_DEV_HOT_FILE)
    {
        $this->moduleAssetsPath = $module->assetsPath();
        $this->blocksPath = $module->blocksPath();
        $this->namespace = $module::hookName();
        $this->productionBuildPath = $this->moduleAssetsPath->findAncestor(SITCHCO_CONFIG_FILENAME);
        if (wp_get_environment_type() !== 'local') {
            $this->isDevServer = false;
            return;
        }
        $this->devBuildPath = $this->productionBuildPath->findAncestor($devServerFile);
        $this->isDevServer = $this->devBuildPath && $this->devBuildPath->exists();
        if ($this->isDevServer) {
            $devBuildUrl = file_get_contents($this->devBuildPath->append($devServerFile));
            $port = parse_url($devBuildUrl, PHP_URL_PORT) ?: 5173;
            $this->devBuildUrl = "https://{$_SERVER['HTTP_HOST']}:$port";
        }
    }

    protected function buildAssetPath(FilePath $assetPath): ?FilePath
    {
        $buildPath = $this->productionAssetsPath();
        $manifestPath = $buildPath->append('.vite/manifest.json');
        if (!$manifestPath->exists()) {
            return null;
        }
        $manifestKey = $manifestPath->value();
        if (!isset($this->manifestCache[$manifestKey])) {
            static::$manifestCache[$manifestKey] = json_decode(file_get_contents($manifestPath), true);
        }
        $manifest = static::$manifestCache[$manifestKey];
        $assetKey = $assetPath->relativeTo($this->productionBuildPath->value());
        if (!isset($manifest[$assetKey])) {
            return null;
        }
        return $buildPath->append($manifest[$assetKey]['file']);
    }

    public function registerScript(string $handle, string $src, array $deps = []): void
    {
        $handle = $this->namespacedHandle($handle);
        $src = $this->scriptUrl($src);
        if (!$src) {
            return;
        }
        wp_register_script($handle, $src, $deps);
        if ($this->isDevServer) {
            wp_register_script_module($handle, $src, $deps);
        }
    }

    public function enqueueScript(string $handle, string $src = '', array $deps = []): void
    {
        $handle = $this->namespacedHandle($handle);
        if ($src) {
            $src = $this->scriptUrl($src);
        }
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
        $this->enqueueDependencies($deps);
        wp_enqueue_script_module($handle, $src, $deps);
    }

    public function registerStyle(string $handle, string $src, array $deps = [], $media = 'all'): void
    {
        $handle = $this->namespacedHandle($handle);
        $src = $this->styleUrl($src);
        if (!$src) {
            return;
        }

        wp_register_style($handle, $src, $deps, null, $media);
    }

    public function enqueueStyle(string $handle, string $src = '', array $deps = [], $media = 'all'): void
    {
        $handle = $this->namespacedHandle($handle);
        if ($src) {
            $src = $this->styleUrl($src);
        }
        if ($this->isDevServer) {
            $this->enqueueViteClient();
        }
        wp_enqueue_style($handle, $src, $deps, null, $media);
    }

    public function enqueueBlockStyle(string $blockName, string $src): void
    {
        if (!doing_action('init') && did_action('init')) {
            _doing_it_wrong(
                __METHOD__,
                'wp_enqueue_block_style() should be called during or before the init hook.',
                '6.1.0',
            );
        }
        if ($this->isDevServer) {
            $this->enqueueViteClient();
        }
        $url = $this->styleUrl($src);
        if (!$url) {
            return;
        }
        wp_enqueue_block_style($blockName, [
            'handle' => $blockName,
            'src' => $url,
            'path' => $this->stylePath($src)->value(),
        ]);
    }

    public function inlineScript(string $handle, string $content, $position = null): void
    {
        $handle = $this->namespacedHandle($handle);
        if (!$this->isDevServer) {
            wp_add_inline_script($handle, $content, $position);
            return;
        }
        $isHeader = $position !== 'after';
        $hook = $isHeader ? (is_admin() ? 'admin_head' : 'wp_head') : (is_admin() ? 'admin_footer' : 'wp_footer');
        $callback = function () use ($content) {
            echo "<script>{$content}</script>";
        };
        if (did_action($hook)) {
            $callback();
        } else {
            add_action($hook, $callback);
        }
    }

    public function inlineScriptData(string $handle, string $object_name, $data, $position = null): void
    {
        $content = sprintf(
            "window.sitchco = window.sitchco || {}; window.sitchco.$object_name = %s;",
            wp_json_encode($data),
        );
        $this->inlineScript($handle, $content, $position);
    }

    public function blockTypeMetadata(array $metadata, array $blocksConfig): array
    {
        $blockPath = $blocksConfig[$metadata['name']] ?? null;
        if (!$blockPath) {
            return $metadata;
        }
        if ($this->isDevServer) {
            $this->enqueueViteClient();
            // Enqueue block dependencies first
            $this->enqueueDependencies($metadata['dependencies'] ?? []);
        }
        foreach (static::BLOCK_METADATA_FIELDS as $fieldName) {
            $metadata = $this->updateBlockAssets($metadata, $fieldName, $blockPath);
        }
        return $metadata;
    }

    private function updateBlockAssets(array $metadata, string $fieldName, string $blockPath): array
    {
        if (!isset($metadata[$fieldName])) {
            return $metadata;
        }
        $isScript = str_ends_with(strtolower($fieldName), 'script');
        $wasArray = is_array($metadata[$fieldName]);
        $assetPaths = $wasArray ? $metadata[$fieldName] : [$metadata[$fieldName]];
        $assetPaths = array_filter($assetPaths);
        $handles = [];

        foreach ($assetPaths as $index => $assetPath) {
            $fullPath = $this->blockAssetPath($blockPath, $assetPath);
            $url = $this->assetUrl($fullPath);

            if (empty($url)) {
                continue;
            }

            // Generate a unique handle for this asset
            $handle = $this->generateAssetHandle($metadata['name'], $fieldName, $index);

            // Register the asset with WordPress
            // Use regular script registration for both dev and production
            // WordPress's block system expects regular script handles, not script modules
            if ($isScript) {
                wp_register_script($handle, $url, [], null, true);
            } else {
                wp_register_style($handle, $url, [], null);
            }

            $handles[] = $handle;
        }

        // Preserve original format: if it was a string, return first handle; if array, return array of handles
        $metadata[$fieldName] = $wasArray ? $handles : $handles[0] ?? '';
        return $metadata;
    }

    private function generateAssetHandle(string $blockName, string $fieldName, int $index): string
    {
        // Convert block name to handle-safe format: roundabout/content-slider -> roundabout-content-slider
        $blockSlug = str_replace('/', '-', $blockName);
        $suffix = $index > 0 ? "-{$index}" : '';
        return "{$blockSlug}-{$fieldName}{$suffix}";
    }

    private function assetUrl(FilePath $assetPath): string
    {
        if ($this->isDevServer) {
            return $this->devBuildUrl . '/' . $assetPath->relativeTo($this->productionBuildPath);
        }
        $buildAssetPath = $this->buildAssetPath($assetPath);
        if ($buildAssetPath) {
            return $buildAssetPath->url();
        }
        error_log('Production build path not found for asset: ' . $assetPath->value(), E_USER_WARNING);
        return '';
    }

    private function assetUrlRelative(string $relativePath): string
    {
        if (empty($relativePath)) {
            error_log('Empty Asset Relative Path: ', E_USER_WARNING);
            return '';
        }
        $assetPath = str_starts_with($relativePath, $this->moduleAssetsPath->value())
            ? new FilePath($this->moduleAssetsPath->value())
            : $this->moduleAssetsPath->append($relativePath);

        return $this->assetUrl($assetPath);
    }

    private function scriptUrl(string $relative): string
    {
        return $this->assetUrlRelative("scripts/$relative");
    }

    private function styleUrl(string $relative): string
    {
        return $this->assetUrlRelative("styles/$relative");
    }

    private function stylePath(string $relative): FilePath
    {
        return $this->moduleAssetsPath->append("styles/$relative");
    }

    public function imageUrl(string $relative): string
    {
        return $this->assetUrlRelative("images/$relative");
    }

    public function inlineSVGSymbol(string $name): string
    {
        if (!$this->isDevServer) {
            return '<svg><use fill="currentColor" href="#' . $name . '"></use></svg>';
        }
        $svgFile = $this->moduleAssetsPath->append("assets/images/svg-sprite/$name.svg");
        if (!$svgFile->exists()) {
            return "<!-- SVG Symbol $name not found -->";
        }
        return file_get_contents($svgFile->value());
    }

    public function productionAssetsPath()
    {
        return $this->productionBuildPath?->append('dist');
    }

    public function blockAssetPath(string $blockPath, string $relativePath): FilePath
    {
        $relativePath = str_replace('file:', '', $relativePath);
        $relativePath = ltrim($relativePath, './');
        return $this->blocksPath->append($blockPath)->append($relativePath);
    }

    private function enqueueViteClient(): void
    {
        if (!$this->isDevServer) {
            return;
        }

        $namespace = $this->productionBuildPath->name();
        $handle = "$namespace/vite-client";

        // Only enqueue if not already enqueued
        if (wp_script_is($handle, 'enqueued')) {
            return;
        }

        wp_enqueue_script_module($handle, $this->devBuildUrl . '/@vite/client', [], null);
    }

    private function namespacedHandle(string $handle): string
    {
        if (!str_starts_with($handle, $this->namespace)) {
            $handle = HookName::join($this->namespace, $handle);
        }
        return $handle;
    }

    private function enqueueDependencies(array $dependencies): void
    {
        foreach ($dependencies as $dep) {
            // only treat our dependencies as modules
            if (str_starts_with($dep, Hooks::ROOT)) {
                wp_enqueue_script_module($dep);
            } else {
                wp_enqueue_script($dep);
            }
        }
    }
}
