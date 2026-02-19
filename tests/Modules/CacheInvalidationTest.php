<?php

namespace Sitchco\Tests\Modules;

use Sitchco\Modules\CacheInvalidation\CacheInvalidation;
use Sitchco\Modules\CacheInvalidation\CacheQueue;
use Sitchco\Modules\CacheInvalidation\CloudflareInvalidator;
use Sitchco\Modules\CacheInvalidation\CloudFrontInvalidator;
use Sitchco\Modules\CacheInvalidation\Invalidator;
use Sitchco\Modules\CacheInvalidation\ObjectCacheInvalidator;
use Sitchco\Modules\CacheInvalidation\WPRocketInvalidator;
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
        $this->container->set(WPRocketInvalidator::class, new WPRocketInvalidator());
        $this->container->set(CloudFrontInvalidator::class, new CloudFrontInvalidator());
        $this->container->set(CloudflareInvalidator::class, new CloudflareInvalidator());
        $this->container->set(ObjectCacheInvalidator::class, new ObjectCacheInvalidator());
        $this->queue->registerInvalidators([
            $this->container->get(WPRocketInvalidator::class),
            $this->container->get(CloudFrontInvalidator::class),
            $this->container->get(CloudflareInvalidator::class),
            $this->container->get(ObjectCacheInvalidator::class),
        ]);
        parent::tearDown();
    }

    private function createMockInvalidator(
        string $slug,
        bool $available,
        int $priority = 0,
        int $delay = 10,
    ): Invalidator {
        $mock = $this->createMock(Invalidator::class);
        $mock->method('slug')->willReturn($slug);
        $mock->method('isAvailable')->willReturn($available);
        $mock->method('priority')->willReturn($priority);
        $mock->method('delay')->willReturn($delay);
        return $mock;
    }

    // ─── Group 1: Delegated Mode — Signal → Queue Routing ───

    public function test_delegated_content_updated_does_not_create_queue(): void
    {
        $this->container->set(WPRocketInvalidator::class, $this->createMockInvalidator('wp_rocket', true, 10, 50));
        $this->container->set(CloudFrontInvalidator::class, $this->createMockInvalidator('cloudfront', true, 50, 100));
        $this->container->set(CloudflareInvalidator::class, $this->createMockInvalidator('cloudflare', true, 100, 100));
        $this->container->set(ObjectCacheInvalidator::class, $this->createMockInvalidator('object_cache', true, 0, 10));
        $module = new CacheInvalidation($this->queue, $this->container);
        $module->init();

        do_action('sitchco/post/content_updated');
        do_action('sitchco/acf/fields_saved');

        $this->queue->flushWriteBuffer();
        $this->assertEmpty(get_option(CacheQueue::OPTION_NAME, []));
    }

    public function test_delegated_visibility_changed_queues_rocket_and_cdns(): void
    {
        $this->container->set(WPRocketInvalidator::class, $this->createMockInvalidator('wp_rocket', true, 10, 50));
        $this->container->set(CloudFrontInvalidator::class, $this->createMockInvalidator('cloudfront', true, 50, 100));
        $this->container->set(CloudflareInvalidator::class, $this->createMockInvalidator('cloudflare', true, 100, 100));
        $this->container->set(ObjectCacheInvalidator::class, $this->createMockInvalidator('object_cache', true, 0, 10));
        $module = new CacheInvalidation($this->queue, $this->container);
        $module->init();

        do_action('sitchco/post/visibility_changed');

        $this->queue->flushWriteBuffer();
        $slugs = array_column(get_option(CacheQueue::OPTION_NAME, []), 'slug');
        $this->assertSame(['wp_rocket', 'cloudfront', 'cloudflare'], $slugs);
    }

    public function test_delegated_deploy_complete_queues_rocket_and_cdns(): void
    {
        $this->container->set(WPRocketInvalidator::class, $this->createMockInvalidator('wp_rocket', true, 10, 50));
        $this->container->set(CloudFrontInvalidator::class, $this->createMockInvalidator('cloudfront', true, 50, 100));
        $this->container->set(CloudflareInvalidator::class, $this->createMockInvalidator('cloudflare', true, 100, 100));
        $this->container->set(ObjectCacheInvalidator::class, $this->createMockInvalidator('object_cache', true, 0, 10));
        $module = new CacheInvalidation($this->queue, $this->container);
        $module->init();

        do_action('sitchco/deploy/complete');

        $this->queue->flushWriteBuffer();
        $slugs = array_column(get_option(CacheQueue::OPTION_NAME, []), 'slug');
        $this->assertSame(['wp_rocket', 'cloudfront', 'cloudflare'], $slugs);
    }

    public function test_delegated_clear_all_queues_rocket_and_cdns(): void
    {
        $this->container->set(WPRocketInvalidator::class, $this->createMockInvalidator('wp_rocket', true, 10, 50));
        $this->container->set(CloudFrontInvalidator::class, $this->createMockInvalidator('cloudfront', true, 50, 100));
        $this->container->set(CloudflareInvalidator::class, $this->createMockInvalidator('cloudflare', true, 100, 100));
        $this->container->set(ObjectCacheInvalidator::class, $this->createMockInvalidator('object_cache', true, 0, 10));
        $module = new CacheInvalidation($this->queue, $this->container);
        $module->init();

        do_action('sitchco/cache/clear_all');

        $this->queue->flushWriteBuffer();
        $slugs = array_column(get_option(CacheQueue::OPTION_NAME, []), 'slug');
        $this->assertSame(['wp_rocket', 'cloudfront', 'cloudflare'], $slugs);
    }

    public function test_delegated_after_rocket_clean_queues_cdns_only(): void
    {
        $this->container->set(WPRocketInvalidator::class, $this->createMockInvalidator('wp_rocket', true, 10, 50));
        $this->container->set(CloudFrontInvalidator::class, $this->createMockInvalidator('cloudfront', true, 50, 100));
        $this->container->set(CloudflareInvalidator::class, $this->createMockInvalidator('cloudflare', true, 100, 100));
        $this->container->set(ObjectCacheInvalidator::class, $this->createMockInvalidator('object_cache', true, 0, 10));
        $module = new CacheInvalidation($this->queue, $this->container);
        $module->init();

        do_action('after_rocket_clean_domain');

        $this->queue->flushWriteBuffer();
        $slugs = array_column(get_option(CacheQueue::OPTION_NAME, []), 'slug');
        $this->assertSame(['cloudfront', 'cloudflare'], $slugs);
        $this->assertNotContains('wp_rocket', $slugs);
    }

    public function test_delegated_queue_excludes_unavailable_invalidators(): void
    {
        $this->container->set(WPRocketInvalidator::class, $this->createMockInvalidator('wp_rocket', true, 10, 50));
        $this->container->set(CloudFrontInvalidator::class, $this->createMockInvalidator('cloudfront', true, 50, 100));
        $this->container->set(
            CloudflareInvalidator::class,
            $this->createMockInvalidator('cloudflare', false, 100, 100),
        );
        $this->container->set(ObjectCacheInvalidator::class, $this->createMockInvalidator('object_cache', true, 0, 10));
        $module = new CacheInvalidation($this->queue, $this->container);
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
            $this->container->set(WPRocketInvalidator::class, $this->createMockInvalidator('wp_rocket', false, 10, 50));
            $this->container->set(
                CloudFrontInvalidator::class,
                $this->createMockInvalidator('cloudfront', true, 50, 100),
            );
            $this->container->set(
                CloudflareInvalidator::class,
                $this->createMockInvalidator('cloudflare', true, 100, 100),
            );
            $this->container->set(
                ObjectCacheInvalidator::class,
                $this->createMockInvalidator('object_cache', true, 0, 10),
            );
            $queue = $this->container->get(CacheQueue::class);
            $module = new CacheInvalidation($queue, $this->container);
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
        $this->container->set(WPRocketInvalidator::class, $this->createMockInvalidator('wp_rocket', false, 10, 50));
        $this->container->set(CloudFrontInvalidator::class, $this->createMockInvalidator('cloudfront', true, 50, 100));
        $this->container->set(CloudflareInvalidator::class, $this->createMockInvalidator('cloudflare', true, 100, 100));
        $this->container->set(ObjectCacheInvalidator::class, $this->createMockInvalidator('object_cache', true, 0, 10));
        $module = new CacheInvalidation($this->queue, $this->container);
        $module->init();

        do_action('sitchco/post/content_updated');

        $this->queue->flushWriteBuffer();
        $slugs = array_column(get_option(CacheQueue::OPTION_NAME, []), 'slug');
        $this->assertNotContains('wp_rocket', $slugs);
    }

    public function test_standalone_queue_excludes_unavailable_cdns(): void
    {
        $this->container->set(WPRocketInvalidator::class, $this->createMockInvalidator('wp_rocket', false, 10, 50));
        $this->container->set(CloudFrontInvalidator::class, $this->createMockInvalidator('cloudfront', false, 50, 100));
        $this->container->set(
            CloudflareInvalidator::class,
            $this->createMockInvalidator('cloudflare', false, 100, 100),
        );
        $this->container->set(ObjectCacheInvalidator::class, $this->createMockInvalidator('object_cache', true, 0, 10));
        $module = new CacheInvalidation($this->queue, $this->container);
        $module->init();

        do_action('sitchco/post/content_updated');

        $this->queue->flushWriteBuffer();
        $slugs = array_column(get_option(CacheQueue::OPTION_NAME, []), 'slug');
        $this->assertSame(['object_cache'], $slugs);
    }

    // ─── Group 3: Delegated Mode — Sync Object Cache Flush ───

    public function test_delegated_sync_flushes_object_cache_on_before_rocket_clean(): void
    {
        $this->container->set(WPRocketInvalidator::class, $this->createMockInvalidator('wp_rocket', true, 10, 50));
        $this->container->set(CloudFrontInvalidator::class, $this->createMockInvalidator('cloudfront', false, 50, 100));
        $this->container->set(
            CloudflareInvalidator::class,
            $this->createMockInvalidator('cloudflare', false, 100, 100),
        );
        $this->container->set(ObjectCacheInvalidator::class, $this->createMockInvalidator('object_cache', true, 0, 10));

        wp_cache_set('_sitchco_test_key', 'test_value');

        $module = new CacheInvalidation($this->queue, $this->container);
        $module->init();
        $module->syncObjectCacheFlush();

        $this->assertFalse(wp_cache_get('_sitchco_test_key'), 'wp_cache_flush() should have cleared the object cache');
    }

    public function test_delegated_sync_flush_executes_only_once_per_request(): void
    {
        $this->container->set(WPRocketInvalidator::class, $this->createMockInvalidator('wp_rocket', true, 10, 50));
        $this->container->set(CloudFrontInvalidator::class, $this->createMockInvalidator('cloudfront', false, 50, 100));
        $this->container->set(
            CloudflareInvalidator::class,
            $this->createMockInvalidator('cloudflare', false, 100, 100),
        );
        $this->container->set(ObjectCacheInvalidator::class, $this->createMockInvalidator('object_cache', true, 0, 10));
        $module = new CacheInvalidation($this->queue, $this->container);
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
        $mockObjectCache = $this->createMockInvalidator('object_cache', true, 0, 10);
        $mockObjectCache->expects($this->once())->method('flush');
        $mockCloudfront = $this->createMockInvalidator('cloudfront', true, 50, 100);
        $this->queue->registerInvalidators([$mockObjectCache, $mockCloudfront]);

        update_option(
            CacheQueue::OPTION_NAME,
            [
                ['slug' => 'object_cache', 'expires' => time() - 10, 'delay' => 10],
                ['slug' => 'cloudfront', 'expires' => time() + 100, 'delay' => 100],
            ],
            false,
        );

        $this->queue->process();

        $remaining = get_option(CacheQueue::OPTION_NAME, []);
        $this->assertCount(1, $remaining);
        $this->assertSame('cloudfront', $remaining[0]['slug']);
    }

    public function test_processor_skips_unexpired_item(): void
    {
        $mockObjectCache = $this->createMockInvalidator('object_cache', true, 0, 10);
        $mockObjectCache->expects($this->never())->method('flush');
        $this->queue->registerInvalidators([$mockObjectCache]);

        $futureTime = time() + 300;
        update_option(
            CacheQueue::OPTION_NAME,
            [['slug' => 'object_cache', 'expires' => $futureTime, 'delay' => 10]],
            false,
        );

        $this->queue->process();

        $remaining = get_option(CacheQueue::OPTION_NAME, []);
        $this->assertCount(1, $remaining);
        $this->assertSame($futureTime, $remaining[0]['expires']);
    }

    public function test_processor_resets_remaining_timestamps_after_processing(): void
    {
        $mockObjectCache = $this->createMockInvalidator('object_cache', true, 0, 10);
        $mockObjectCache->expects($this->once())->method('flush');
        $mockCloudfront = $this->createMockInvalidator('cloudfront', true, 50, 100);
        $mockCloudflare = $this->createMockInvalidator('cloudflare', true, 100, 100);
        $this->queue->registerInvalidators([$mockObjectCache, $mockCloudfront, $mockCloudflare]);

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
        $mockObjectCache = $this->createMockInvalidator('object_cache', true, 0, 10);
        $this->queue->registerInvalidators([$mockObjectCache]);

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
        $mockObjectCache = $this->createMockInvalidator('object_cache', true, 0, 10);
        $this->queue->registerInvalidators([$mockObjectCache]);

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

    public function test_processor_drops_non_array_rows_and_processes_valid_ones(): void
    {
        $mockObjectCache = $this->createMockInvalidator('object_cache', true, 0, 10);
        $mockObjectCache->expects($this->once())->method('flush');
        $mockCloudfront = $this->createMockInvalidator('cloudfront', true, 50, 100);
        $this->queue->registerInvalidators([$mockObjectCache, $mockCloudfront]);

        update_option(
            CacheQueue::OPTION_NAME,
            [
                ['slug' => 'object_cache', 'expires' => time() - 10, 'delay' => 10],
                'not-an-array',
                42,
                null,
                ['slug' => 'cloudfront', 'expires' => time() + 100, 'delay' => 100],
            ],
            false,
        );

        $this->queue->process();

        $remaining = get_option(CacheQueue::OPTION_NAME, []);
        $this->assertCount(1, $remaining);
        $this->assertSame('cloudfront', $remaining[0]['slug']);
    }

    public function test_queue_option_stores_arrays_not_objects(): void
    {
        $this->container->set(WPRocketInvalidator::class, $this->createMockInvalidator('wp_rocket', false, 10, 50));
        $this->container->set(CloudFrontInvalidator::class, $this->createMockInvalidator('cloudfront', true, 50, 100));
        $this->container->set(CloudflareInvalidator::class, $this->createMockInvalidator('cloudflare', true, 100, 100));
        $this->container->set(ObjectCacheInvalidator::class, $this->createMockInvalidator('object_cache', true, 0, 10));
        $module = new CacheInvalidation($this->queue, $this->container);
        $module->init();

        do_action('sitchco/post/content_updated');

        $this->queue->flushWriteBuffer();
        $stored = get_option(CacheQueue::OPTION_NAME, []);
        $this->assertNotEmpty($stored);

        foreach ($stored as $item) {
            $this->assertIsArray($item, 'Queue items must be stored as arrays, not objects');
            $this->assertArrayHasKey('slug', $item);
            $this->assertArrayHasKey('expires', $item);
            $this->assertArrayHasKey('delay', $item);
        }
    }

    // ─── Group 5: Debounce ───

    public function test_new_event_overwrites_existing_queue(): void
    {
        $this->container->set(WPRocketInvalidator::class, $this->createMockInvalidator('wp_rocket', false, 10, 50));
        $this->container->set(CloudFrontInvalidator::class, $this->createMockInvalidator('cloudfront', true, 50, 100));
        $this->container->set(CloudflareInvalidator::class, $this->createMockInvalidator('cloudflare', true, 100, 100));
        $this->container->set(ObjectCacheInvalidator::class, $this->createMockInvalidator('object_cache', true, 0, 10));
        $module = new CacheInvalidation($this->queue, $this->container);
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
