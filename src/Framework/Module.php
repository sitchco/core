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

    /**
     * Default initialization feature that is always called when module is activated
     * @return void
     */
    public function init()
    {
    }

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

    protected function scriptUrl(string $relative): string
    {
        return $this->path('assets/scripts')->append($relative)->url();
    }

    protected function styleUrl(string $relative): string
    {
        return $this->path('assets/styles')->append($relative)->url();
    }

}
