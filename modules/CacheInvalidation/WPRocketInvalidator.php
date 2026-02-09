<?php

declare(strict_types=1);

namespace Sitchco\Modules\CacheInvalidation;

class WPRocketInvalidator implements Invalidator
{
    public function slug(): string
    {
        return 'wp_rocket';
    }

    public function isAvailable(): bool
    {
        return CacheCondition::RocketActive->check();
    }

    public function priority(): int
    {
        return 10;
    }

    public function delay(): int
    {
        return 50;
    }

    public function flush(): void
    {
        rocket_clean_domain();
    }
}
