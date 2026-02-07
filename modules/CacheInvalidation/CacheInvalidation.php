<?php

declare(strict_types=1);

namespace Sitchco\Modules\CacheInvalidation;

use Sitchco\Framework\Module;
use Sitchco\Modules\Cron;
use Sitchco\Modules\PostLifecycle;
use Sitchco\Utils\Logger;

/**
 * Cache invalidation orchestration module.
 *
 * Coordinates cache invalidation across multiple backends (object cache, WP Rocket, CDN)
 * using a staggered timing model to ensure proper cache layer sequencing.
 *
 * Hooks provided:
 * - sitchco/cache/clear_all    - Fire this action to manually trigger cache invalidation
 * - sitchco/cache/should_flag  - Filter to disable flagging during maintenance
 * - sitchco/cache/flushed      - Fired after each invalidator completes
 * - sitchco/cache/complete     - Fired after entire queue is processed
 */
class CacheInvalidation extends Module
{
    public const DEPENDENCIES = [Cron::class, PostLifecycle::class];
    public const HOOK_SUFFIX = 'cache';

    public function __construct(private CacheScheduler $scheduler) {}

    public function init(): void
    {
        // Initialize the scheduler (subscribes to minutely cron)
        $this->scheduler->init();

        // Initialize all active invalidators (registers their trigger listeners)
        $activeInvalidators = $this->scheduler->getActiveInvalidators();

        foreach ($activeInvalidators as $invalidator) {
            $invalidator->init();
        }

        // At shutdown, schedule any flagged invalidators
        add_action('shutdown', [$this, 'scheduleFlaggedInvalidators']);
    }

    /**
     * Schedule all flagged invalidators for processing.
     *
     * Called at shutdown to batch all triggered invalidators into a single queue.
     */
    public function scheduleFlaggedInvalidators(): void
    {
        $flagged = array_filter(
            $this->scheduler->getActiveInvalidators(),
            fn(Invalidator $i) => $i->isFlagged() && $i->delay() > 0,
        );

        if (!empty($flagged)) {
            $names = array_map(fn(Invalidator $i) => substr(strrchr($i::class, '\\'), 1), $flagged);
            Logger::debug('[Cache] Scheduling flagged invalidators at shutdown: ' . implode(', ', $names));
            $this->scheduler->schedule($flagged);
        }
    }

    /**
     * Clear all scheduled invalidations.
     *
     * Use this as a fallback during maintenance to wipe the queue entirely.
     */
    public function clearScheduledInvalidations(): void
    {
        $this->scheduler->clearScheduledInvalidations();
    }
}
