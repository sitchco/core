<?php

declare(strict_types=1);

namespace Sitchco\Modules\CacheInvalidation;

/**
 * Defines conditions for plugin/service availability checks.
 */
enum CacheCondition: string
{
    case RocketActive = 'rocket_active';
    case CloudflareInstalled = 'cloudflare_installed';
    case CloudFrontInstalled = 'cloudfront_installed';

    /**
     * Check if this condition is currently met.
     */
    public function check(): bool
    {
        return match ($this) {
            self::RocketActive => function_exists('rocket_clean_domain'),
            self::CloudflareInstalled => defined('CLOUDFLARE_PLUGIN_DIR'),
            self::CloudFrontInstalled => class_exists('CloudFront_Clear_Cache') &&
                method_exists('CloudFront_Clear_Cache', 'get_instance') &&
                method_exists('CloudFront_Clear_Cache', 'c3_invalidation'),
        };
    }
}
