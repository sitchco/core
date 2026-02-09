<?php

declare(strict_types=1);

namespace Sitchco\Modules\CacheInvalidation;

class CloudFrontInvalidator implements Invalidator
{
    public function slug(): string
    {
        return 'cloudfront';
    }

    public function isAvailable(): bool
    {
        return CacheCondition::CloudFrontInstalled->check();
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
        \CloudFront_Clear_Cache::get_instance()->c3_invalidation();
    }
}
