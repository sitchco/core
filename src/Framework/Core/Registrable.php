<?php

namespace Sitchco\Framework\Core;

trait Registrable
{
    
    /**
     * The unique name of the module, used for theme support registration and identification.
     *
     * @var string
     */
    public const NAME = '';

    /**
     * Determines if this module should be enabled by default.
     *
     * If true, this module will add theme support automatically, but themes
     * can override this by removing theme support for the module.
     *
     * @var bool
     */
    public const DEFAULT = false;

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
     * The priority of this module when multiple modules share the same NAME.
     *
     * Modules with the same NAME will be resolved based on priority. The module
     * with the highest priority will be instantiated, while others will be ignored.
     *
     * This is a shorthand for deregister/re-register.
     *
     * @var int
     */
    public const PRIORITY = 10;

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

    /**
     * Registers this module with the central Registry.
     *
     * This method should be called by each module to add itself to the Registry,
     * which will then manage the initialization and configuration of the module
     * based on theme support and conditions.
     *
     * @return void
     */
    public static function register(): void
    {
        Registry::add(static::class);
    }

    public static function name(): string
    {
        return static::NAME ?: (new \ReflectionClass(static::class))->getShortName();
    }
}