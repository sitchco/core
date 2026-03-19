<?php

namespace Sitchco\Tests\Modules\CustomTags;

use Sitchco\Modules\CustomTags\CustomTags;
use Sitchco\Modules\TagManager\TagManager;
use Sitchco\Tests\TestCase;
use Sitchco\Utils\Cache;

class CustomTagsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        acf_get_store('values')->reset();
        Cache::forget('custom_tags_by_placement');
    }

    protected function tearDown(): void
    {
        remove_all_filters(CustomTags::hookName('render'));
        remove_all_filters('acf/load_value/name=gtm_container_ids');
        remove_all_filters(TagManager::hookName('enable-gtm'));
        parent::tearDown();
    }

    private function createCustomTag(string $content, string $placement = 'after_gtm', string $status = 'publish'): int
    {
        $postId = $this->factory()->post->create([
            'post_type' => 'sitchco_script',
            'post_status' => $status,
            'post_title' => 'Test Tag',
        ]);
        update_field('script_content', $content, $postId);
        update_field('script_placement', $placement, $postId);
        return $postId;
    }

    private function captureHook(string $hook): string
    {
        ob_start();
        do_action($hook);
        return ob_get_clean();
    }

    public function test_before_gtm_tag_renders_in_wp_head(): void
    {
        $this->createCustomTag('<script>console.log("before-gtm")</script>', 'before_gtm');
        $head = $this->captureHook('wp_head');
        $this->assertStringContainsString('console.log("before-gtm")', $head);
    }

    public function test_before_gtm_tag_renders_before_datalayer_init(): void
    {
        add_filter('acf/load_value/name=gtm_container_ids', fn() => [['container_id' => 'GTM-TEST']], 10, 0);
        $this->createCustomTag('<!-- BEFORE_GTM_MARKER -->', 'before_gtm');
        $head = $this->captureHook('wp_head');
        $markerPos = strpos($head, '<!-- BEFORE_GTM_MARKER -->');
        $dlPos = strpos($head, 'window.dataLayer=window.dataLayer||[]');
        $this->assertNotFalse($markerPos);
        $this->assertNotFalse($dlPos);
        $this->assertLessThan($dlPos, $markerPos);
    }

    public function test_after_gtm_tag_renders_in_wp_head_after_gtm_snippet(): void
    {
        add_filter('acf/load_value/name=gtm_container_ids', fn() => [['container_id' => 'GTM-TEST']], 10, 0);
        $this->createCustomTag('<!-- AFTER_GTM_MARKER -->', 'after_gtm');
        $head = $this->captureHook('wp_head');
        $markerPos = strpos($head, '<!-- AFTER_GTM_MARKER -->');
        $gtmPos = strpos($head, 'googletagmanager');
        $this->assertNotFalse($markerPos);
        $this->assertNotFalse($gtmPos);
        $this->assertGreaterThan($gtmPos, $markerPos);
    }

    public function test_footer_tag_renders_in_wp_footer(): void
    {
        $this->setExpectedDeprecated('the_block_template_skip_link');
        $this->createCustomTag('<script>console.log("footer-tag")</script>', 'footer');
        $footer = $this->captureHook('wp_footer');
        $this->assertStringContainsString('console.log("footer-tag")', $footer);
    }

    public function test_draft_tags_do_not_render(): void
    {
        $this->createCustomTag('<script>console.log("draft")</script>', 'before_gtm', 'draft');
        $head = $this->captureHook('wp_head');
        $this->assertStringNotContainsString('console.log("draft")', $head);
    }

    public function test_tags_render_when_gtm_is_disabled(): void
    {
        add_filter(TagManager::hookName('enable-gtm'), '__return_false');
        $this->createCustomTag('<!-- INDEPENDENT_TAG -->', 'before_gtm');
        $head = $this->captureHook('wp_head');
        $this->assertStringContainsString('<!-- INDEPENDENT_TAG -->', $head);
    }

    public function test_multiple_tags_with_same_placement_all_render(): void
    {
        $this->createCustomTag('<!-- TAG_ONE -->', 'after_gtm');
        $this->createCustomTag('<!-- TAG_TWO -->', 'after_gtm');
        $head = $this->captureHook('wp_head');
        $this->assertStringContainsString('<!-- TAG_ONE -->', $head);
        $this->assertStringContainsString('<!-- TAG_TWO -->', $head);
    }
}
