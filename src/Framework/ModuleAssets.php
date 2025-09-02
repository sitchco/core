<?php

namespace Sitchco\Framework;

use Sitchco\Support\FilePath;
use Sitchco\Support\HookName;
use Sitchco\Utils\Hooks;

class ModuleAssets
{
    public readonly FilePath $modulePath;

    public readonly string $namespace;

    public readonly ?FilePath $productionBuildPath;
    public readonly ?FilePath $devBuildPath;
    public readonly string $devBuildUrl;
    public readonly bool $isDevServer;


    private static array $manifestCache = [];

    public function __construct(Module $module, $devServerFile = SITCHCO_DEV_HOT_FILE)
    {
        $this->modulePath = $module->assetsPath();
        $this->namespace = $module::hookName();
        $this->productionBuildPath = $this->modulePath->findAncestor(SITCHCO_CONFIG_FILENAME);
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
        $handle = $this->namespacedHandle($handle);
        $src_url = $this->scriptUrl($src);
        $is_module = str_ends_with(strtolower($src), '.mjs');

        if ($this->isDevServer || $is_module) {
            wp_register_script_module($handle, $src_url, $deps);
        } else {
            wp_register_script($handle, $src_url, $deps);
        }
    }

    public function enqueueScript(string $handle, string $src = '', array $deps = []): void
    {
        $handle = $this->namespacedHandle($handle);
        $src_url = $this->scriptUrl($src);
        $is_module = str_ends_with(strtolower($src), '.mjs');

        if (!$this->isDevServer) {
            if ($is_module) {
                wp_enqueue_script_module($handle, $src_url, $deps);
            } else {
                wp_enqueue_script($handle, $src_url, $deps);
            }
            return;
        }

        $this->enqueueViteClient();
        $registered = wp_scripts()->registered[$handle] ?? null;
        if ($registered) {
            $deps = $registered->deps;
        }
        foreach ($deps as $dep) {
            if (str_starts_with($dep, Hooks::ROOT)) {
                wp_enqueue_script_module($dep);
            } else {
                wp_enqueue_script($dep);
            }
        }
        wp_enqueue_script_module($handle, $src_url, $deps);
    }

    public function registerStyle(string $handle, string $src, array $deps = [], $media = 'all'): void
    {
        $handle = $this->namespacedHandle($handle);
        $src = $this->styleUrl($src);
        wp_register_style($handle, $src, $deps, null, $media);
    }

    public function enqueueStyle(string $handle, string $src = '', array $deps = [], $media = 'all'): void
    {
        $handle = $this->namespacedHandle($handle);
        $src = $this->styleUrl($src);
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
                '6.1.0'
            );
        }
        if ($this->isDevServer) {
            $this->enqueueViteClient();
        }
        wp_enqueue_block_style($blockName, [
            'handle' => $blockName,
            'src' => $this->styleUrl($src),
            'path' =>  $this->stylePath($src)->value(),
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
        $content = sprintf("window.$object_name = %s;", wp_json_encode($data));
        $this->inlineScript($handle, $content, $position);
    }

    private function assetUrl(string $relativePath): string
    {
        if (empty($relativePath) || str_starts_with($relativePath, $this->modulePath->value())) {
            return $relativePath;
        }
        $assetPath = $this->modulePath->append($relativePath);
        if ($this->isDevServer) {
            return $this->devBuildUrl . '/' . $assetPath->relativeTo($this->productionBuildPath);
        }
        $buildAssetPath = $this->buildAssetPath($assetPath);
        return $buildAssetPath ? $buildAssetPath->url() : $relativePath;
    }

    private function scriptUrl(string $relative): string
    {
        return $this->assetUrl("assets/scripts/$relative");
    }

    private function styleUrl(string $relative): string
    {
        return $this->assetUrl("assets/styles/$relative");
    }

    private function stylePath(string $relative): FilePath
    {
        return $this->modulePath->append("assets/styles/$relative");
    }

    private function enqueueViteClient(): void
    {
        if (doing_action('enqueue_block_assets')) {
            $namespace = $this->productionBuildPath->name();
            wp_enqueue_script_module("$namespace/vite-client", $this->devBuildUrl . '/@vite/client', [], null);
        }
    }

    private function namespacedHandle(string $handle): string
    {
        if (!str_starts_with($handle, $this->namespace)) {
            $handle = HookName::join($this->namespace, $handle);
        }
        return $handle;
    }
}
