<?php

namespace Sitchco\Tests\Modules;

use Sitchco\Modules\CacheInvalidation\CacheInvalidation;
use Sitchco\Modules\CacheInvalidation\CacheQueue;
use Sitchco\Tests\TestCase;
use Sitchco\Utils\Hooks;

class CacheInvalidationTest extends TestCase
{
    private CacheQueue $queue;

    private const SIGNAL_HOOKS = [
        'sitchco/post/content_updated',
        'sitchco/post/visibility_changed',
        'sitchco/acf/fields_saved',
        'sitchco/deploy/complete',
        'sitchco/cache/clear_all',
        'after_rocket_clean_domain',
        'before_rocket_clean_domain',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->queue = $this->container->get(CacheQueue::class);
        // Flush any pending write from bootstrap or previous test, then clear the option
        $this->queue->flushWriteBuffer();
        delete_option(CacheQueue::OPTION_NAME);
        // Remove bootstrap-registered signal hooks so each test controls its own routing
        foreach (self::SIGNAL_HOOKS as $hook) {
            remove_all_actions($hook);
        }
    }

    protected function tearDown(): void
    {
        delete_option(CacheQueue::OPTION_NAME);
        foreach (self::SIGNAL_HOOKS as $hook) {
            remove_all_actions($hook);
        }
        remove_all_filters('sitchco/cache/condition/rocket_active');
        remove_all_filters('sitchco/cache/condition/cloudflare_installed');
        remove_all_filters('sitchco/cache/condition/cloudfront_installed');
        parent::tearDown();
    }

    // ─── Group 1: Delegated Mode — Signal → Queue Routing ───

    public function test_delegated_content_updated_does_not_create_queue(): void
    {
        add_filter('sitchco/cache/condition/rocket_active', '__return_true');
        add_filter('sitchco/cache/condition/cloudflare_installed', '__return_true');
        add_filter('sitchco/cache/condition/cloudfront_installed', '__return_true');
        $module = new CacheInvalidation($this->queue);
        $module->init();

        do_action('sitchco/post/content_updated');
        do_action('sitchco/acf/fields_saved');

        $this->queue->flushWriteBuffer();
        $this->assertEmpty(get_option(CacheQueue::OPTION_NAME, []));
    }

    public function test_delegated_visibility_changed_queues_rocket_and_cdns(): void
    {
        add_filter('sitchco/cache/condition/rocket_active', '__return_true');
        add_filter('sitchco/cache/condition/cloudflare_installed', '__return_true');
        add_filter('sitchco/cache/condition/cloudfront_installed', '__return_true');
        $module = new CacheInvalidation($this->queue);
        $module->init();

        do_action('sitchco/post/visibility_changed');

        $this->queue->flushWriteBuffer();
        $slugs = array_column(get_option(CacheQueue::OPTION_NAME, []), 'slug');
        $this->assertSame(['wp_rocket', 'cloudfront', 'cloudflare'], $slugs);
    }

    public function test_delegated_deploy_complete_queues_rocket_and_cdns(): void
    {
        add_filter('sitchco/cache/condition/rocket_active', '__return_true');
        add_filter('sitchco/cache/condition/cloudflare_installed', '__return_true');
        add_filter('sitchco/cache/condition/cloudfront_installed', '__return_true');
        $module = new CacheInvalidation($this->queue);
        $module->init();

        do_action('sitchco/deploy/complete');

        $this->queue->flushWriteBuffer();
        $slugs = array_column(get_option(CacheQueue::OPTION_NAME, []), 'slug');
        $this->assertSame(['wp_rocket', 'cloudfront', 'cloudflare'], $slugs);
    }

    public function test_delegated_clear_all_queues_rocket_and_cdns(): void
    {
        add_filter('sitchco/cache/condition/rocket_active', '__return_true');
        add_filter('sitchco/cache/condition/cloudflare_installed', '__return_true');
        add_filter('sitchco/cache/condition/cloudfront_installed', '__return_true');
        $module = new CacheInvalidation($this->queue);
        $module->init();

        do_action('sitchco/cache/clear_all');

        $this->queue->flushWriteBuffer();
        $slugs = array_column(get_option(CacheQueue::OPTION_NAME, []), 'slug');
        $this->assertSame(['wp_rocket', 'cloudfront', 'cloudflare'], $slugs);
    }

    public function test_delegated_after_rocket_clean_queues_cdns_only(): void
    {
        add_filter('sitchco/cache/condition/rocket_active', '__return_true');
        add_filter('sitchco/cache/condition/cloudflare_installed', '__return_true');
        add_filter('sitchco/cache/condition/cloudfront_installed', '__return_true');
        $module = new CacheInvalidation($this->queue);
        $module->init();

        do_action('after_rocket_clean_domain');

        $this->queue->flushWriteBuffer();
        $slugs = array_column(get_option(CacheQueue::OPTION_NAME, []), 'slug');
        $this->assertSame(['cloudfront', 'cloudflare'], $slugs);
        $this->assertNotContains('wp_rocket', $slugs);
    }

