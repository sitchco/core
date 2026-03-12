<?php

namespace Sitchco\Tests\Modules\UIModal;

use Sitchco\Modules\UIModal\ModalData;
use Sitchco\Modules\UIModal\ModalType;
use Sitchco\Tests\TestCase;
use Timber\Timber;

class ModalDataTest extends TestCase
{
    public function test_id_prefixes_digit_starting_ids()
    {
        $normal = new ModalData('about-us', '', '', ModalType::BOX);
        $this->assertEquals('about-us', $normal->id());

        $digitPrefixed = new ModalData('42-things', '', '', ModalType::BOX);
        $this->assertEquals('modal-42-things', $digitPrefixed->id());

        $allDigit = new ModalData('123', '', '', ModalType::BOX);
        $this->assertEquals('modal-123', $allDigit->id());
    }

    public function test_fromPost_omits_heading_when_content_contains_heading_tag()
    {
        $post_id = $this->factory()->post->create([
            'post_title' => 'Modal Title',
            'post_name' => 'modal-title',
            'post_content' => '<h2>Section Title</h2><p>Body text</p>',
        ]);
        $post = Timber::get_post($post_id);
        $modal = ModalData::fromPost($post, ModalType::BOX);

        $this->assertEmpty($modal->heading());
    }

    public function test_fromPost_uses_post_title_when_content_has_no_heading()
    {
        $post_id = $this->factory()->post->create([
            'post_title' => 'Modal Title',
            'post_name' => 'modal-title',
            'post_content' => '<p>Just a paragraph of content.</p>',
        ]);
        $post = Timber::get_post($post_id);
        $modal = ModalData::fromPost($post, ModalType::CENTERED);

        $this->assertEquals('Modal Title', $modal->heading());
    }

    public function test_fromPost_uses_excerpt_when_flag_is_true()
    {
        $post_id = $this->factory()->post->create([
            'post_title' => 'Modal Title',
            'post_name' => 'modal-title',
            'post_content' => '<p>This is the full content of the modal.</p>',
            'post_excerpt' => 'Short summary',
        ]);
        $post = Timber::get_post($post_id);

        $withContent = ModalData::fromPost($post, ModalType::BOX);
        $withExcerpt = ModalData::fromPost($post, ModalType::BOX, excerpt: true);

        $this->assertStringContainsString('full content of the modal', $withContent->content());
        $this->assertStringContainsString('Short summary', $withExcerpt->content());
    }

    public function test_video_type_modal_from_raw_strings(): void
    {
        $modal = new ModalData('test-video', 'Video Title', '<div>player</div>', ModalType::VIDEO);

        $this->assertEquals('test-video', $modal->id());
        $this->assertEquals('Video Title', $modal->heading());
        $this->assertEquals('<div>player</div>', $modal->content());
        $this->assertEquals(ModalType::VIDEO, $modal->type);
    }
}
