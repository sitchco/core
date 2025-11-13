<?php

namespace Sitchco\Tests\Modules;

use Sitchco\Support\DateTime;
use Sitchco\Tests\TestCase;
use Sitchco\Modules\TimberModule;
use WP_Block;
use WP_Block_Supports;

class TimberModuleTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // Load and register the ACF field group
        $field_group = include SITCHCO_CORE_FIXTURES_DIR . '/acf-field-group.php';
        acf_add_local_field_group($field_group);
    }

    /**
     * Helper method to execute blockRenderCallback and return the context
     *
     * @param array $blockData Additional block data array
     * @param int $postId Post ID
     * @param string $content Block content
     * @param bool $isPreview Is preview mode
     * @param WP_Block|null $wpBlock WP_Block instance
     * @return array|string The context array after blockRenderCallback processing, or a rendered template string
     */
    private function renderBlockWithContext(
        array $blockData = [],
        int $postId = 0,
        string $content = '',
        bool $isPreview = false,
        ?WP_Block $wpBlock = null,
        bool $return_context = false,
    ): array|string {
        $block = array_merge(
            [
                'name' => 'sitchco/test-block',
                'path' => SITCHCO_CORE_TESTS_DIR . '/Fakes/ModuleTester/blocks/test-block',
                'return_context' => $return_context,
            ],
            $blockData,
        );

        ob_start();
        TimberModule::blockRenderCallback($block, $content, $isPreview, $postId, $wpBlock);
        $output = ob_get_clean();

        return maybe_unserialize($output);
    }

    public function test_acf_date_meta()
    {
        $post_id = $this->factory()->post->create([
            'post_title' => 'Post',
        ]);
        update_field('start_time', '2026-01-01 12:30:00', $post_id);
        update_field('end_time', '2026-01-01 14:30:00', $post_id);
        $Post = \Timber\Timber::get_post($post_id);
        $this->assertInstanceOf(DateTime::class, $Post->start_time);
    }

    public function test_blockRenderCallback_with_minimal_block_data()
    {
        $post_id = $this->factory()->post->create([
            'post_title' => 'Test Post',
        ]);

        $context = $this->renderBlockWithContext(postId: $post_id, return_context: true);

        // Basic assertions - context should be set up
        $this->assertIsArray($context);
        $this->assertArrayHasKey('block', $context);
        $this->assertArrayHasKey('post', $context);
    }

    public function test_blockRenderCallback_parses_content_when_no_wp_block()
    {
        $post_id = $this->factory()->post->create(['post_title' => 'Test Post']);
        $content = '<!-- wp:paragraph --><p>Test paragraph</p><!-- /wp:paragraph -->';
        // This test verifies the existing behavior before WP_Block support
        $context = $this->renderBlockWithContext(postId: $post_id, content: $content, return_context: true);
        $this->assertIsArray($context);
        $this->assertArrayHasKey('block', $context);
        $this->assertNotEmpty($context['block']['innerBlocks'], 'Expected innerBlocks to be parsed from content');
    }

    public function test_blockRenderCallback_sets_is_preview_flag()
    {
        $post_id = $this->factory()->post->create(['post_title' => 'Test Post']);
        // Test with is_preview = false
        $context = $this->renderBlockWithContext(postId: $post_id, return_context: true);
        $this->assertFalse($context['is_preview']);
        // Test with is_preview = true
        $context = $this->renderBlockWithContext(postId: $post_id, isPreview: true, return_context: true);
        $this->assertTrue($context['is_preview']);
    }

    public function test_blockRenderCallback_injects_helper_variables()
    {
        $post_id = $this->factory()->post->create(['post_title' => 'Test Post']);

        $context = $this->renderBlockWithContext(
            blockData: ['wrapper_attributes' => 'class="test-class"'],
            postId: $post_id,
            return_context: true,
        );

        // Note: inner_blocks and wrapper_attributes are set AFTER block.php is loaded,
        // so they won't be in our serialized context. We can verify the block data is passed through though.
        $this->assertArrayHasKey('block', $context);
        $this->assertEquals('class="test-class"', $context['block']['wrapper_attributes']);
    }

    public function test_blockRenderCallback_with_wp_block_extracts_inner_blocks()
    {
        $post_id = $this->factory()->post->create(['post_title' => 'Test Post']);

        // Create a mock WP_Block with inner blocks
        $innerBlockData = [
            'blockName' => 'core/paragraph',
            'attrs' => [],
            'innerContent' => ['<p>Inner paragraph</p>'],
        ];

        $wp_block = new WP_Block([
            'blockName' => 'sitchco/test-block',
            'attrs' => [],
            'innerBlocks' => [$innerBlockData],
        ]);

        $context = $this->renderBlockWithContext(postId: $post_id, wpBlock: $wp_block, return_context: true);

        // Verify that innerBlocks were extracted from WP_Block to the block array
        $this->assertNotEmpty($context['block']['innerBlocks'], 'Expected to have inner blocks');
        $this->assertCount(1, $context['block']['innerBlocks']);
    }

    public function test_blockRenderCallback_loads_metadata_and_sets_innerBlocksConfig()
    {
        $post_id = $this->factory()->post->create(['post_title' => 'Test Post']);

        $output = $this->renderBlockWithContext(postId: $post_id, isPreview: true);
        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded, 'Expected innerBlocksConfig to be output as JSON');
        $this->assertArrayHasKey('allowedBlocks', $decoded);
        $this->assertEquals(['core/paragraph', 'core/heading'], $decoded['allowedBlocks']);
        $this->assertArrayHasKey('template', $decoded);
        $this->assertArrayHasKey('templateLock', $decoded);
    }

    public function test_blockRenderCallback_wrapper_element()
    {
        $post_id = $this->factory()->post->create(['post_title' => 'Test Post']);
        WP_Block_Supports::$block_to_render = [
            'blockName' => 'sitchco/test-block',
            'attrs' => [],
        ];
        $output = $this->renderBlockWithContext(postId: $post_id);
        $this->assertStringContainsString('<div class="wp-block-sitchco-test-block">', $output);
    }
}
