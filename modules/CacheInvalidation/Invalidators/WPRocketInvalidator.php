<?php

declare(strict_types=1);

namespace Sitchco\Modules\CacheInvalidation\Invalidators;

use Sitchco\Modules\CacheInvalidation\AbstractInvalidator;
use Sitchco\Modules\CacheInvalidation\CacheCondition;
use Sitchco\Modules\CacheInvalidation\CacheTrigger;

/**
 * Invalidates WP Rocket page cache.
 *
 * Priority 10, 50s delay - runs after object cache with a stagger.
 * Does NOT participate in WP Rocket admin UI clears (BeforeRocketClean/AfterRocketClean)
 * since those are already handled by WP Rocket itself.
 */
class WPRocketInvalidator extends AbstractInvalidator
{
    public function triggers(): array
    {
        return [CacheTrigger::PostDeployment, CacheTrigger::ContentChange, CacheTrigger::ManualClear];
    }

    public function conditions(): array
    {
        return [CacheCondition::RocketActive];
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
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
    }
}
