<?php

namespace Sitchco\Tests\Integration;

use Sitchco\Events\SavePermalinksRequestEvent;
use Sitchco\Events\SavePostQueueEvent;
use Sitchco\Integration\BackgroundProcessing\BackgroundActionQueue;
use Sitchco\Tests\Support\TestCase;
use Sitchco\Utils\Hooks;

/**
 * class BackgroundProcessingTest
 * @package Sitchco\Tests\Integration
 */
class BackgroundProcessingTest extends TestCase
{
    protected function setUp(): void
    {
        $this->fakeHttp();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->restoreHttp();
        parent::tearDown();
    }

    function test_save_permalink_request_event()
    {
        $event = $this->container->get(SavePermalinksRequestEvent::class);
        $event->init();
        do_action('sitchco/after_save_permalinks');
        $this->assertEmpty($event->getDispatchResponse());
        $processed = false;
        add_action(SavePermalinksRequestEvent::hookName(), function() use (&$processed) {
            $processed = true;
        });
        do_action('sitchco/after_save_permalinks');
        $url = $event->getDispatchResponse()['url'];
        $this->assertStringContainsString('admin-ajax.php?action=sitchco_after_save_permalinks', $url);
        $this->setupAjaxHandle($url, SavePermalinksRequestEvent::HOOK_NAME);
        $event->maybe_handle();
        $this->assertTrue($processed);
    }

    function test_save_post_queue_event()
    {
        $event = $this->container->get(SavePostQueueEvent::class);
        $event->init();
        $this->factory()->post->create();
        $this->assertDidAction('wp_after_insert_post');
        $Queue = $this->container->get(BackgroundActionQueue::class);
        $this->assertFalse($Queue->hasQueuedItems());
        $processed = 0;
        $Queue->addTask(SavePostQueueEvent::HOOK_NAME, function(array $args) use (&$processed) {
            $processed = $args['post_id'];
        });
        $post = $this->factory()->post->create_and_get();
        $this->assertEquals([
            ['action' => SavePostQueueEvent::HOOK_NAME, 'args' => ['post_id' => $post->ID]],
        ], $Queue->getQueuedItems());
        $url = $this->saveQueue();
        $this->setupAjaxHandle($url, BackgroundActionQueue::HOOK_NAME);
        $Queue->maybe_handle();
        $this->assertEquals($post->ID, $processed);
    }

    function test_save_permalinks_bulk_save_posts_task()
    {
        $event = $this->container->get(SavePermalinksRequestEvent::class);
        $event->init();
        $this->container->get(SavePostQueueEvent::class)->init();
        $post_ids = $this->factory()->post->create_many(3);
        $Queue = $this->container->get(BackgroundActionQueue::class);
        $processed = [];
        $Queue->addSavePermalinksBulkSavePostsTask(function(array $args) use (&$processed) {
            $processed[] = $args['post_id'];
        });
        // Process save permalinks
        do_action('sitchco/after_save_permalinks');
        $url = $event->getDispatchResponse()['url'];
        $this->assertStringContainsString('admin-ajax.php?action=sitchco_after_save_permalinks', $url);
        $this->setupAjaxHandle($url, SavePermalinksRequestEvent::HOOK_NAME);
        $event->maybe_handle();
        $this->assertEquals([
            ['action' => SavePostQueueEvent::HOOK_NAME, 'args' => ['post_id' => $post_ids[0]]],
            ['action' => SavePostQueueEvent::HOOK_NAME, 'args' => ['post_id' => $post_ids[1]]],
            ['action' => SavePostQueueEvent::HOOK_NAME, 'args' => ['post_id' => $post_ids[2]]],
        ], $Queue->getQueuedItems());
        $url = $this->saveQueue();
        $this->setupAjaxHandle($url, BackgroundActionQueue::HOOK_NAME);
        $Queue->maybe_handle();
        $this->assertEquals($post_ids, $processed);
    }

    protected function saveQueue()
    {
        $Queue = $this->container->get(BackgroundActionQueue::class);
        do_action(Hooks::name('save_background_queue'));
        $this->assertFalse($Queue->hasQueuedItems());
        $url = $Queue->getDispatchResponse()['url'];
        $this->assertStringContainsString('admin-ajax.php?action=sitchco_background_queue', $url);
        return $url;
    }

    protected function setupAjaxHandle(string $url, string $identifier): void
    {
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $_REQUEST['nonce'] = $query['nonce'];
        add_filter("sitchco_{$identifier}_wp_die", '__return_false');
    }

}