    public function test_delegated_queue_excludes_unavailable_invalidators(): void
    {
        add_filter('sitchco/cache/condition/rocket_active', '__return_true');
        add_filter('sitchco/cache/condition/cloudfront_installed', '__return_true');
        add_filter('sitchco/cache/condition/cloudflare_installed', '__return_false');
        $module = new CacheInvalidation($this->queue);
        $module->init();

        do_action('sitchco/post/visibility_changed');

        $this->queue->flushWriteBuffer();
        $slugs = array_column(get_option(CacheQueue::OPTION_NAME, []), 'slug');
        $this->assertSame(['wp_rocket', 'cloudfront'], $slugs);
        $this->assertNotContains('cloudflare', $slugs);
    }

    // ─── Group 2: Standalone Mode — Signal → Queue Routing ───

    public function test_standalone_content_signal_queues_object_cache_and_cdns(): void
    {
        add_filter('sitchco/cache/condition/rocket_active', '__return_false');
        add_filter('sitchco/cache/condition/cloudflare_installed', '__return_true');
        add_filter('sitchco/cache/condition/cloudfront_installed', '__return_true');

        $expected = ['object_cache', 'cloudfront', 'cloudflare'];

        $signals = [
            'sitchco/post/content_updated',
            'sitchco/post/visibility_changed',
            'sitchco/acf/fields_saved',
            'sitchco/deploy/complete',
            'sitchco/cache/clear_all',
        ];

        foreach ($signals as $signal) {
            delete_option(CacheQueue::OPTION_NAME);
            $queue = $this->container->get(CacheQueue::class);
            $module = new CacheInvalidation($queue);
            $module->init();

            do_action($signal);

            $queue->flushWriteBuffer();
            $slugs = array_column(get_option(CacheQueue::OPTION_NAME, []), 'slug');
            $this->assertSame($expected, $slugs, "Signal {$signal} should queue object_cache + CDNs");

            // Clean up signal hooks before next iteration
            remove_all_actions($signal);
        }
    }

    public function test_standalone_queue_excludes_rocket(): void
    {
        add_filter('sitchco/cache/condition/rocket_active', '__return_false');
        add_filter('sitchco/cache/condition/cloudflare_installed', '__return_true');
        add_filter('sitchco/cache/condition/cloudfront_installed', '__return_true');
        $module = new CacheInvalidation($this->queue);
        $module->init();

        do_action('sitchco/post/content_updated');

        $this->queue->flushWriteBuffer();
        $slugs = array_column(get_option(CacheQueue::OPTION_NAME, []), 'slug');
        $this->assertNotContains('wp_rocket', $slugs);
    }

    public function test_standalone_queue_excludes_unavailable_cdns(): void
    {
        add_filter('sitchco/cache/condition/rocket_active', '__return_false');
        add_filter('sitchco/cache/condition/cloudflare_installed', '__return_false');
        add_filter('sitchco/cache/condition/cloudfront_installed', '__return_false');
        $module = new CacheInvalidation($this->queue);
        $module->init();

        do_action('sitchco/post/content_updated');

        $this->queue->flushWriteBuffer();
        $slugs = array_column(get_option(CacheQueue::OPTION_NAME, []), 'slug');
        $this->assertSame(['object_cache'], $slugs);
    }

    // ─── Group 3: Delegated Mode — Sync Object Cache Flush ───

    public function test_delegated_sync_flushes_object_cache_on_before_rocket_clean(): void
    {
        add_filter('sitchco/cache/condition/rocket_active', '__return_true');
        add_filter('sitchco/cache/condition/cloudflare_installed', '__return_false');
        add_filter('sitchco/cache/condition/cloudfront_installed', '__return_false');

        wp_cache_set('_sitchco_test_key', 'test_value');

        $module = new CacheInvalidation($this->queue);
        $module->init();
        $module->syncObjectCacheFlush();

        $this->assertFalse(wp_cache_get('_sitchco_test_key'), 'wp_cache_flush() should have cleared the object cache');
    }

    public function test_delegated_sync_flush_executes_only_once_per_request(): void
    {
        add_filter('sitchco/cache/condition/rocket_active', '__return_true');
        add_filter('sitchco/cache/condition/cloudflare_installed', '__return_false');
        add_filter('sitchco/cache/condition/cloudfront_installed', '__return_false');
        $module = new CacheInvalidation($this->queue);
        $module->init();

        wp_cache_set('_sitchco_test_key', 'value1');
        $module->syncObjectCacheFlush();
        $firstResult = wp_cache_get('_sitchco_test_key');

        wp_cache_set('_sitchco_test_key', 'value2');
        $module->syncObjectCacheFlush();
        $secondResult = wp_cache_get('_sitchco_test_key');

        $this->assertFalse($firstResult, 'First flush should clear cache');
        $this->assertSame('value2', $secondResult, 'Second flush should be guarded — cache value should survive');
    }

