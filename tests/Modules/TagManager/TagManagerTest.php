<?php

namespace Sitchco\Tests\Modules\TagManager;

use Sitchco\Modules\TagManager\TagManager;
use Sitchco\Tests\TestCase;

class TagManagerTest extends TestCase
{
    private TagManager $module;

    protected function setUp(): void
    {
        parent::setUp();
        $this->module = $this->container->get(TagManager::class);
    }

    protected function tearDown(): void
    {
        remove_all_filters('acf/format_value/name=gtm_container_ids');
        remove_all_filters(TagManager::hookName('enable-gtm'));
        parent::tearDown();
    }

    private function setContainerIds(array $ids): void
    {
        add_filter('acf/format_value/name=gtm_container_ids', fn() => $ids, 10, 0);
    }

    private function captureHook(string $hook): string
    {
        ob_start();
        do_action($hook);
        return ob_get_clean();
    }

    public function test_renders_gtm_snippets_for_configured_containers(): void
    {
        $this->setContainerIds([
            ['container_id' => 'GTM-FIRST'],
            ['container_id' => 'GTM-SECOND'],
        ]);
        $head = $this->captureHook('wp_head');
        $body = $this->captureHook('wp_body_open');
        $this->assertStringContainsString("'GTM-FIRST'", $head);
        $this->assertStringContainsString("'GTM-SECOND'", $head);
        $this->assertStringContainsString('ns.html?id=GTM-FIRST', $body);
        $this->assertStringContainsString('ns.html?id=GTM-SECOND', $body);
    }

    public function test_no_output_when_no_containers_configured(): void
    {
        $this->setContainerIds([]);
        $head = $this->captureHook('wp_head');
        $body = $this->captureHook('wp_body_open');
        $this->assertStringNotContainsString('googletagmanager', $head);
        $this->assertStringNotContainsString('googletagmanager', $body);
    }

    public function test_enable_gtm_filter_suppresses_output(): void
    {
        $this->setContainerIds([['container_id' => 'GTM-TEST123']]);
        add_filter(TagManager::hookName('enable-gtm'), '__return_false');
        $head = $this->captureHook('wp_head');
        $body = $this->captureHook('wp_body_open');
        $this->assertStringNotContainsString('googletagmanager', $head);
        $this->assertStringNotContainsString('googletagmanager', $body);
    }
}
