<?php

namespace Sitchco\Tests\Modules\VideoBlock;

use Sitchco\Modules\UIModal\UIModal;
use Sitchco\Modules\VideoBlock\VideoBlock;
use Sitchco\Tests\TestCase;
use WP_Block_Type_Registry;

class VideoBlockTest extends TestCase
{
    protected function tearDown(): void
    {
        // Reset UIModal's modalsLoaded array to prevent test pollution
        $uiModal = $this->container->get(UIModal::class);
        $ref = new \ReflectionProperty(UIModal::class, 'modalsLoaded');
        $ref->setValue($uiModal, []);
        parent::tearDown();
    }

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
            'https://img.youtube.com/vi/oembed_thumb_test/maxresdefault.jpg',
            $output,
            'Poster img src should be the upgraded (maxresdefault) oEmbed thumbnail URL',
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
        $url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
        $this->deleteOembedTransient($url);
        $this->fakeOembedResponse($url, [
            'thumbnail_url' => 'https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
            'title' => 'Video ID Test',
            'width' => 480,
            'height' => 360,
        ]);

        $output = $this->renderBlock(
            $this->makeAttributes(['url' => $url, 'provider' => 'youtube', 'videoTitle' => 'Video ID Test']),
            '',
        );

        $this->assertStringContainsString(
            'data-video-id="dQw4w9WgXcQ"',
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

    // --- Modal Rendering ---

    public function test_modal_mode_renders_poster_and_dialog(): void
    {
        $url = 'https://www.youtube.com/watch?v=modal_test01';
        $this->deleteOembedTransient($url);
        $this->fakeOembedResponse($url, [
            'thumbnail_url' => 'https://img.youtube.com/vi/modal_test01/hqdefault.jpg',
            'title' => 'Modal Test Video',
            'width' => 480,
            'height' => 360,
        ]);

        $result = $this->renderBlockWithModals(
            $this->makeAttributes([
                'url' => $url,
                'provider' => 'youtube',
                'videoTitle' => 'Modal Test Video',
                'displayMode' => 'modal',
                'modalId' => 'modal-test-video',
            ]),
            '',
        );

        $this->assertStringContainsString(
            'sitchco-video',
            $result['page'],
            'Modal mode should render poster wrapper on page',
        );
        $this->assertStringContainsString(
            'data-modal-id',
            $result['page'],
            'Modal mode wrapper should have data-modal-id',
        );
        $this->assertStringContainsString('<dialog', $result['footer'], 'Modal mode should produce dialog in footer');
        $this->assertStringContainsString(
            'sitchco-modal--video',
            $result['footer'],
            'Dialog should have video modal class',
        );
        $this->restoreHttp();
    }

    public function test_modal_mode_poster_stays_on_page(): void
    {
        $url = 'https://www.youtube.com/watch?v=modal_poster1';
        $this->deleteOembedTransient($url);
        $this->fakeOembedResponse($url, [
            'thumbnail_url' => 'https://img.youtube.com/vi/modal_poster1/hqdefault.jpg',
            'title' => 'Modal Poster Test',
            'width' => 480,
            'height' => 360,
        ]);

        $result = $this->renderBlockWithModals(
            $this->makeAttributes([
                'url' => $url,
                'provider' => 'youtube',
                'videoTitle' => 'Modal Poster Test',
                'displayMode' => 'modal',
                'modalId' => 'modal-poster-test',
            ]),
            '',
        );

        $this->assertStringContainsString(
            'sitchco-video__poster-img',
            $result['page'],
            'Modal mode should show poster image',
        );
        $this->assertStringContainsString('<button', $result['page'], 'Modal mode should show play button');
        $this->restoreHttp();
    }

    public function test_modal_only_renders_no_visible_html(): void
    {
        $url = 'https://www.youtube.com/watch?v=modalonly_te1';
        $this->deleteOembedTransient($url);
        $this->fakeOembedResponse($url, [
            'thumbnail_url' => 'https://img.youtube.com/vi/modalonly_te1/hqdefault.jpg',
            'title' => 'Modal Only Test',
            'width' => 480,
            'height' => 360,
        ]);

        $result = $this->renderBlockWithModals(
            $this->makeAttributes([
                'url' => $url,
                'provider' => 'youtube',
                'videoTitle' => 'Modal Only Test',
                'displayMode' => 'modal-only',
                'modalId' => 'modal-only-test',
            ]),
            '',
        );

        $this->assertEmpty($result['page'], 'Modal-only mode should render no visible HTML on page');
        $this->restoreHttp();
    }

    public function test_modal_only_still_queues_dialog(): void
    {
        $url = 'https://www.youtube.com/watch?v=modalonly_q01';
        $this->deleteOembedTransient($url);
        $this->fakeOembedResponse($url, [
            'thumbnail_url' => 'https://img.youtube.com/vi/modalonly_q01/hqdefault.jpg',
            'title' => 'Modal Only Queue Test',
            'width' => 480,
            'height' => 360,
        ]);

        $result = $this->renderBlockWithModals(
            $this->makeAttributes([
                'url' => $url,
                'provider' => 'youtube',
                'videoTitle' => 'Modal Only Queue Test',
                'displayMode' => 'modal-only',
                'modalId' => 'modal-only-queue',
            ]),
            '',
        );

        $this->assertStringContainsString(
            '<dialog',
            $result['footer'],
            'Modal-only should still queue dialog in footer',
        );
        $this->restoreHttp();
    }

    public function test_modal_id_is_slugified(): void
    {
        $url = 'https://www.youtube.com/watch?v=slug_test_01';
        $this->deleteOembedTransient($url);
        $this->fakeOembedResponse($url, [
            'thumbnail_url' => 'https://img.youtube.com/vi/slug_test_01/hqdefault.jpg',
            'title' => 'Slug Test',
            'width' => 480,
            'height' => 360,
        ]);

        $result = $this->renderBlockWithModals(
            $this->makeAttributes([
                'url' => $url,
                'provider' => 'youtube',
                'videoTitle' => 'My Video Title',
                'displayMode' => 'modal',
                'modalId' => 'My Video Title',
            ]),
            '',
        );

        $this->assertStringContainsString('id="my-video-title"', $result['footer'], 'Modal ID should be slugified');
        $this->restoreHttp();
    }

    public function test_modal_dialog_has_video_title_heading(): void
    {
        $url = 'https://www.youtube.com/watch?v=heading_tes1';
        $this->deleteOembedTransient($url);
        $this->fakeOembedResponse($url, [
            'thumbnail_url' => 'https://img.youtube.com/vi/heading_tes1/hqdefault.jpg',
            'title' => 'Heading Test',
            'width' => 480,
            'height' => 360,
        ]);

        $result = $this->renderBlockWithModals(
            $this->makeAttributes([
                'url' => $url,
                'provider' => 'youtube',
                'videoTitle' => 'My Heading Title',
                'displayMode' => 'modal',
                'modalId' => 'heading-test',
            ]),
            '',
        );

        $this->assertStringContainsString('<h2', $result['footer'], 'Dialog should contain h2 heading');
        $this->assertStringContainsString(
            'My Heading Title',
            $result['footer'],
            'Dialog heading should contain video title',
        );
        $this->restoreHttp();
    }

    public function test_modal_content_has_data_attributes(): void
    {
        $url = 'https://www.youtube.com/watch?v=data_attr_t1';
        $this->deleteOembedTransient($url);
        $this->fakeOembedResponse($url, [
            'thumbnail_url' => 'https://img.youtube.com/vi/data_attr_t1/hqdefault.jpg',
            'title' => 'Data Attr Test',
            'width' => 480,
            'height' => 360,
        ]);

        $result = $this->renderBlockWithModals(
            $this->makeAttributes([
                'url' => $url,
                'provider' => 'youtube',
                'videoTitle' => 'Data Attr Test',
                'displayMode' => 'modal',
                'modalId' => 'data-attr-test',
            ]),
            '',
        );

        $this->assertStringContainsString('data-url=', $result['footer'], 'Modal content should have data-url');
        $this->assertStringContainsString(
            'data-provider=',
            $result['footer'],
            'Modal content should have data-provider',
        );
        $this->assertStringContainsString(
            'data-video-id=',
            $result['footer'],
            'Modal content should have data-video-id',
        );
        $this->restoreHttp();
    }

    public function test_modal_content_has_aspect_ratio(): void
    {
        $url = 'https://www.youtube.com/watch?v=aspect_rat01';
        $this->deleteOembedTransient($url);
        $this->fakeOembedResponse($url, [
            'thumbnail_url' => 'https://img.youtube.com/vi/aspect_rat01/hqdefault.jpg',
            'title' => 'Aspect Ratio Test',
            'width' => 480,
            'height' => 360,
        ]);

        $result = $this->renderBlockWithModals(
            $this->makeAttributes([
                'url' => $url,
                'provider' => 'youtube',
                'videoTitle' => 'Aspect Ratio Test',
                'displayMode' => 'modal',
                'modalId' => 'aspect-ratio-test',
            ]),
            '',
        );

        $this->assertStringContainsString(
            'aspect-ratio:',
            $result['footer'],
            'Modal content should have aspect-ratio style',
        );
        $this->restoreHttp();
    }

    public function test_modal_has_oembed_poster_flag(): void
    {
        // Test 1: No InnerBlocks (oEmbed poster used) -> data-has-oembed-poster="true"
        $url = 'https://www.youtube.com/watch?v=poster_flag1';
        $this->deleteOembedTransient($url);
        $this->fakeOembedResponse($url, [
            'thumbnail_url' => 'https://img.youtube.com/vi/poster_flag1/hqdefault.jpg',
            'title' => 'Poster Flag Test',
            'width' => 480,
            'height' => 360,
        ]);

        $result = $this->renderBlockWithModals(
            $this->makeAttributes([
                'url' => $url,
                'provider' => 'youtube',
                'videoTitle' => 'Poster Flag Test',
                'displayMode' => 'modal',
                'modalId' => 'poster-flag-test',
            ]),
            '',
        );

        $this->assertStringContainsString(
            'data-has-oembed-poster="true"',
            $result['footer'],
            'Without InnerBlocks, modal should have data-has-oembed-poster="true"',
        );
        $this->restoreHttp();

        // Reset modals between sub-tests
        $uiModal = $this->container->get(UIModal::class);
        $ref = new \ReflectionProperty(UIModal::class, 'modalsLoaded');
        $ref->setValue($uiModal, []);

        // Test 2: With InnerBlocks -> data-has-oembed-poster="false"
        $url2 = 'https://www.youtube.com/watch?v=poster_flag2';
        $this->deleteOembedTransient($url2);
        $this->fakeOembedResponse($url2, [
            'thumbnail_url' => 'https://img.youtube.com/vi/poster_flag2/hqdefault.jpg',
            'title' => 'Poster Flag Test 2',
            'width' => 480,
            'height' => 360,
        ]);

        $result2 = $this->renderBlockWithModals(
            $this->makeAttributes([
                'url' => $url2,
                'provider' => 'youtube',
                'videoTitle' => 'Poster Flag Test 2',
                'displayMode' => 'modal',
                'modalId' => 'poster-flag-test-2',
            ]),
            '<p>Custom poster</p>',
        );

        $this->assertStringContainsString(
            'data-has-oembed-poster="false"',
            $result2['footer'],
            'With InnerBlocks, modal should have data-has-oembed-poster="false"',
        );
        $this->restoreHttp();
    }

    public function test_inline_mode_no_modal_queued(): void
    {
        $url = 'https://www.youtube.com/watch?v=inline_nomod';
        $this->deleteOembedTransient($url);
        $this->fakeOembedResponse($url, [
            'thumbnail_url' => 'https://img.youtube.com/vi/inline_nomod/hqdefault.jpg',
            'title' => 'Inline No Modal',
            'width' => 480,
            'height' => 360,
        ]);

        $result = $this->renderBlockWithModals(
            $this->makeAttributes([
                'url' => $url,
                'provider' => 'youtube',
                'videoTitle' => 'Inline No Modal',
                'displayMode' => 'inline',
            ]),
            '',
        );

        $this->assertNotEmpty($result['page'], 'Inline mode should render page content');
        $this->assertEmpty($result['footer'], 'Inline mode should not queue any modal dialog');
        $this->restoreHttp();
    }

    // --- Extension Points ---

    public function test_hook_suffix_produces_correct_filter_name(): void
    {
        $this->assertSame(
            'sitchco/video/play_icon_svg',
            VideoBlock::hookName('play_icon_svg'),
            'VideoBlock::hookName("play_icon_svg") should return "sitchco/video/play_icon_svg"',
        );
    }

    public function test_play_icon_svg_filter_is_applied(): void
    {
        $url = 'https://www.youtube.com/watch?v=filter_apply1';
        $this->deleteOembedTransient($url);
        $this->fakeOembedResponse($url, [
            'thumbnail_url' => 'https://img.youtube.com/vi/filter_apply1/hqdefault.jpg',
            'title' => 'Filter Apply Test',
            'width' => 480,
            'height' => 360,
        ]);

        add_filter('sitchco/video/play_icon_svg', function ($svg) {
            return '<svg class="custom-icon">replaced</svg>';
        });

        $output = $this->renderBlock(
            $this->makeAttributes([
                'url' => $url,
                'provider' => 'youtube',
                'videoTitle' => 'Filter Apply Test',
            ]),
            '',
        );

        remove_all_filters('sitchco/video/play_icon_svg');

        $this->assertStringContainsString(
            'custom-icon',
            $output,
            'Replacement SVG from filter should appear in output',
        );
        $this->assertStringContainsString(
            'aria-label="Play video:',
            $output,
            'Button wrapper aria-label should be preserved when SVG is replaced',
        );
        $this->restoreHttp();
    }

    public function test_play_icon_svg_filter_receives_correct_args(): void
    {
        $url = 'https://www.youtube.com/watch?v=filter_args01';
        $this->deleteOembedTransient($url);
        $this->fakeOembedResponse($url, [
            'thumbnail_url' => 'https://img.youtube.com/vi/filter_args01/hqdefault.jpg',
            'title' => 'Filter Args Test',
            'width' => 480,
            'height' => 360,
        ]);

        $captured = [];
        add_filter(
            'sitchco/video/play_icon_svg',
            function ($svg, $provider, $style) use (&$captured) {
                $captured = ['provider' => $provider, 'style' => $style];
                return $svg;
            },
            10,
            3,
        );

        $this->renderBlock(
            $this->makeAttributes([
                'url' => $url,
                'provider' => 'youtube',
                'videoTitle' => 'Filter Args Test',
                'playIconStyle' => 'dark',
            ]),
            '',
        );

        remove_all_filters('sitchco/video/play_icon_svg');

        $this->assertSame('youtube', $captured['provider'] ?? null, 'Filter should receive provider as second arg');
        $this->assertSame('dark', $captured['style'] ?? null, 'Filter should receive play_icon_style as third arg');
        $this->restoreHttp();
    }

    // --- oEmbed Failure / Fallback ---

    public function test_render_oembed_failure_renders_link_fallback(): void
    {
        $url = 'https://vimeo.com/999999999';
        $this->deleteOembedTransient($url);
        $this->fakeOembedFailure();

        $output = $this->renderBlock(
            $this->makeAttributes(['url' => $url, 'provider' => 'vimeo', 'videoTitle' => 'Broken Video']),
            '',
        );

        $this->assertStringContainsString(
            'sitchco-video__fallback-link',
            $output,
            'oEmbed failure should render fallback link',
        );
        $this->assertStringContainsString('Watch on Vimeo', $output, 'Fallback should show "Watch on Vimeo" label');
        $this->assertStringNotContainsString('<button', $output, 'oEmbed failure should NOT render play button');
        $this->restoreHttp();
    }

    public function test_render_oembed_failure_no_interactive_attributes(): void
    {
        $url = 'https://vimeo.com/999999998';
        $this->deleteOembedTransient($url);
        $this->fakeOembedFailure();

        $output = $this->renderBlock(
            $this->makeAttributes([
                'url' => $url,
                'provider' => 'vimeo',
                'videoTitle' => 'Broken Video',
                'clickBehavior' => 'poster',
            ]),
            '',
        );

        $this->assertStringNotContainsString('role="button"', $output, 'oEmbed failure should NOT have role="button"');
        $this->assertStringNotContainsString('tabindex="0"', $output, 'oEmbed failure should NOT have tabindex');
        $this->assertStringNotContainsString(
            'aria-label="Play video:',
            $output,
            'oEmbed failure should NOT have play aria-label on wrapper',
        );
        $this->restoreHttp();
    }

    public function test_render_oembed_failure_with_inner_blocks_still_renders_normally(): void
    {
        $url = 'https://vimeo.com/999999997';
        $this->deleteOembedTransient($url);
        $this->fakeOembedFailure();

        $output = $this->renderBlock(
            $this->makeAttributes(['url' => $url, 'provider' => 'vimeo', 'videoTitle' => 'Custom Poster Video']),
            '<p>Custom poster</p>',
        );

        $this->assertStringContainsString(
            '<p>Custom poster</p>',
            $output,
            'InnerBlocks content should still render when oEmbed fails',
        );
        $this->assertStringContainsString(
            '<button',
            $output,
            'Play button should still render with InnerBlocks even when oEmbed fails',
        );
        $this->assertStringNotContainsString(
            'sitchco-video__fallback-link',
            $output,
            'Fallback link should NOT appear when InnerBlocks are present',
        );
        $this->restoreHttp();
    }

    public function test_modal_mode_oembed_failure_no_modal_queued(): void
    {
        $url = 'https://vimeo.com/999999996';
        $this->deleteOembedTransient($url);
        $this->fakeOembedFailure();

        $result = $this->renderBlockWithModals(
            $this->makeAttributes([
                'url' => $url,
                'provider' => 'vimeo',
                'videoTitle' => 'Modal Broken Video',
                'displayMode' => 'modal',
                'modalId' => 'modal-broken',
            ]),
            '',
        );

        $this->assertStringNotContainsString(
            '<dialog',
            $result['footer'],
            'oEmbed failure in modal mode should NOT queue a dialog',
        );
        $this->assertStringContainsString(
            'sitchco-video__fallback-link',
            $result['page'],
            'oEmbed failure in modal mode should render link fallback on page',
        );
        $this->restoreHttp();
    }

    public function test_render_oembed_failure_has_unavailable_data_attribute(): void
    {
        $url = 'https://vimeo.com/999999995';
        $this->deleteOembedTransient($url);
        $this->fakeOembedFailure();

        $output = $this->renderBlock(
            $this->makeAttributes(['url' => $url, 'provider' => 'vimeo', 'videoTitle' => 'Unavailable Video']),
            '',
        );

        $this->assertStringContainsString(
            'data-video-unavailable="true"',
            $output,
            'oEmbed failure should have data-video-unavailable attribute',
        );
        $this->restoreHttp();
    }

    public function test_modal_only_oembed_failure_renders_nothing(): void
    {
        $url = 'https://vimeo.com/999999994';
        $this->deleteOembedTransient($url);
        $this->fakeOembedFailure();

        $result = $this->renderBlockWithModals(
            $this->makeAttributes([
                'url' => $url,
                'provider' => 'vimeo',
                'videoTitle' => 'Modal Only Broken',
                'displayMode' => 'modal-only',
                'modalId' => 'modal-only-broken',
            ]),
            '',
        );

        $this->assertEmpty($result['page'], 'Modal-only with oEmbed failure should render empty page');
        $this->assertEmpty($result['footer'], 'Modal-only with oEmbed failure should render empty footer');
        $this->restoreHttp();
    }

    // --- Domain Restriction ---

    public function test_render_domain_restricted_renders_fallback(): void
    {
        $url = 'https://vimeo.com/1167868106/c189f4fa10';
        $this->deleteOembedTransient($url);
        $this->fakeOembedResponse($url, [
            'type' => 'video',
            'provider_name' => 'Vimeo',
            'width' => 240,
            'height' => 426,
            'domain_status_code' => 403,
            'video_id' => 1167868106,
        ]);

        $output = $this->renderBlock(
            $this->makeAttributes(['url' => $url, 'provider' => 'vimeo', 'videoTitle' => 'Restricted Video']),
            '',
        );

        $this->assertStringContainsString(
            'sitchco-video__fallback-link',
            $output,
            'Domain-restricted video should render fallback link',
        );
        $this->assertStringContainsString('Watch on Vimeo', $output, 'Fallback should show "Watch on Vimeo" label');
        $this->assertStringNotContainsString(
            '<button',
            $output,
            'Domain-restricted video should NOT render play button',
        );
        $this->assertStringContainsString(
            'data-video-unavailable="true"',
            $output,
            'Domain-restricted video should have data-video-unavailable attribute',
        );
        $this->restoreHttp();
    }

    public function test_modal_mode_domain_restricted_no_modal_queued(): void
    {
        $url = 'https://vimeo.com/1167868107/abc123';
        $this->deleteOembedTransient($url);
        $this->fakeOembedResponse($url, [
            'type' => 'video',
            'provider_name' => 'Vimeo',
            'width' => 240,
            'height' => 426,
            'domain_status_code' => 403,
            'video_id' => 1167868107,
        ]);

        $result = $this->renderBlockWithModals(
            $this->makeAttributes([
                'url' => $url,
                'provider' => 'vimeo',
                'videoTitle' => 'Modal Restricted',
                'displayMode' => 'modal',
                'modalId' => 'modal-restricted',
            ]),
            '',
        );

        $this->assertStringNotContainsString(
            '<dialog',
            $result['footer'],
            'Domain-restricted video in modal mode should NOT queue a dialog',
        );
        $this->assertStringContainsString(
            'sitchco-video__fallback-link',
            $result['page'],
            'Domain-restricted video in modal mode should render link fallback on page',
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
            ],
            $overrides,
        );
    }

    private function deleteOembedTransient(string $url): void
    {
        delete_transient('sitchco_voembed_' . md5($url));
    }

    private function fakeOembedFailure(): void
    {
        $this->fakeHttp(function () {
            return [
                'response' => ['code' => 404, 'message' => 'Not Found'],
                'body' => '',
                'headers' => [],
                'cookies' => [],
            ];
        });
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

        $block = new \stdClass();
        $block->inner_blocks = $content !== '' ? [true] : [];

        ob_start();
        // Scope the include so that $attributes, $content, $block are available
        (function (string $_file, array $attributes, string $content, object $block) {
            include $_file;
        })($renderFile, $attributes, $content, $block);
        return ob_get_clean();
    }

    /**
     * Render the block and capture both page output and modal footer output.
     *
     * @return array{page: string, footer: string}
     */
    private function renderBlockWithModals(array $attributes, string $content): array
    {
        $pageOutput = $this->renderBlock($attributes, $content);

        $uiModal = $this->container->get(UIModal::class);
        ob_start();
        $uiModal->unloadModals();
        $footerOutput = ob_get_clean();

        return ['page' => $pageOutput, 'footer' => $footerOutput];
    }
}
