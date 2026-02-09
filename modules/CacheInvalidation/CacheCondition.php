<?php

declare(strict_types=1);

namespace Sitchco\Modules\CacheInvalidation;

enum CacheCondition: string
{
    case RocketActive = 'rocket_active';
    case CloudflareInstalled = 'cloudflare_installed';
    case CloudFrontInstalled = 'cloudfront_installed';

    public function check(): bool
    {
        $result = match ($this) {
            self::RocketActive => function_exists('rocket_clean_domain'),
            self::CloudflareInstalled => defined('CLOUDFLARE_PLUGIN_DIR'),
            self::CloudFrontInstalled => class_exists('CloudFront_Clear_Cache') &&
                method_exists('CloudFront_Clear_Cache', 'get_instance') &&
                method_exists('CloudFront_Clear_Cache', 'c3_invalidation'),
        };

        return (bool) apply_filters('sitchco/cache/condition/' . $this->value, $result);
    }
}
