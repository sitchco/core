<?php

namespace Sitchco\Framework;

use Sitchco\Support\FilePath;
use Sitchco\Support\HasHooks;

/**
 * Abstract base class for modules in the Sitchco framework.
 * Modules extending this class can provide a range of functionalities,
 * from adding simple WordPress filters to complex configurations including
 * custom post types, ACF fields, and Gutenberg blocks.
 * Each module can be conditionally enabled or configured based on theme support.
 */
abstract class Module
{
    use HasHooks;

    /**
     * An array of other modules that this module depends on to function.
     * These modules will automatically be registered immediately before this module and configured to activate.
     * Each key is the fully-qualified class name of the module
     * @example [OtherModule1::class, OtherModule2::class]
     */
    public const DEPENDENCIES = [];
    /**
     * An array of features provided by this module.
     * Each feature is a key-value pair, where:
     * - The key is a string representing the feature name, which maps to a method.
     * - The value is a boolean that indicates if the feature is enabled by default.
     * The feature keys should match method names in the class for dynamic execution.
     * @example ['disable_admin_bar' => true, 'custom_post_type' => false]
     * @var array<string, bool>
     */
    public const FEATURES = [];
    /**
     * An array of Timber custom post classes associated with this module.
     * Each entry is a fully qualified class name for the Timber post model.
     * The ModuleRegistry will automatically add these classes to the `timber/post/classmap`.
     * @example [\Sitchco\Models\PortfolioPost::class]
     * @var array<string>
     */
    public const POST_CLASSES = [];

    private ?FilePath $modulePath = null;

    private ModuleAssets $assets;

    /**
     * Default initialization feature that is always called when module is activated
     * @return void
     */
    public function init() {}

    /**
     * Filesystem path to this module's directory or subpath.
     */
    public function path(string $relative = ''): FilePath
    {
        if (!isset($this->modulePath)) {
            $this->modulePath = FilePath::createFromClassDir($this);
        }

        return $relative ? $this->modulePath->append($relative) : $this->modulePath;
    }

    public function assetsPath(): FilePath
    {
        return $this->path('assets');
    }

    public function blocksPath(): FilePath
    {
        return $this->path('blocks');
    }

    public function filterBlockAssets(array $blocksConfig): void
    {
        add_filter('block_type_metadata', fn($metadata) => $this->assets()->blockTypeMetadata($metadata, $blocksConfig));
    }

    protected function assets(): ModuleAssets
    {
        if (!isset($this->assets)) {
            $this->assets = new ModuleAssets($this);
        }
        return $this->assets;
    }

    protected function enqueueGlobalAssets(callable $callable, int $priority = 10): void
    {
        $this->addAssetAction('enqueue_block_assets', $callable, $priority);
    }

    protected function enqueueAdminAssets(callable $callable, int $priority = 10): void
    {
        $this->addAssetAction('admin_enqueue_scripts', $callable, $priority);
    }

    protected function enqueueFrontendAssets(callable $callable, int $priority = 10): void
    {
        $this->addAssetAction('wp_enqueue_scripts', $callable, $priority);
    }

    protected function enqueueEditorPreviewAssets(callable $callable, int $priority = 10): void
    {
        $this->addAssetAction('enqueue_block_assets', fn($a) => is_admin() && $callable($a), $priority);
    }

    protected function enqueueEditorUIAssets(callable $callable, int $priority = 10): void
    {
        $this->addAssetAction('enqueue_block_editor_assets', $callable, $priority);
    }

    protected function registerAssets(callable $callable, int $priority = 20): void
    {
        $this->addAssetAction('init', $callable, $priority);
    }

    protected function enqueueBlockStyles(callable $callable, int $priority = 30): void
    {
        $this->addAssetAction('init', $callable, $priority);
    }

    private function addAssetAction(string $action, callable $callable, int $priority): void
    {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        add_action($action, fn() => $callable($this->assets()), $priority);
    }

}
