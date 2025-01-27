<?php

namespace Sitchco\Tests;

use Sitchco\Model\Post;
use Sitchco\Repository\PostRepository;
use Timber\Timber;
use WPTest\Test\TestCase;

/**
 * class PostRepositoryTest
 * @package Sitchco\Tests
 */
class PostRepositoryTest extends TestCase
{
    // TODO: create "setup" method (confirm this)
    public function test_update_post()
    {
        $title = 'Test Post (Repository Add)';
        $post_id = $this->factory()->post->create([
            'post_title' => $title,
            'post_content' => 'This post currently has no meta attached.',
        ]);
        $Post = Timber::get_post($post_id);
        $this->assertInstanceOf(Post::class, $Post);
        $modified_title = "Modified: {$title}";

        // Set a thumbnail (note: this does not set the thumbnail, instead just attaches to the post)
        $thumbnail_id = $this->factory()->attachment->create_upload_object(
            SITCHCO_CORE_DIR . '/tests/assets/sample-image.jpg',
            $post_id
        );

        // update native and custom keys
        $Post->wp_object()->post_title = $modified_title;
        $Post->custom_string_key = 'some string';
        $Post->custom_number_key = 123;
        $Post->thumbnail_id = $thumbnail_id;

        // update with custom setter
        $some_custom_value = 'Testing';
        $Post->some_custom_value = $some_custom_value;
        (new PostRepository())->add($Post);

        // re-fetch the data
        $Post = Timber::get_post($post_id);
        $this->assertEquals($modified_title, $Post->post_title);
        $this->assertEquals('some string', $Post->custom_string_key);
        $this->assertEquals(123, $Post->custom_number_key);
        $this->assertEquals("Custom Setter: {$some_custom_value}", $Post->some_custom_value);

        // Check if the thumbnail was set
        $this->assertEquals($thumbnail_id, $Post->thumbnail_id(), 'The post thumbnail was not set correctly.');
    }
}