    // ─── Group 4: Queue Processing ───

    public function test_processor_flushes_expired_item_and_removes_from_queue(): void
    {
        update_option(
            CacheQueue::OPTION_NAME,
            [
                ['slug' => 'object_cache', 'expires' => time() - 10, 'delay' => 10],
                ['slug' => 'cloudfront', 'expires' => time() + 100, 'delay' => 100],
            ],
            false,
        );

        wp_cache_set('_sitchco_test_flush', 'exists');

        $this->queue->process();

        $this->assertFalse(wp_cache_get('_sitchco_test_flush'), 'Object cache should have been flushed');

        $remaining = get_option(CacheQueue::OPTION_NAME, []);
        $this->assertCount(1, $remaining);
        $this->assertSame('cloudfront', $remaining[0]['slug']);
    }

    public function test_processor_skips_unexpired_item(): void
    {
        $futureTime = time() + 300;
        update_option(
            CacheQueue::OPTION_NAME,
            [['slug' => 'object_cache', 'expires' => $futureTime, 'delay' => 10]],
            false,
        );

        wp_cache_set('_sitchco_test_skip', 'should_survive');

        $this->queue->process();

        $this->assertSame(
            'should_survive',
            wp_cache_get('_sitchco_test_skip'),
            'Object cache should NOT have been flushed',
        );

        $remaining = get_option(CacheQueue::OPTION_NAME, []);
        $this->assertCount(1, $remaining);
        $this->assertSame($futureTime, $remaining[0]['expires']);
    }

    public function test_processor_resets_remaining_timestamps_after_processing(): void
    {
        update_option(
            CacheQueue::OPTION_NAME,
            [
                ['slug' => 'object_cache', 'expires' => time() - 10, 'delay' => 10],
                ['slug' => 'cloudfront', 'expires' => time() + 50, 'delay' => 100],
                ['slug' => 'cloudflare', 'expires' => time() + 100, 'delay' => 100],
            ],
            false,
        );

        $now = time();
        $this->queue->process();

        $remaining = get_option(CacheQueue::OPTION_NAME, []);
        $this->assertCount(2, $remaining);
        $this->assertEqualsWithDelta($now + 100, $remaining[0]['expires'], 2);
        $this->assertEqualsWithDelta($now + 100, $remaining[1]['expires'], 2);
    }

    public function test_processor_fires_completion_hook_when_queue_empty(): void
    {
        update_option(
            CacheQueue::OPTION_NAME,
            [['slug' => 'object_cache', 'expires' => time() - 10, 'delay' => 10]],
            false,
        );

        $completionFired = false;
        add_action(Hooks::name('cache', 'complete'), function () use (&$completionFired) {
            $completionFired = true;
        });

        $this->queue->process();

        $this->assertTrue($completionFired, 'Completion hook should fire when queue is fully drained');
    }

    public function test_processor_deletes_option_when_queue_empty(): void
    {
        update_option(
            CacheQueue::OPTION_NAME,
            [['slug' => 'object_cache', 'expires' => time() - 10, 'delay' => 10]],
            false,
        );

        $this->queue->process();

        $this->assertFalse(
            get_option(CacheQueue::OPTION_NAME),
            'Queue option should be deleted when cascade completes',
        );
    }

    // ─── Group 5: Debounce ───

    public function test_new_event_overwrites_existing_queue(): void
    {
        add_filter('sitchco/cache/condition/rocket_active', '__return_false');
        add_filter('sitchco/cache/condition/cloudflare_installed', '__return_true');
        add_filter('sitchco/cache/condition/cloudfront_installed', '__return_true');
        $module = new CacheInvalidation($this->queue);
        $module->init();

        // Fire two signals before flushing — write buffer's last-write-wins handles debounce
        do_action('sitchco/post/content_updated');
        do_action('sitchco/post/visibility_changed');

        $this->queue->flushWriteBuffer();
        $queue = get_option(CacheQueue::OPTION_NAME, []);
        $slugs = array_column($queue, 'slug');

        $this->assertSame(['object_cache', 'cloudfront', 'cloudflare'], $slugs);

        // All timestamps should be valid (now + delay)
        $now = time();
        foreach ($queue as $item) {
            $this->assertGreaterThanOrEqual(
                $now,
                $item['expires'],
                "Item {$item['slug']} should have a future timestamp",
            );
        }
    }
}
