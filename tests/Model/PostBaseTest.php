<?php

namespace Sitchco\Tests;

use Sitchco\Tests\Support\PostTester;
use Timber\Timber;
use WPTest\Test\TestCase;

/**
 * class PostBaseTest
 * @package Sitchco\Tests
 */
class PostBaseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        add_filter('timber/post/classmap', function($classmap) {
            $classmap['post'] = PostTester::class;
            return $classmap;
        });
    }

    public function test_get_method_with_wordpress_keys()
    {
        $post_title = 'Test Post Title';
        $post_content = 'Lorem ipsum sit amet dolor.';
        $post_excerpt = 'Truncated text';
        $post_id = $this->factory()->post->create([
            'post_title' => $post_title,
            'post_content' => $post_content,
            'post_excerpt' => $post_excerpt
        ]);
        $post = Timber::get_post($post_id);
        $this->assertInstanceOf(PostTester::class, $post);

        // test against different variations
        $this->assertEquals($post_id, $post->ID);
        $this->assertEquals($post_id, $post->id);
        $this->assertEquals($post_id, $post->wp_object()->ID);
        $this->assertEquals($post_title, $post->title());
        $this->assertEquals($post_title, $post->post_title);
        $this->assertEquals(apply_filters('the_content', $post_content), $post->content());
        $this->assertEquals($post_content, $post->post_content);
        $this->assertEquals($post_excerpt, (string)$post->excerpt(['read_more' => false]));
        $this->assertEquals($post_excerpt, $post->post_excerpt);
    }

    public function test_get_method_with_custom_keys()
    {
        $custom_meta_key_value = 'Lorem ipsum sit amet dolor';
        $post_id = $this->factory()->post->create([
            'post_title' => 'Test Post Title',
        ]);

        // Intentionally not using post repository here, tests for that exist in the PostRepositoryTest class
        update_post_meta($post_id, 'custom_meta_key', $custom_meta_key_value);

        $post = Timber::get_post($post_id);
        $this->assertInstanceOf(PostTester::class, $post);
        $this->assertEquals($custom_meta_key_value, $post->custom_meta_key);
    }

    public function test_get_method_override()
    {
        $post_id = $this->factory()->post->create([
            'post_title' => 'Post',
        ]);
        $post = Timber::get_post($post_id);
        $this->assertInstanceOf(PostTester::class, $post);
        $this->assertEquals("Custom Getter: Test Custom Value", $post->test_custom_value);
    }

    /**
     * This test simply checks if the set override is working as expected.
     *
     * Tests for PostBase::__set() exist in the PostRepositoryTest class, as PostRepository->add() is needed to deep store the data.
     */
    public function test_set_method_override()
    {
        $post_id = $this->factory()->post->create([
            'post_title' => 'Test Post',
        ]);
        $post = Timber::get_post($post_id);
        $this->assertInstanceOf(PostTester::class, $post);

        $some_custom_value = 'Testing';
        $post->some_custom_key = $some_custom_value;
        $this->assertEquals("Custom Setter: {$some_custom_value}", $post->some_custom_key);
    }
}