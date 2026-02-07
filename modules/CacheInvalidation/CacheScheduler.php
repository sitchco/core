<?php

declare(strict_types=1);

namespace Sitchco\Modules\CacheInvalidation;

use DI\Container;
use Sitchco\Modules\CacheInvalidation\Invalidators\CloudflareInvalidator;
use Sitchco\Modules\CacheInvalidation\Invalidators\CloudFrontInvalidator;
use Sitchco\Modules\CacheInvalidation\Invalidators\ObjectCacheInvalidator;
use Sitchco\Modules\CacheInvalidation\Invalidators\WPRocketInvalidator;
use Sitchco\Utils\Hooks;
use Sitchco\Utils\Logger;

/**
 * Manages the cache invalidation queue and scheduled processing.
 *
 * Uses wp_options for queue storage with staggered timing via minutely cron.
 */
class CacheScheduler
{
    public const OPTION_NAME = 'sitchco_cache_scheduled_invalidations';

    /** @var array<string, class-string<Invalidator>> */
    private const INVALIDATOR_CLASSES = [
        'object_cache' => ObjectCacheInvalidator::class,
        'wp_rocket' => WPRocketInvalidator::class,
        'cloudfront' => CloudFrontInvalidator::class,
        'cloudflare' => CloudflareInvalidator::class,
    ];

    /** @var array<string, Invalidator> Lazily populated */
    private array $invalidators = [];

    public function __construct(private Container $container) {}

    /**
     * Initialize the scheduler - subscribe to minutely cron.
     */
    public function init(): void
    {
        add_action(Hooks::name('cron', 'minutely'), [$this, 'processScheduledInvalidations']);
    }

    /**
     * Get an invalidator by its ID.
     */
    public function getInvalidator(string $id): ?Invalidator
    {
        if (!isset(self::INVALIDATOR_CLASSES[$id])) {
            return null;
        }
        if (!isset($this->invalidators[$id])) {
            $this->invalidators[$id] = $this->container->get(self::INVALIDATOR_CLASSES[$id]);
        }
        return $this->invalidators[$id];
    }

    /**
     * Get the ID for an invalidator class.
     */
    public function getInvalidatorId(string $className): ?string
    {
        return array_search($className, self::INVALIDATOR_CLASSES, true) ?: null;
    }

    /**
     * Get all invalidators where shouldRun() returns true.
     *
     * @return Invalidator[]
     */
    public function getActiveInvalidators(): array
    {
        return array_filter(
            array_map(fn($id) => $this->getInvalidator($id), array_keys(self::INVALIDATOR_CLASSES)),
            fn(?Invalidator $i) => $i !== null && $i->shouldRun(),
        );
    }

    /**
     * Schedule flagged invalidators for processing.
     *
     * @param Invalidator[] $invalidators
     */
    public function schedule(array $invalidators): void
    {
        if (empty($invalidators)) {
            return;
        }

        // Sort by priority
        usort($invalidators, fn(Invalidator $a, Invalidator $b) => $a->priority() <=> $b->priority());

        $now = time();
        $queue = [];

        foreach ($invalidators as $invalidator) {
            $id = $this->getInvalidatorId($invalidator::class);
            if ($id === null) {
                continue;
            }

            $queue[] = [
                'id' => $id,
                'expires' => $now + $invalidator->delay(),
                'priority' => $invalidator->priority(),
                'delay' => $invalidator->delay(),
            ];
        }

        // Queue overwrite is intentional - new triggers restart the cascade
        update_option(self::OPTION_NAME, $queue, false);
    }

    /**
     * Process scheduled invalidations from the queue.
     *
     * Called by minutely cron. Processes one invalidator if its time has expired,
     * then resets remaining items' expiration times.
     */
    public function processScheduledInvalidations(): void
    {
        $queue = get_option(self::OPTION_NAME, []);

        if (empty($queue)) {
            return;
        }

        $now = time();
        $first = $queue[0];

        // Not yet time to process
        if ($first['expires'] > $now) {
            return;
        }

        // Remove first item from queue
        array_shift($queue);

        // Look up and process the invalidator
        $invalidator = $this->getInvalidator($first['id']);

        if ($invalidator !== null) {
            Logger::debug("[Cache] Processing {$first['id']}, " . count($queue) . ' remaining in queue');
            $this->processInvalidator($invalidator);
        }

        // Reset remaining items' expires to now + their delay
        foreach ($queue as &$item) {
            $item['expires'] = $now + $item['delay'];
        }
        unset($item);

        if (empty($queue)) {
            delete_option(self::OPTION_NAME);
            Logger::debug('[Cache] Queue complete, all invalidators processed');
            do_action(CacheInvalidation::hookName('complete'));
        } else {
            $remaining = array_column($queue, 'id');
            Logger::debug('[Cache] Queue updated, remaining: ' . implode(', ', $remaining));
            update_option(self::OPTION_NAME, $queue, false);
        }
    }

    /**
     * Clear all scheduled invalidations.
     */
    public function clearScheduledInvalidations(): void
    {
        delete_option(self::OPTION_NAME);
    }

    /**
     * Process a single invalidator with error handling.
     */
    private function processInvalidator(Invalidator $invalidator): void
    {
        $name = substr(strrchr($invalidator::class, '\\'), 1);
        // Suppress flagging during the entire processing pipeline to prevent cascading
        // re-triggers (e.g. WP Rocket's rocket_clean_domain() firing before/after hooks)
        add_filter(CacheInvalidation::hookName('should_flag'), '__return_false');
        try {
            $invalidator->flush();
            Logger::debug("[Cache] {$name} flushed successfully");
            do_action(CacheInvalidation::hookName('flushed'), $invalidator::class);
        } catch (\Throwable $e) {
            Logger::error("[Cache] Flush failed for {$name}: {$e->getMessage()}");
        } finally {
            remove_filter(CacheInvalidation::hookName('should_flag'), '__return_false');
        }
    }
}
