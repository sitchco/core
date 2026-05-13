<?php

namespace Sitchco\Tests\Utils;

use Sitchco\Tests\TestCase;
use Sitchco\Utils\WordPress;

class WordPressTest extends TestCase
{
    private array $registered_types = [];

    private ?\WP_Styles $wp_styles_backup = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wp_styles_backup = $GLOBALS['wp_styles'] ?? null;
    }

    protected function tearDown(): void
    {
        foreach ($this->registered_types as $type) {
            unregister_post_type($type);
        }
        $this->registered_types = [];
        $GLOBALS['wp_styles'] = $this->wp_styles_backup;
        parent::tearDown();
    }

    private function registerPostType(string $name, array $args = []): void
    {
        register_post_type($name, $args);
        $this->registered_types[] = $name;
    }

    // --- getVisibleArchivePostTypes ---

    public function test_archive_types_excludes_post(): void
    {
        $types = WordPress::getVisibleArchivePostTypes();
        $this->assertNotContains('post', $types);
    }

    public function test_archive_types_excludes_page(): void
    {
        $types = WordPress::getVisibleArchivePostTypes();
        $this->assertNotContains('page', $types);
    }

    public function test_archive_types_excludes_attachment(): void
    {
        $types = WordPress::getVisibleArchivePostTypes();
        $this->assertNotContains('attachment', $types);
    }

    public function test_archive_types_includes_custom_type_with_archive(): void
    {
        $this->registerPostType('test_event', [
            'public' => true,
            'has_archive' => true,
        ]);
        $types = WordPress::getVisibleArchivePostTypes();
        $this->assertContains('test_event', $types);
    }

    public function test_archive_types_excludes_custom_type_without_archive(): void
    {
        $this->registerPostType('test_event', [
            'public' => true,
            'has_archive' => false,
        ]);
        $types = WordPress::getVisibleArchivePostTypes();
        $this->assertNotContains('test_event', $types);
    }

    public function test_archive_types_includes_publicly_queryable_not_public(): void
    {
        $this->registerPostType('test_event', [
            'public' => false,
            'publicly_queryable' => true,
            'has_archive' => true,
        ]);
        $types = WordPress::getVisibleArchivePostTypes();
        $this->assertContains('test_event', $types);
    }

    public function test_archive_types_excludes_non_viewable(): void
    {
        $this->registerPostType('test_internal', [
            'public' => false,
            'publicly_queryable' => false,
        ]);
        $types = WordPress::getVisibleArchivePostTypes();
        $this->assertNotContains('test_internal', $types);
    }

    // --- getVisibleSinglePostTypes ---

    public function test_single_types_includes_post_when_posts_exist(): void
    {
        $this->factory()->post->create(['post_type' => 'post', 'post_status' => 'publish']);
        $types = WordPress::getVisibleSinglePostTypes();
        $this->assertContains('post', $types);
    }

    public function test_single_types_includes_page_when_pages_exist(): void
    {
        $this->factory()->post->create(['post_type' => 'page', 'post_status' => 'publish']);
        $types = WordPress::getVisibleSinglePostTypes();
        $this->assertContains('page', $types);
    }

    public function test_single_types_excludes_attachment(): void
    {
        $types = WordPress::getVisibleSinglePostTypes(true);
        $this->assertNotContains('attachment', $types);
    }

    public function test_single_types_excludes_empty_by_default(): void
    {
        $this->registerPostType('test_event', [
            'public' => true,
        ]);
        $types = WordPress::getVisibleSinglePostTypes();
        $this->assertNotContains('test_event', $types);
    }

    public function test_single_types_includes_empty_when_flag_set(): void
    {
        $this->registerPostType('test_event', [
            'public' => true,
        ]);
        $types = WordPress::getVisibleSinglePostTypes(true);
        $this->assertContains('test_event', $types);
    }

    public function test_single_types_includes_publicly_queryable_not_public(): void
    {
        $this->registerPostType('test_event', [
            'public' => false,
            'publicly_queryable' => true,
        ]);
        $this->factory()->post->create(['post_type' => 'test_event', 'post_status' => 'publish']);
        wp_cache_delete('test_event', 'counts');
        $types = WordPress::getVisibleSinglePostTypes();
        $this->assertContains('test_event', $types);
    }

    public function test_single_types_includes_type_with_draft_posts(): void
    {
        $this->registerPostType('test_event', [
            'public' => true,
        ]);
        $this->factory()->post->create(['post_type' => 'test_event', 'post_status' => 'draft']);
        wp_cache_delete('test_event', 'counts');
        $types = WordPress::getVisibleSinglePostTypes();
        $this->assertContains('test_event', $types);
    }

    public function test_single_types_includes_type_without_rewrite(): void
    {
        $this->registerPostType('test_event', [
            'publicly_queryable' => true,
            'rewrite' => false,
        ]);
        $this->factory()->post->create(['post_type' => 'test_event', 'post_status' => 'publish']);
        wp_cache_delete('test_event', 'counts');
        $types = WordPress::getVisibleSinglePostTypes();
        $this->assertContains('test_event', $types);
    }

    public function test_single_types_excludes_non_viewable_even_with_include_empty(): void
    {
        $this->registerPostType('test_internal', [
            'public' => false,
            'publicly_queryable' => false,
        ]);
        $types = WordPress::getVisibleSinglePostTypes(true);
        $this->assertNotContains('test_internal', $types);
    }

    // --- captureWithInlineStyleRecovery ---

    public function test_inline_recovery_appends_style_block_for_orphaned_handle(): void
    {
        $wp_styles = wp_styles();
        wp_register_style('wptest-recovery-orphan', false);
        $wp_styles->done[] = 'wptest-recovery-orphan';

        $output = WordPress::captureWithInlineStyleRecovery(function () {
            wp_add_inline_style('wptest-recovery-orphan', '.orphan { color: red; }');
            return '<p>body</p>';
        });

        $this->assertStringContainsString('<p>body</p>', $output);
        $this->assertStringContainsString('<style>', $output);
        $this->assertStringContainsString('.orphan { color: red; }', $output);
    }

    public function test_inline_recovery_omits_style_block_when_no_orphans(): void
    {
        $wp_styles = wp_styles();
        wp_register_style('wptest-recovery-clean', false);
        $wp_styles->done[] = 'wptest-recovery-clean';

        $output = WordPress::captureWithInlineStyleRecovery(fn() => '<p>body</p>');

        $this->assertSame('<p>body</p>', $output);
        $this->assertStringNotContainsString('<style>', $output);
    }

    public function test_inline_recovery_skips_handles_printed_during_render(): void
    {
        wp_register_style('wptest-recovery-printed', false);
        wp_add_inline_style('wptest-recovery-printed', '.printed { color: blue; }');

        $output = WordPress::captureWithInlineStyleRecovery(function () {
            wp_styles()->done[] = 'wptest-recovery-printed';
            return '<p>body</p>';
        });

        $this->assertSame('<p>body</p>', $output);
        $this->assertStringNotContainsString('.printed { color: blue; }', $output);
    }
}
