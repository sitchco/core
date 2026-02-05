<?php

declare(strict_types=1);

namespace Sitchco\Modules\CacheInvalidation;

/**
 * Interface for cache invalidation implementations.
 *
 * Each invalidator handles a specific cache layer (object cache, WP Rocket, CDN, etc.)
 * and defines when it should be triggered, under what conditions, and with what timing.
 */
interface Invalidator
{
    /**
     * Returns the trigger types that should activate this invalidator.
     *
     * @return CacheTrigger[]
     */
    public function triggers(): array;

    /**
     * Returns conditions that must be met for this invalidator to be active.
     *
     * @return CacheCondition[]
     */
    public function conditions(): array;

    /**
     * Returns the priority for processing order (lower = earlier).
     */
    public function priority(): int;

    /**
     * Returns the delay in seconds before this invalidator should run.
     */
    public function delay(): int;

    /**
     * Initialize the invalidator - register listeners for triggers.
     */
    public function init(): void;

    /**
     * Flag this invalidator for execution at shutdown.
     */
    public function flag(): void;

    /**
     * Check if this invalidator has been flagged for execution.
     */
    public function isFlagged(): bool;

    /**
     * Check if all conditions are met for this invalidator to run.
     */
    public function shouldRun(): bool;

    /**
     * Reset the flagged state.
     */
    public function resetFlag(): void;

    /**
     * Perform the actual cache flush operation.
     */
    public function flush(): void;
}
