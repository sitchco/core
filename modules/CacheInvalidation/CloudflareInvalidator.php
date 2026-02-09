<?php

declare(strict_types=1);

namespace Sitchco\Modules\CacheInvalidation;

class CloudflareInvalidator implements Invalidator
{
    public const PURGE_ACTION = 'sitchco/cloudflare_purge_cache';

    public function slug(): string
    {
        return 'cloudflare';
    }

    public function isAvailable(): bool
    {
        return CacheCondition::CloudflareInstalled->check();
    }

    public function priority(): int
    {
        return 100;
    }

    public function delay(): int
    {
        return 100;
    }

    public function flush(): void
    {
        do_action(self::PURGE_ACTION);
    }
}
