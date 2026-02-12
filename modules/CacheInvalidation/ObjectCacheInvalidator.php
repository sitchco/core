<?php

declare(strict_types=1);

namespace Sitchco\Modules\CacheInvalidation;

class ObjectCacheInvalidator extends Invalidator
{
    public function slug(): string
    {
        return 'object_cache';
    }

    protected function checkAvailability(): bool
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
