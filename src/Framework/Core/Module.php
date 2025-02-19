<?php

namespace Sitchco\Framework\Core;

use Sitchco\Support\HasHooks;
use Sitchco\Utils\Hooks;

/**
 * Abstract base class for modules in the Sitchco framework.
 *
 * Modules extending this class can provide a range of functionalities,
 * from adding simple WordPress filters to complex configurations including
 * custom post types, ACF fields, and Gutenberg blocks.
 *
 * Each module can be conditionally enabled or configured based on theme support.
 * 
 */
abstract class Module
{
    /**
     * Set a descriptive name for the module to use in building action/filter hooks
     */
    public const HOOK_NAME = '';

    /**
     * An array of other modules that this module depends on to function.
     * These modules will automatically be registered immediately before this module and configured to activate.
     * Each key is the fully-qualified class name of the module
     *
     * @example [OtherModule1::class, OtherModule2::class]
     *
     */
    public const DEPENDENCIES = [];

    /**
     * An array of features provided by this module.
     *
     * Each feature is a key-value pair, where:
     * - The key is a string representing the feature name, which maps to a method.
     * - The value is a boolean that indicates if the feature is enabled by default.
     *
     * The feature keys should match method names in the class for dynamic execution.
     *
     * @example ['disable_admin_bar' => true, 'custom_post_type' => false]
     *
     * @var array<string, bool>
     */
    public const FEATURES = [];

    /**
     * An array of Timber custom post classes associated with this module.
     *
     * Each entry is a fully qualified class name for the Timber post model.
     *
     * The Registry will automatically add these classes to the `timber/post/classmap`.
     *
     * @example [\Sitchco\Models\PortfolioPost::class]
     *
     * @var array<string>
     */
    public const POST_CLASSES = [];

    /**
     * An array of paths or identifiers for associated Gutenberg blocks.
     *
     * These blocks will be registered by the Registry, enabling each module
     * to encapsulate its own Gutenberg block configurations.
     *
     * @example ['blocks/portfolio-gallery', 'blocks/portfolio-slider']
     *
     * @var array<string>
     */
    public const BLOCKS = [];
    
    protected string $basePath;

    /**
     * Default initialization feature that is always called when module is activated
     *
     * @return void
     */
    public function init() {}

    protected function getModuleBasePath(): ?string
    {
        if (empty($this->basePath)) {
            $reflector = new \ReflectionClass($this);
            $this->basePath = trailingslashit(dirname($reflector->getFileName()));
        }

        return $this->basePath;
    }

    public function getAcfJsonPaths(): array
    {
        return array_filter([realpath($this->getModuleBasePath() . 'acf-json')]);
    }

    protected static function hookName(...$name_parts): string
    {
        return Hooks::name(static::HOOK_NAME, ...$name_parts);
    }

}