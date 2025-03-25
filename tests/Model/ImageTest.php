<?php

namespace Sitchco\Tests\Model;

use Sitchco\Model\Image;
use Sitchco\Support\CropDirection;
use Sitchco\Tests\Support\TestCase;

class ImageTest extends TestCase
{
    protected int $attachment_id;

    protected function setUp(): void
    {
        $this->attachment_id = $this->factory()->attachment->create_upload_object(
            SITCHCO_CORE_FIXTURES_DIR . '/sample-image.jpg'
        );
        update_post_meta($this->attachment_id, '_wp_attachment_image_alt', 'Image Description');
        parent::setUp();
    }

    protected function tearDown(): void
    {
        wp_delete_attachment($this->attachment_id, true);
        parent::tearDown();
    }

    public function test_render_image()
    {
        $image = Image::build(get_post($this->attachment_id));
        $image->setAttr('class', ['card-image', 'card-image--test'])->setAttr('data-test', 'image');
        $this->assertHTMLEquals(
            '<img src="' . $image->src() . '" width="880" height="660" class="card-image card-image--test" data-test="image" alt="Image Description" loading="lazy" />',
            (string) $image
        );
    }

    public function test_resize_image()
    {
        $image = Image::build(get_post($this->attachment_id));
        $image->resize(440, 330)->setLazy(false);
        $resized_src = str_replace('.jpg', '-440x330-c-default.jpg', $image->src());
        $this->assertHTMLEquals(
            '<img src="' . $resized_src . '" width="440" height="330" alt="Image Description" loading="eager" />',
            (string) $image
        );
    }

    public function test_resize_image_auto_height()
    {
        $image = Image::build(get_post($this->attachment_id));
        $image->setWidth(440);
        $resized_src = str_replace('.jpg', '-440x330-c-default.jpg', $image->src());
        $this->assertHTMLEquals(
            '<img src="' . $resized_src . '" width="440" height="330" alt="Image Description" loading="lazy" />',
            (string) $image
        );
    }

    public function test_resize_image_auto_width()
    {
        $image = Image::build(get_post($this->attachment_id));
        $image->setHeight(330);
        $resized_src = str_replace('.jpg', '-440x330-c-default.jpg', $image->src());
        $this->assertHTMLEquals(
            '<img src="' . $resized_src . '" width="440" height="330" alt="Image Description" loading="lazy" />',
            (string) $image
        );
    }

    public function test_resize_image_crop()
    {
        $image = Image::build(get_post($this->attachment_id));
        $image->resize(330, 330)->crop();
        $resized_src = str_replace('.jpg', '-330x330-c-center.jpg', $image->src());
        $this->assertHTMLEquals(
            '<img src="' . $resized_src . '" width="330" height="330" alt="Image Description" loading="lazy" />',
            (string) $image
        );
    }
}