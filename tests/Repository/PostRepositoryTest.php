<?php

namespace Sitchco\Tests;

use Sitchco\Repository\PostRepository;
use Sitchco\Tests\Support\PostTester;
use Timber\Timber;
use WPTest\Test\TestCase;

/**
 * class PostRepositoryTest
 * @package Sitchco\Tests
 */
class PostRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        add_filter('timber/post/classmap', function($classmap) {
            $classmap['post'] = PostTester::class;
            return $classmap;
        });
    }

    public function test_create_post()
    {
        $title = 'Created Post';
        $createdPost = PostTester::create();
        $this->assertInstanceOf(PostTester::class, $createdPost);

        $createdPost->wp_object()->post_title = $title;
        (new PostRepository())->add($createdPost);

        $returnedPost = Timber::get_post($createdPost->ID);
        $this->assertEquals($createdPost->wp_object()->ID, $returnedPost->wp_object()->ID);
        $this->assertEquals($title, $returnedPost->post_title);
    }

    public function test_update_post()
    {
        $title = 'Test Post (Repository Add)';
        $post_id = $this->factory()->post->create([
            'post_title' => $title,
            'post_content' => 'This post currently has no meta attached.',
        ]);
        $post = Timber::get_post($post_id);
        $this->assertInstanceOf(PostTester::class, $post);
        $modified_title = "Modified: {$title}";

        // Set a thumbnail (note: this does not set the thumbnail, instead just attaches to the post)
        $thumbnail_id = $this->factory()->attachment->create_upload_object(
            SITCHCO_CORE_DIR . '/tests/assets/sample-image.jpg',
            $post_id
        );

        // update native and custom keys
        $post->wp_object()->post_title = $modified_title;
        $post->custom_string_key = 'some string';
        $post->custom_number_key = 123;
        $post->thumbnail_id = $thumbnail_id;

        // update with custom setter
        $some_custom_value = 'Testing';
        // TODO: this is not retaining its data for some reason.
        $post->some_custom_key = $some_custom_value;
        (new PostRepository())->add($post);

        // re-fetch the data
        $post = Timber::get_post($post_id);
        $this->assertEquals($modified_title, $post->post_title);
        $this->assertEquals('some string', $post->custom_string_key);
        $this->assertEquals(123, $post->custom_number_key);
        $this->assertEquals("Custom Setter: {$some_custom_value}", $post->some_custom_key);

        // Check if the thumbnail was set
        $this->assertEquals($thumbnail_id, $post->thumbnail_id(), 'The post thumbnail was not set correctly.');
    }
}