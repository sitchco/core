<?php

declare(strict_types=1);

namespace Sitchco\Modules\CacheInvalidation;

class CloudFrontInvalidator extends Invalidator
{
    public function slug(): string
    {
        return 'cloudfront';
    }

    protected function checkAvailability(): bool
    {
        return class_exists('CloudFront_Clear_Cache') &&
            method_exists('CloudFront_Clear_Cache', 'get_instance') &&
            method_exists('CloudFront_Clear_Cache', 'c3_invalidation');
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
