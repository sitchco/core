<?php

declare(strict_types=1);

namespace Sitchco\Modules\CacheInvalidation\Invalidators;

use Sitchco\Modules\CacheInvalidation\AbstractInvalidator;
use Sitchco\Modules\CacheInvalidation\CacheCondition;
use Sitchco\Modules\CacheInvalidation\CacheTrigger;

/**
 * Invalidates the WordPress object cache (Redis, Memcached, etc.).
 *
 * Priority 0, no delay - runs first and immediately.
 */
class ObjectCacheInvalidator extends AbstractInvalidator
{
    public function triggers(): array
    {
        return [
            CacheTrigger::PostDeployment,
            CacheTrigger::ContentChange,
            CacheTrigger::ManualClear,
            CacheTrigger::BeforeRocketClean,
        ];
    }

    public function conditions(): array
    {
        return [];
    }

    public function priority(): int
    {
        return 0;
    }

    public function delay(): int
    {
        return 0;
    }

    public function flush(): void
    {
        wp_cache_flush();
    }
}
