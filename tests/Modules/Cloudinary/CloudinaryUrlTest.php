<?php

namespace Sitchco\Tests\Modules\Cloudinary;

use Sitchco\Modules\Cloudinary\CloudinaryUrl;
use Sitchco\Support\CropDirection;
use Sitchco\Tests\TestCase;

class CloudinaryUrlTest extends TestCase
{
    private CloudinaryUrl $url;

    protected function setUp(): void
    {
        parent::setUp();
        $this->url = new CloudinaryUrl('testcloud', 'testfolder');
    }

    public function test_image_url_uses_image_upload_path()
    {
        $result = $this->url->buildUrl('https://example.com/wp-content/uploads/2024/01/photo.jpg');
        $this->assertStringContainsString('/testcloud/image/upload/', $result);
        $this->assertStringContainsString('f_auto,q_auto', $result);
        $this->assertStringEndsWith('/testfolder/2024/01/photo.jpg', $result);
    }

    public function test_image_url_includes_dimension_transforms()
    {
        $result = $this->url->buildUrl(
            'https://example.com/wp-content/uploads/2024/01/photo.jpg',
            800,
            600,
            CropDirection::CENTER,
        );
        $this->assertStringContainsString('w_800', $result);
        $this->assertStringContainsString('h_600', $result);
        $this->assertStringContainsString('c_fill', $result);
        $this->assertStringContainsString('g_center', $result);
        $this->assertStringContainsString('/image/upload/', $result);
    }

    public function test_video_url_uses_video_upload_path()
    {
        $result = $this->url->buildUrl('https://example.com/wp-content/uploads/2024/01/clip.mp4');
        $this->assertStringContainsString('/testcloud/video/upload/', $result);
        $this->assertStringContainsString('f_auto,q_auto', $result);
        $this->assertStringEndsWith('/testfolder/2024/01/clip.mp4', $result);
    }

    public function test_video_url_omits_dimension_transforms()
    {
        $result = $this->url->buildUrl(
            'https://example.com/wp-content/uploads/2024/01/clip.mp4',
            1920,
            1080,
            CropDirection::CENTER,
        );
        $this->assertStringNotContainsString('w_', $result);
        $this->assertStringNotContainsString('h_', $result);
        $this->assertStringNotContainsString('c_fill', $result);
        $this->assertStringContainsString('f_auto,q_auto', $result);
        $this->assertStringContainsString('/video/upload/', $result);
    }

    public function test_case_insensitive_video_extension()
    {
        $result = $this->url->buildUrl('https://example.com/wp-content/uploads/2024/01/clip.MP4');
        $this->assertStringContainsString('/video/upload/', $result);
    }

    /**
     * @dataProvider videoExtensionProvider
     */
    public function test_all_video_extensions(string $extension)
    {
        $result = $this->url->buildUrl("https://example.com/wp-content/uploads/2024/01/file.{$extension}");
        $this->assertStringContainsString('/video/upload/', $result);
    }

    public static function videoExtensionProvider(): array
    {
        return [
            'mp4' => ['mp4'],
            'webm' => ['webm'],
            'mov' => ['mov'],
            'avi' => ['avi'],
            'mkv' => ['mkv'],
            'ogv' => ['ogv'],
        ];
    }

    public function test_unknown_extension_defaults_to_image()
    {
        $result = $this->url->buildUrl('https://example.com/wp-content/uploads/2024/01/doc.pdf');
        $this->assertStringContainsString('/image/upload/', $result);
    }

    public function test_non_upload_url_passes_through_unchanged()
    {
        $externalUrl = 'https://cdn.example.com/images/photo.jpg';
        $result = $this->url->buildUrl($externalUrl);
        $this->assertSame($externalUrl, $result);
    }

    public function test_svg_gets_quality_only_no_format_or_dimensions()
    {
        $result = $this->url->buildUrl(
            'https://example.com/wp-content/uploads/2024/01/icon.svg',
            400,
            300,
            CropDirection::CENTER,
        );
        $this->assertEquals(
            'https://res.cloudinary.com/testcloud/image/upload/q_auto/testfolder/2024/01/icon.svg',
            $result,
        );
    }

    public function test_svg_with_query_string_still_detected_as_svg()
    {
        $result = $this->url->buildUrl(
            'https://example.com/wp-content/uploads/2024/01/icon.svg?ver=1',
            400,
            300,
            CropDirection::CENTER,
        );
        $this->assertEquals(
            'https://res.cloudinary.com/testcloud/image/upload/q_auto/testfolder/2024/01/icon.svg',
            $result,
        );
    }

    /**
     * @dataProvider gravityMapProvider
     */
    public function test_gravity_map(CropDirection $crop, string $expectedGravity)
    {
        $result = $this->url->buildUrl('https://example.com/wp-content/uploads/2024/01/photo.jpg', 800, 600, $crop);
        $this->assertEquals(
            "https://res.cloudinary.com/testcloud/image/upload/f_auto,q_auto,w_800,h_600,c_fill,{$expectedGravity}/testfolder/2024/01/photo.jpg",
            $result,
        );
    }

    public static function gravityMapProvider(): array
    {
        return [
            'CENTER' => [CropDirection::CENTER, 'g_center'],
            'TOP' => [CropDirection::TOP, 'g_north'],
            'BOTTOM' => [CropDirection::BOTTOM, 'g_south'],
            'LEFT' => [CropDirection::LEFT, 'g_west'],
            'RIGHT' => [CropDirection::RIGHT, 'g_east'],
            'TOP_CENTER' => [CropDirection::TOP_CENTER, 'g_north'],
            'BOTTOM_CENTER' => [CropDirection::BOTTOM_CENTER, 'g_south'],
        ];
    }

    public function test_crop_direction_none_applies_dimensions_without_fill_or_gravity()
    {
        $result = $this->url->buildUrl(
            'https://example.com/wp-content/uploads/2024/01/photo.jpg',
            800,
            600,
            CropDirection::NONE,
        );
        $this->assertEquals(
            'https://res.cloudinary.com/testcloud/image/upload/f_auto,q_auto,w_800,h_600/testfolder/2024/01/photo.jpg',
            $result,
        );
    }

    public function test_cloudinary_url_passes_through_unchanged()
    {
        $cloudinaryUrl = 'https://res.cloudinary.com/mycloud/image/upload/f_auto,q_auto/folder/2024/01/photo.jpg';
        $result = $this->url->buildUrl($cloudinaryUrl);
        $this->assertSame($cloudinaryUrl, $result);
    }
}
