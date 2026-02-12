<?php

declare(strict_types=1);

namespace Sitchco\Modules\CacheInvalidation;

use Sitchco\Utils\Hooks;
use Sitchco\Utils\Logger;

/**
 * Cache invalidation queue with two independent sides:
 *
 * Writer — buffers queue entries per-request and flushes once at shutdown.
 * Processor — reads queue on cron, processes expired items, resets remaining timestamps.
 *
 * Both sides share a single wp_options row. A processing guard suppresses
 * queue writes during flush execution to prevent re-entrant triggers.
 */
class CacheQueue
{
    public const OPTION_NAME = 'sitchco_cache_queue';

    private bool $processing = false;

    /** @var array<string, Invalidator> slug -> invalidator instance */
    private array $slugMap = [];

    /** @var PendingInvalidation[]|null */
    private ?array $pendingWrite = null;

    private bool $shutdownRegistered = false;

    public function isProcessing(): bool
    {
        return $this->processing;
    }

    /**
     * Register invalidators so slugs can be resolved during cron processing.
     *
     * @param Invalidator[] $invalidators
     */
    public function registerInvalidators(array $invalidators): void
    {
        foreach ($invalidators as $invalidator) {
            $this->slugMap[$invalidator->slug()] = $invalidator;
        }
    }

    /**
     * Buffer a queue write for the given invalidators.
     *
     * Filters by availability, sorts by priority, computes timestamps.
     * The actual DB write happens at shutdown via flushWriteBuffer().
     * No-ops if the processing guard is active or no invalidators are available.
     *
     * @param Invalidator[] $invalidators
     */
    public function write(array $invalidators): void
    {
        if ($this->processing) {
            Logger::debug('[Cache] Queue write suppressed during processing');
            return;
        }

        $available = array_filter($invalidators, fn($i) => $i->isAvailable());
        if (empty($available)) {
            return;
        }

        usort($available, fn($a, $b) => $a->priority() <=> $b->priority());

        $now = time();
        $this->pendingWrite = array_map(
            fn(Invalidator $invalidator) => PendingInvalidation::fromInvalidator($invalidator, $now),
            $available,
        );

        if (!$this->shutdownRegistered) {
            add_action('shutdown', [$this, 'flushWriteBuffer']);
            $this->shutdownRegistered = true;
        }
    }

    /**
     * Flush the buffered queue write to wp_options.
     * Called once at shutdown via the WordPress shutdown hook.
     */
    public function flushWriteBuffer(): void
    {
        if ($this->pendingWrite === null) {
            return;
        }

        $slugs = array_column($this->pendingWrite, 'slug');
        Logger::debug('[Cache] Queue written: ' . implode(', ', $slugs));
        update_option(
            self::OPTION_NAME,
            array_map(fn(PendingInvalidation $item) => $item->toArray(), $this->pendingWrite),
            false,
        );
        $this->pendingWrite = null;
    }

    /**
     * Process the next expired item in the queue.
     * Called by minutely cron.
     */
    public function process(): void
    {
        $rawQueue = get_option(self::OPTION_NAME, []);

        if (empty($rawQueue)) {
            return;
        }

        $queue = array_values(
            array_filter(
                array_map(function (mixed $row) {
                    if (!is_array($row)) {
                        Logger::warning('[Cache] Dropping non-array queue entry');
                        return null;
                    }
                    return PendingInvalidation::fromArray($row);
                }, $rawQueue),
            ),
        );

        if (empty($queue)) {
            delete_option(self::OPTION_NAME);
            return;
        }

        $now = time();
        $first = $queue[0];

        if (!$first->isExpired($now)) {
            return;
        }

        array_shift($queue);

        $invalidator = $this->resolveInvalidator($first->slug);

        if ($invalidator !== null) {
            Logger::debug("[Cache] Processing {$first->slug}, " . count($queue) . ' remaining');
            $this->processing = true;

            try {
                $invalidator->flush();
                Logger::debug("[Cache] {$first->slug} flushed successfully");
            } catch (\Throwable $e) {
                Logger::error("[Cache] Flush failed for {$first->slug}: {$e->getMessage()}");
            } finally {
                $this->processing = false;
            }

            $queue = array_map(fn(PendingInvalidation $item) => $item->refresh($now), $queue);
        } else {
            Logger::warning("[Cache] Unknown invalidator slug '{$first->slug}', removing from queue");
        }

        if (empty($queue)) {
            delete_option(self::OPTION_NAME);
            Logger::debug('[Cache] Cascade complete');
            do_action(Hooks::name('cache', 'complete'));
        } else {
            update_option(
                self::OPTION_NAME,
                array_map(fn(PendingInvalidation $item) => $item->toArray(), $queue),
                false,
            );
        }
    }

    /**
     * Resolve a slug to a registered invalidator instance for flush execution.
     */
    private function resolveInvalidator(string $slug): ?Invalidator
    {
        return $this->slugMap[$slug] ?? null;
    }
}
