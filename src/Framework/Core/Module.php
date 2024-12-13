<?php

namespace Sitchco\Framework\Core;

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
    // NOTE: use of a trait would allow registration of a class that uses a different parent class. For example, some services
    // extend \WP_Background_Process, this would allow those services to become a registrable module without requiring separate
    // wrappers to instantiate. We may rethink this if it's a poor design pattern.
    use Registrable;
}