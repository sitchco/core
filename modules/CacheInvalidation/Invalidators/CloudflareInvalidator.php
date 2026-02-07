<?php

declare(strict_types=1);

namespace Sitchco\Modules\CacheInvalidation\Invalidators;

use Sitchco\Modules\CacheInvalidation\AbstractInvalidator;
use Sitchco\Modules\CacheInvalidation\CacheCondition;
use Sitchco\Modules\CacheInvalidation\CacheTrigger;

/**
 * Invalidates Cloudflare CDN cache.
 *
 * Priority 100, 100s delay - runs last in the cascade.
 */
class CloudflareInvalidator extends AbstractInvalidator
{
    private const PURGE_ACTION = 'sitchco/cloudflare_purge_cache';

    public function init(): void
    {
        // Register our action with Cloudflare before the plugin loads its listeners
        add_filter('cloudflare_purge_everything_actions', fn(array $actions) => [...$actions, self::PURGE_ACTION]);
        parent::init();
    }

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
        return [CacheCondition::CloudflareInstalled];
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
