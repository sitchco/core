<?php

namespace Sitchco\Framework;

use Sitchco\Support\FilePath;
use Sitchco\Support\HookName;
use Sitchco\Utils\Cache;
use Sitchco\Utils\Logger;
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

    private static bool $hooked = false;

    public function __construct(Module $module, $devServerFile = SITCHCO_DEV_HOT_FILE)
    {
        $this->moduleAssetsPath = $module->assetsPath();
        $this->blocksPath = $module->blocksPath();
        $this->namespace = $module::hookName();
        $this->productionBuildPath = $this->moduleAssetsPath->findAncestor(SITCHCO_CONFIG_FILENAME);
        if (wp_get_environment_type() !== 'local' || is_admin()) {
            $this->isDevServer = false;
            return;
        }
        $this->devBuildPath = $this->productionBuildPath->findAncestor($devServerFile);
        $this->isDevServer = $this->devBuildPath && $this->devBuildPath->exists();
        if ($this->isDevServer) {
            $devBuildUrl = file_get_contents($this->devBuildPath->append($devServerFile));
            $port = parse_url($devBuildUrl, PHP_URL_PORT) ?: 5173;
            $this->devBuildUrl = "https://{$_SERVER['HTTP_HOST']}:$port";
            if (!self::$hooked) {
                add_filter('wp_script_attributes', [$this, 'devServerScriptAttributes']);
            }
        }
    }

    public function devServerScriptAttributes(array $attributes): array
    {
        if ($this->isDevServer && str_starts_with($attributes['src'], $this->devBuildUrl)) {
            $attributes['type'] = 'module';
        }
        return $attributes;
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
        $assetKey = $assetPath->relativeTo($this->productionBuildPath);
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
    }

    public function enqueueScript(string $handle, string $src = '', array $deps = []): void
    {
        $handle = $this->namespacedHandle($handle);
        if ($src) {
            $src = $this->scriptUrl($src);
        }
        wp_enqueue_script($handle, $src, $deps);
        $this->enqueueViteClient();
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
        wp_enqueue_style($handle, $src, $deps, null, $media);
        $this->enqueueViteClient();
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
        $this->enqueueViteClient();
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
        $this->enqueueViteClient();
        foreach (static::BLOCK_METADATA_FIELDS as $fieldName) {
            $this->updateBlockAssets($metadata, $fieldName, $blockPath);
        }
        return $metadata;
    }

    private function updateBlockAssets(array $metadata, string $fieldName, string $blockPath): void
    {
        if (!isset($metadata[$fieldName])) {
            return;
        }
        $isScript = str_ends_with(strtolower($fieldName), 'script');
        $assetPaths = is_array($metadata[$fieldName]) ? $metadata[$fieldName] : [$metadata[$fieldName]];
        $assetPaths = array_filter($assetPaths);
        foreach ($assetPaths as $index => $assetPath) {
            if (!str_contains($assetPath, 'file:') && !str_contains($assetPath, '.')) {
                continue;
            }

            $fullPath = $this->blockAssetPath($blockPath, $assetPath);
            $assetUrl = $this->assetUrl($fullPath);
            if (!$assetUrl) {
                continue;
            }
            $handle = generate_block_asset_handle($metadata['name'], $fieldName, $index);

            // Load dependencies and version from .asset.php file if it exists
            $dependencies = [];
            $version = $metadata['version'] ?? null;
            $assetPhpPath = $fullPath->parent()->append(sprintf('%s.asset.php', $fullPath->name()));
            $assetData = Cache::remember(
                'asset_php:' . md5($assetPhpPath->value()),
                fn() => $assetPhpPath->exists() ? require $assetPhpPath->value() : [],
            );
            $dependencies = $assetData['dependencies'] ?? [];
            $version = $assetData['version'] ?? $version;

            if ($isScript) {
                $args = 'viewScript' === $fieldName ? ['strategy' => 'defer'] : [];
                wp_register_script($handle, $assetUrl, $dependencies, $version, $args);
            } else {
                wp_register_style($handle, $assetUrl, $dependencies, $version);
            }
        }
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
        Logger::warning('Production build path not found for asset: ' . $assetPath->value());
        return '';
    }

    private function assetUrlRelative(string $relativePath): string
    {
        if (empty($relativePath)) {
            Logger::warning('Empty Asset Relative Path');
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
        wp_enqueue_script_module("$namespace/vite-client", $this->devBuildUrl . '/@vite/client', [], null);
    }

    private function namespacedHandle(string $handle): string
    {
        if (!str_starts_with($handle, $this->namespace)) {
            $joined = HookName::join($this->namespace, $handle);
            $handle = str_ends_with($this->namespace, "/$handle") ? $this->namespace : $joined;
        }
        return $handle;
    }
}
