<?php

namespace Sitchco\Tests\Modules\Cloudinary;

use Sitchco\Modules\Cloudinary\CloudinaryModule;
use Sitchco\Tests\TestCase;

class CloudinaryModuleTest extends TestCase
{
    private CloudinaryModule $module;

    private int $attachment_id;

    public static function setUpBeforeClass(): void
    {
        if (!defined('CLOUDINARY_CLOUD_NAME')) {
            define('CLOUDINARY_CLOUD_NAME', 'testcloud');
        }
        if (!defined('CLOUDINARY_FOLDER')) {
            define('CLOUDINARY_FOLDER', 'testfolder');
        }
        parent::setUpBeforeClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->module = $this->container->get(CloudinaryModule::class);
        $this->module->init();
        $this->attachment_id = $this->factory()->attachment->create_upload_object(
            SITCHCO_CORE_FIXTURES_DIR . '/sample-image.jpg',
        );
    }

    protected function tearDown(): void
    {
        wp_delete_attachment($this->attachment_id, true);
        parent::tearDown();
    }

    public function test_init_registers_hooks()
    {
        $this->assertNotFalse(has_filter('image_downsize', [$this->module, 'imageDownsize']));
        $this->assertNotFalse(has_filter('wp_content_img_tag', [$this->module, 'contentImgTag']));
        $this->assertNotFalse(has_filter('wp_calculate_image_srcset', [$this->module, 'calculateImageSrcset']));
        $this->assertNotFalse(has_filter('sitchco/image/resize', [$this->module, 'imageResize']));
    }

    public function test_imageDownsize_returns_cloudinary_url_for_registered_size()
    {
        $result = $this->module->imageDownsize(false, $this->attachment_id, 'medium');
        $this->assertIsArray($result);
        [$url, $width, $height, $is_intermediate] = $result;
        $this->assertStringContainsString('res.cloudinary.com/testcloud/image/upload/', $url);
        $this->assertStringContainsString('testfolder/', $url);
        $this->assertIsInt($width);
        $this->assertIsInt($height);
        $this->assertTrue($is_intermediate);
    }

    public function test_imageDownsize_returns_full_size_with_no_crop()
    {
        $result = $this->module->imageDownsize(false, $this->attachment_id, 'full');
        $this->assertIsArray($result);
        [$url, $width, $height, $is_intermediate] = $result;
        $this->assertStringContainsString('res.cloudinary.com/testcloud/image/upload/', $url);
        $this->assertEquals(880, $width);
        $this->assertEquals(660, $height);
        $this->assertFalse($is_intermediate);
    }

    public function test_imageDownsize_returns_false_in_admin_context()
    {
        set_current_screen('edit-post');
        $result = $this->module->imageDownsize(false, $this->attachment_id, 'medium');
        $this->assertFalse($result);
        set_current_screen('front');
    }

    public function test_imageDownsize_handles_array_size()
    {
        $result = $this->module->imageDownsize(false, $this->attachment_id, [400, 300]);
        $this->assertIsArray($result);
        [$url, $width, $height, $is_intermediate] = $result;
        $this->assertStringContainsString('w_400', $url);
        $this->assertStringContainsString('h_300', $url);
        $this->assertEquals(400, $width);
        $this->assertEquals(300, $height);
        $this->assertTrue($is_intermediate);
    }

    public function test_imageDownsize_returns_false_for_unknown_size()
    {
        $result = $this->module->imageDownsize(false, $this->attachment_id, 'nonexistent-size');
        $this->assertFalse($result);
    }

    public function test_contentImgTag_rewrites_local_upload_src()
    {
        $uploadUrl = wp_get_attachment_url($this->attachment_id);
        $html = '<img src="' . $uploadUrl . '" width="880" height="660" alt="test" />';
        $result = $this->module->contentImgTag($html, 'the_content', $this->attachment_id);
        $this->assertStringContainsString('res.cloudinary.com/testcloud/image/upload/', $result);
        $this->assertStringNotContainsString($uploadUrl, $result);
    }

    public function test_contentImgTag_does_not_rewrite_external_url()
    {
        $externalUrl = 'https://cdn.example.com/images/photo.jpg';
        $html = '<img src="' . $externalUrl . '" alt="external" />';
        $result = $this->module->contentImgTag($html, 'the_content', $this->attachment_id);
        $this->assertStringContainsString($externalUrl, $result);
        $this->assertStringNotContainsString('res.cloudinary.com', $result);
    }

    public function test_calculateImageSrcset_rewrites_all_source_urls()
    {
        $uploadUrl = wp_get_attachment_url($this->attachment_id);
        $sources = [
            300 => ['url' => $uploadUrl, 'descriptor' => 'w', 'value' => 300],
            600 => ['url' => $uploadUrl, 'descriptor' => 'w', 'value' => 600],
        ];
        $result = $this->module->calculateImageSrcset($sources, [300, 200], $uploadUrl, [], $this->attachment_id);
        foreach ($result as $source) {
            $this->assertStringContainsString('res.cloudinary.com/testcloud/image/upload/', $source['url']);
            $this->assertStringNotContainsString($uploadUrl, $source['url']);
        }
    }
}
