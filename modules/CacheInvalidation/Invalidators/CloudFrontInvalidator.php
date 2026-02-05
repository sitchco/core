<?php

declare(strict_types=1);

namespace Sitchco\Modules\CacheInvalidation\Invalidators;

use Sitchco\Modules\CacheInvalidation\AbstractInvalidator;
use Sitchco\Modules\CacheInvalidation\CacheCondition;
use Sitchco\Modules\CacheInvalidation\CacheTrigger;

/**
 * Invalidates AWS CloudFront CDN cache.
 *
 * Priority 50, 100s delay - runs after WP Rocket with additional stagger.
 */
class CloudFrontInvalidator extends AbstractInvalidator
{
    public function triggers(): array
    {
        return [
            CacheTrigger::PostDeployment,
            CacheTrigger::ContentChange,
            CacheTrigger::ManualClear,
            CacheTrigger::AfterRocketClean,
        ];
    }

    public function conditions(): array
    {
        return [CacheCondition::CloudFrontInstalled];
    }

    public function priority(): int
    {
        return 50;
    }

    public function delay(): int
    {
        return 100;
    }

    public function flush(): void
    {
        if (
            class_exists('CloudFront_Clear_Cache') &&
            method_exists('CloudFront_Clear_Cache', 'get_instance') &&
            method_exists('CloudFront_Clear_Cache', 'c3_invalidation')
        ) {
            \CloudFront_Clear_Cache::get_instance()->c3_invalidation();
        }
    }
}
