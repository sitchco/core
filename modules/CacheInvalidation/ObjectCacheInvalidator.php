<?php

declare(strict_types=1);

namespace Sitchco\Modules\CacheInvalidation;

class ObjectCacheInvalidator implements Invalidator
{
    public function slug(): string
    {
        return 'object_cache';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function priority(): int
    {
        return 0;
    }

    public function delay(): int
    {
        return 10;
    }

    public function flush(): void
    {
        wp_cache_flush();
    }
}
