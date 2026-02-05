<?php

declare(strict_types=1);

namespace Sitchco\Tests\Modules;

use Sitchco\Modules\CacheInvalidation\CacheInvalidation;
use Sitchco\Modules\CacheInvalidation\CacheScheduler;
use Sitchco\Modules\CacheInvalidation\Invalidators\ObjectCacheInvalidator;
use Sitchco\Tests\TestCase;

class CacheInvalidationTest extends TestCase
{
    private CacheInvalidation $module;
    private ObjectCacheInvalidator $invalidator;

    private CacheScheduler $scheduler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->module = $this->container->get(CacheInvalidation::class);
        $this->scheduler = $this->container->get(CacheScheduler::class);
        $this->invalidator = $this->container->get(ObjectCacheInvalidator::class);
        foreach ($this->scheduler->getActiveInvalidators() as $inv) {
            $inv->resetFlag();
        }
    }

    // --- Group 1: Invalidator Flagging ---

    public function test_zero_delay_invalidator_is_not_flagged_after_trigger(): void
    {
        do_action('sitchco/cache/clear_all');
        $this->assertFalse(
            $this->invalidator->isFlagged(),
            'Zero-delay invalidator should flush inline, not flag for later scheduling',
        );
    }

    public function test_flagging_is_blocked_when_should_flag_filter_returns_false(): void
    {
        add_filter('sitchco/cache/should_flag', '__return_false');
        do_action('sitchco/cache/clear_all');
        $this->assertFalse(
            $this->invalidator->isFlagged(),
            'Invalidator should not be flagged when should_flag filter returns false',
        );
    }

    public function test_zero_delay_invalidator_flushes_inline_on_trigger(): void
    {
        // Set a known cache value
        wp_cache_set('test_key', 'test_value');

        // Fire a trigger that ObjectCacheInvalidator listens to
        do_action('sitchco/cache/clear_all');

        // Object cache should already be flushed — no need to wait for cron
        $this->assertFalse(
            wp_cache_get('test_key'),
            'Zero-delay invalidator should flush inline when triggered, not wait for cron',
        );
    }

    public function test_zero_delay_invalidator_does_not_enter_queue_on_trigger(): void
    {
        do_action('sitchco/cache/clear_all');
        $this->module->scheduleFlaggedInvalidators();

        $queue = get_option(CacheScheduler::OPTION_NAME);
        if (is_array($queue)) {
            $ids = array_column($queue, 'id');
            $this->assertNotContains(
                'object_cache',
                $ids,
                'Zero-delay invalidator should not appear in queue after trigger',
            );
        } else {
            $this->assertFalse($queue, 'No queue should be created when only zero-delay invalidators triggered');
        }
    }

    // --- Group 2: Scheduling Queue ---

    public function test_schedule_flagged_invalidators_creates_queue(): void
    {
        $this->invalidator->flag();
        $this->module->scheduleFlaggedInvalidators();

        $queue = get_option(CacheScheduler::OPTION_NAME);
        $this->assertIsArray($queue);
        $this->assertCount(1, $queue);
        $this->assertSame('object_cache', $queue[0]['id']);
        $this->assertSame(0, $queue[0]['priority']);
        $this->assertSame(0, $queue[0]['delay']);
        $this->assertEqualsWithDelta(time(), $queue[0]['expires'], 2);
    }

    public function test_no_queue_created_when_nothing_flagged(): void
    {
        $this->module->scheduleFlaggedInvalidators();
        $this->assertFalse(
            get_option(CacheScheduler::OPTION_NAME),
            'No queue should be created when nothing is flagged',
        );
    }

    // --- Group 3: Queue Processing ---

    public function test_processes_expired_queue_item(): void
    {
        update_option(
            CacheScheduler::OPTION_NAME,
            [['id' => 'object_cache', 'expires' => time() - 1, 'priority' => 0, 'delay' => 0]],
            false,
        );

        $flushedClass = null;
        add_action('sitchco/cache/flushed', function (string $class) use (&$flushedClass) {
            $flushedClass = $class;
        });

        do_action('sitchco/cron/minutely');

        $this->assertSame(
            ObjectCacheInvalidator::class,
            $flushedClass,
            'Flushed action should fire with ObjectCacheInvalidator class',
        );
    }

    public function test_skips_unexpired_queue_item(): void
    {
        update_option(
            CacheScheduler::OPTION_NAME,
            [['id' => 'object_cache', 'expires' => time() + 9999, 'priority' => 0, 'delay' => 0]],
            false,
        );

        $flushed = false;
        add_action('sitchco/cache/flushed', function () use (&$flushed) {
            $flushed = true;
        });

        do_action('sitchco/cron/minutely');

        $this->assertFalse($flushed, 'Flushed action should not fire for unexpired item');
        $this->assertNotFalse(get_option(CacheScheduler::OPTION_NAME), 'Queue should still exist');
    }

    public function test_resets_remaining_item_expiration_after_processing(): void
    {
        update_option(
            CacheScheduler::OPTION_NAME,
            [
                ['id' => 'object_cache', 'expires' => time() - 1, 'priority' => 0, 'delay' => 0],
                ['id' => 'wp_rocket', 'expires' => time() + 9999, 'priority' => 10, 'delay' => 120],
            ],
            false,
        );

        do_action('sitchco/cron/minutely');

        $queue = get_option(CacheScheduler::OPTION_NAME);
        $this->assertIsArray($queue);
        $this->assertCount(1, $queue);
        $this->assertSame('wp_rocket', $queue[0]['id']);
        $this->assertEqualsWithDelta(time() + 120, $queue[0]['expires'], 2);
    }

    public function test_fires_complete_action_when_queue_fully_processed(): void
    {
        update_option(
            CacheScheduler::OPTION_NAME,
            [['id' => 'object_cache', 'expires' => time() - 1, 'priority' => 0, 'delay' => 0]],
            false,
        );

        $completed = false;
        add_action('sitchco/cache/complete', function () use (&$completed) {
            $completed = true;
        });

        do_action('sitchco/cron/minutely');

        $this->assertTrue($completed, 'Complete action should fire when queue is fully processed');
    }

    public function test_deletes_option_when_queue_fully_processed(): void
    {
        update_option(
            CacheScheduler::OPTION_NAME,
            [['id' => 'object_cache', 'expires' => time() - 1, 'priority' => 0, 'delay' => 0]],
            false,
        );

        do_action('sitchco/cron/minutely');

        $this->assertFalse(
            get_option(CacheScheduler::OPTION_NAME),
            'Queue option should be deleted after full processing',
        );
    }

    // --- Group 5: Cascade Suppression ---

    public function test_hooks_fired_during_flush_do_not_reflag_invalidators(): void
    {
        // Set up a queue with just object_cache (expired, ready to process)
        update_option(
            CacheScheduler::OPTION_NAME,
            [['id' => 'object_cache', 'expires' => time() - 1, 'priority' => 0, 'delay' => 0]],
            false,
        );

        // Simulate the cascade: when object_cache flushes, a hook fires that would
        // normally flag other invalidators (like WP Rocket's hooks re-flagging things)
        add_action('sitchco/cache/flushed', function () {
            do_action('sitchco/cache/clear_all');
        });

        do_action('sitchco/cron/minutely');

        // No invalidators should be flagged after processing — the cascade should be suppressed
        $flagged = array_filter($this->scheduler->getActiveInvalidators(), fn($i) => $i->isFlagged());
        $this->assertEmpty(
            $flagged,
            'Invalidators should not be re-flagged by hooks fired during scheduled flush processing',
        );
    }

    // --- Group 4: Clear All ---

    public function test_clear_scheduled_invalidations_removes_queue(): void
    {
        update_option(
            CacheScheduler::OPTION_NAME,
            [['id' => 'object_cache', 'expires' => time() + 9999, 'priority' => 0, 'delay' => 0]],
            false,
        );

        $this->module->clearScheduledInvalidations();

        $this->assertFalse(
            get_option(CacheScheduler::OPTION_NAME),
            'Queue should be removed after clearScheduledInvalidations',
        );
    }
}
