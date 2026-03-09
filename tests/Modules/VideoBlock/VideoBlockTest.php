<?php

namespace Sitchco\Tests\Modules\VideoBlock;

use Sitchco\Modules\VideoBlock\VideoBlock;
use Sitchco\Tests\TestCase;
use WP_Block_Type_Registry;

class VideoBlockTest extends TestCase
{
    // --- Block Registration ---

    public function test_module_is_registered(): void
    {
        $module = $this->container->get(VideoBlock::class);
        $this->assertInstanceOf(VideoBlock::class, $module);
    }

    public function test_block_type_is_registered(): void
    {
        $this->assertTrue(
            WP_Block_Type_Registry::get_instance()->is_registered('sitchco/video'),
            'Block type sitchco/video should be registered',
        );
    }

    // --- Render Output ---

    public function test_render_without_url_outputs_innerblocks_content(): void
    {
        $attributes = [
            'url' => '',
            'provider' => '',
            'videoTitle' => '',
            'displayMode' => 'inline',
            'modalId' => '',
            'playIconStyle' => 'dark',
            'playIconX' => 50,
            'playIconY' => 50,
            'clickBehavior' => 'poster',
            '_videoTitleEdited' => false,
            '_modalIdEdited' => false,
        ];
        $content = '<p>Inner blocks content here</p>';
        $output = $this->renderBlock($attributes, $content);

        $this->assertEquals($content, $output, 'When url is empty, render should output InnerBlocks content directly');
    }

    public function test_render_with_url_outputs_wrapper_with_data_attributes(): void
    {
        $attributes = [
            'url' => 'https://www.youtube.com/watch?v=test123',
            'provider' => 'youtube',
            'videoTitle' => 'Test Video',
            'displayMode' => 'inline',
            'modalId' => '',
            'playIconStyle' => 'dark',
            'playIconX' => 50,
            'playIconY' => 50,
            'clickBehavior' => 'poster',
            '_videoTitleEdited' => false,
            '_modalIdEdited' => false,
        ];
        $content = '<p>Inner blocks content here</p>';
        $output = $this->renderBlock($attributes, $content);

        $this->assertStringContainsString('sitchco-video', $output, 'Output should contain sitchco-video class');
        $this->assertStringContainsString(
            'data-url="https://www.youtube.com/watch?v=test123"',
            $output,
            'Output should contain data-url attribute',
        );
        $this->assertStringContainsString(
            'data-provider="youtube"',
            $output,
            'Output should contain data-provider attribute',
        );
        $this->assertStringContainsString($content, $output, 'Output should contain InnerBlocks content');
    }

    /**
     * Render the block's render.php template with the given attributes and content.
     */
    private function renderBlock(array $attributes, string $content): string
    {
        $module = $this->container->get(VideoBlock::class);
        $renderFile = $module->blocksPath()->append('video/render.php')->toString();

        // Mock $block as a simple object (render.php receives $block but Phase 1 doesn't use it)
        $block = new \stdClass();

        ob_start();
        // Scope the include so that $attributes, $content, $block are available
        (function (string $_file, array $attributes, string $content, object $block) {
            include $_file;
        })($renderFile, $attributes, $content, $block);
        return ob_get_clean();
    }
}
