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

    // --- Poster Rendering ---

    public function test_render_with_oembed_thumbnail(): void
    {
        $url = 'https://www.youtube.com/watch?v=oembed_thumb_test';
        $this->deleteOembedTransient($url);
        $this->fakeOembedResponse($url, [
            'thumbnail_url' => 'https://img.youtube.com/vi/oembed_thumb_test/hqdefault.jpg',
            'title' => 'Test Video Title',
            'width' => 480,
            'height' => 360,
        ]);

        $output = $this->renderBlock(
            $this->makeAttributes(['url' => $url, 'provider' => 'youtube', 'videoTitle' => 'Test Video Title']),
            '',
        );

        $this->assertStringContainsString('<img', $output, 'Output should contain an img element');
        $this->assertStringContainsString(
            'sitchco-video__poster-img',
            $output,
            'Poster img should have sitchco-video__poster-img class',
        );
        $this->assertStringContainsString(
            'https://img.youtube.com/vi/oembed_thumb_test/hqdefault.jpg',
            $output,
            'Poster img src should be the oEmbed thumbnail URL',
        );
        $this->restoreHttp();
    }

    public function test_render_innerblocks_as_poster(): void
    {
        $url = 'https://www.youtube.com/watch?v=innerblocks_test';
        $this->deleteOembedTransient($url);

        $output = $this->renderBlock(
            $this->makeAttributes(['url' => $url, 'provider' => 'youtube', 'videoTitle' => 'InnerBlocks Test']),
            '<p>Custom poster</p>',
        );

        $this->assertStringContainsString('<p>Custom poster</p>', $output, 'Output should contain InnerBlocks content');
        $this->assertStringNotContainsString('<img', $output, 'Output should NOT contain img when InnerBlocks present');
    }

    public function test_render_generic_placeholder(): void
    {
        $url = 'https://www.youtube.com/watch?v=placeholder_test';
        $this->deleteOembedTransient($url);
        $this->fakeOembedResponse($url, [
            'title' => 'No Thumbnail Video',
            'width' => 480,
            'height' => 360,
        ]);

        $output = $this->renderBlock(
            $this->makeAttributes(['url' => $url, 'provider' => 'youtube', 'videoTitle' => 'No Thumbnail Video']),
            '',
        );

        $this->assertStringContainsString(
            'sitchco-video__placeholder-poster',
            $output,
            'Output should contain placeholder poster class when no thumbnail',
        );
        $this->restoreHttp();
    }

    public function test_play_button_aria_label(): void
    {
        $url = 'https://www.youtube.com/watch?v=aria_test';
        $this->deleteOembedTransient($url);
        $this->fakeOembedResponse($url, [
            'thumbnail_url' => 'https://img.youtube.com/vi/aria_test/hqdefault.jpg',
            'title' => 'Aria Test',
            'width' => 480,
            'height' => 360,
        ]);

        $output = $this->renderBlock(
            $this->makeAttributes(['url' => $url, 'provider' => 'youtube', 'videoTitle' => 'Test Title']),
            '',
        );

        $this->assertStringContainsString('<button', $output, 'Output should contain a button element');
        $this->assertStringContainsString(
            'aria-label="Play video: Test Title"',
            $output,
            'Play button should have aria-label with video title',
        );
        $this->restoreHttp();
    }

    public function test_poster_click_mode_accessibility(): void
    {
        $url = 'https://www.youtube.com/watch?v=poster_click_test';
        $this->deleteOembedTransient($url);
        $this->fakeOembedResponse($url, [
            'thumbnail_url' => 'https://img.youtube.com/vi/poster_click_test/hqdefault.jpg',
            'title' => 'Poster Click Test',
            'width' => 480,
            'height' => 360,
        ]);

        $output = $this->renderBlock(
            $this->makeAttributes([
                'url' => $url,
                'provider' => 'youtube',
                'videoTitle' => 'Poster Click Test',
                'clickBehavior' => 'poster',
            ]),
            '',
        );

        $this->assertStringContainsString(
            'role="button"',
            $output,
            'Poster click mode wrapper should have role="button"',
        );
        $this->assertStringContainsString(
            'tabindex="0"',
            $output,
            'Poster click mode wrapper should have tabindex="0"',
        );
        $this->assertStringContainsString(
            'aria-label="Play video: Poster Click Test"',
            $output,
            'Poster click mode wrapper should have aria-label',
        );
        $this->restoreHttp();
    }

    public function test_icon_click_mode_no_wrapper_role(): void
    {
        $url = 'https://www.youtube.com/watch?v=icon_click_test';
        $this->deleteOembedTransient($url);
        $this->fakeOembedResponse($url, [
            'thumbnail_url' => 'https://img.youtube.com/vi/icon_click_test/hqdefault.jpg',
            'title' => 'Icon Click Test',
            'width' => 480,
            'height' => 360,
        ]);

        $output = $this->renderBlock(
            $this->makeAttributes([
                'url' => $url,
                'provider' => 'youtube',
                'videoTitle' => 'Icon Click Test',
                'clickBehavior' => 'icon',
            ]),
            '',
        );

        $this->assertStringNotContainsString(
            'role="button"',
            $output,
            'Icon click mode wrapper should NOT have role="button"',
        );
        $this->restoreHttp();
    }

    public function test_data_video_id_attribute(): void
    {
        $url = 'https://www.youtube.com/watch?v=test123';
        $this->deleteOembedTransient($url);
        $this->fakeOembedResponse($url, [
            'thumbnail_url' => 'https://img.youtube.com/vi/test123/hqdefault.jpg',
            'title' => 'Video ID Test',
            'width' => 480,
            'height' => 360,
        ]);

        $output = $this->renderBlock(
            $this->makeAttributes(['url' => $url, 'provider' => 'youtube', 'videoTitle' => 'Video ID Test']),
            '',
        );

        $this->assertStringContainsString(
            'data-video-id="test123"',
            $output,
            'Output should contain data-video-id attribute with extracted YouTube ID',
        );
        $this->restoreHttp();
    }

    public function test_data_video_title_attribute(): void
    {
        $url = 'https://www.youtube.com/watch?v=title_test';
        $this->deleteOembedTransient($url);
        $this->fakeOembedResponse($url, [
            'thumbnail_url' => 'https://img.youtube.com/vi/title_test/hqdefault.jpg',
            'title' => 'Title Attr Test',
            'width' => 480,
            'height' => 360,
        ]);

        $output = $this->renderBlock(
            $this->makeAttributes([
                'url' => $url,
                'provider' => 'youtube',
                'videoTitle' => 'Title Attr Test',
            ]),
            '',
        );

        $this->assertStringContainsString(
            'data-video-title="Title Attr Test"',
            $output,
            'Output should contain data-video-title attribute',
        );
        $this->restoreHttp();
    }

    // --- Helpers ---

    private function makeAttributes(array $overrides = []): array
    {
        return array_merge(
            [
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
            ],
            $overrides,
        );
    }

    private function deleteOembedTransient(string $url): void
    {
        delete_transient('sitchco_voembed_' . md5($url));
    }

    private function fakeOembedResponse(string $url, array $data): void
    {
        $oembed_data = array_merge(
            [
                'version' => '1.0',
                'type' => 'video',
                'provider_name' => 'YouTube',
            ],
            $data,
        );

        $this->fakeHttp(function ($args, $request_url) use ($oembed_data) {
            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'body' => json_encode($oembed_data),
                'headers' => [],
                'cookies' => [],
            ];
        });
    }

    /**
     * Render the block's render.php template with the given attributes and content.
     */
    private function renderBlock(array $attributes, string $content): string
    {
        $module = $this->container->get(VideoBlock::class);
        $renderFile = $module->blocksPath()->append('video/render.php')->value();

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
