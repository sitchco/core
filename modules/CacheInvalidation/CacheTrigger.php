<?php

declare(strict_types=1);

namespace Sitchco\Modules\CacheInvalidation;

use Sitchco\Modules\PostDeployment;
use Sitchco\Modules\PostLifecycle;

/**
 * Defines the types of events that can trigger cache invalidation.
 */
enum CacheTrigger: string
{
    case PostDeployment = 'post_deployment';
    case ContentChange = 'content_change';
    case ManualClear = 'manual_clear';
    case BeforeRocketClean = 'before_rocket';
    case AfterRocketClean = 'after_rocket';

    /**
     * Returns the WordPress hooks that should trigger this cache trigger type.
     *
     * @return string[]
     */
    public function hooks(): array
    {
        return match ($this) {
            self::PostDeployment => [PostDeployment::hookName('complete')],
            self::ContentChange => [
                PostLifecycle::hookName('content_updated'),
                PostLifecycle::hookName('visibility_changed'),
                'acf/save_post',
                'wpsdb_migration_complete',
            ],
            self::ManualClear => [CacheInvalidation::hookName('clear_all')],
            self::BeforeRocketClean => ['before_rocket_clean_domain'],
            self::AfterRocketClean => ['after_rocket_clean_domain'],
        };
    }
}
