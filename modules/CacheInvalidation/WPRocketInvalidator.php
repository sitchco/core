<?php

declare(strict_types=1);

namespace Sitchco\Modules\CacheInvalidation;

class WPRocketInvalidator extends Invalidator
{
    public function slug(): string
    {
        return 'wp_rocket';
    }

    protected function checkAvailability(): bool
    {
        return function_exists('rocket_clean_domain');
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
