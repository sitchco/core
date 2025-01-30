<?php

namespace Sitchco\Tests;

use Sitchco\Model\Category;
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

//        $test_term_id = $this->factory()->term->create([
//            'name' => 'Test Category',
//            'taxonomy' => 'category',
//        ]);
//
//        $second_test_term_id = $this->factory()->term->create([
//            'name' => 'Second Test Category',
//            'taxonomy' => 'category',
//        ]);

        $createdPost->wp_object()->post_title = $title;
//        $createdTerms = [$test_term_id, $second_test_term_id];
//        $createdPost->categories = $createdTerms;
        (new PostRepository())->add($createdPost);

        $returnedPost = Timber::get_post($createdPost->ID);
        $this->assertEquals($createdPost->ID, $returnedPost->wp_object()->ID);
        $this->assertEquals($title, $returnedPost->post_title);
        $returnedCategories = $returnedPost->terms(['taxonomy' => 'category']);
        $this->assertInstanceOf(Category::class, $returnedCategories[0]);
//        $this->assertEquals(array_column($returnedCategories, 'term_id'), array_reverse($createdTerms));
    }

    public function test_update_post()
    {
        $title = 'Test Post (Repository Add)';
        $post_id = $this->factory()->post->create([
            'post_title' => $title,
            'post_content' => 'This post currently has no meta attached.',
        ]);
//        $test_term_id = $this->factory()->term->create([
//            'name' => 'Test Category',
//            'taxonomy' => 'category',
//        ]);
//        $second_test_term_id = $this->factory()->term->create([
//            'name' => 'Second Test Category',
//            'taxonomy' => 'category',
//        ]);
//        $createdTerms = [$test_term_id, $second_test_term_id];
//        // TODO: create some sort of abstraction for this?
//        wp_set_object_terms($post_id, $createdTerms, 'category');
        $createdPost = Timber::get_post($post_id);
        $this->assertInstanceOf(PostTester::class, $createdPost);
        $modified_title = "Modified: {$title}";

        // Set a thumbnail (note: this does not set the thumbnail, instead just attaches to the post)
        $thumbnail_id = $this->factory()->attachment->create_upload_object(
            SITCHCO_CORE_DIR . '/tests/assets/sample-image.jpg',
            $post_id
        );

        // update native and custom keys
        $createdPost->wp_object()->post_title = $modified_title;
        $createdPost->custom_string_key = 'some string';
        $createdPost->custom_number_key = 123;
        $createdPost->thumbnail_id = $thumbnail_id;
//        $third_test_term_id = $this->factory()->term->create([
//            'name' => 'Third Test Category',
//            'taxonomy' => 'category',
//        ]);
//        $updatedTerms = [$second_test_term_id, $third_test_term_id];
//        $createdPost->categories = $updatedTerms;

        (new PostRepository())->add($createdPost);

        // re-fetch the data
        $returnedPost = Timber::get_post($post_id);
        $this->assertEquals($modified_title, $returnedPost->post_title);
        $this->assertEquals('some string', $returnedPost->custom_string_key);
        $this->assertEquals(123, $returnedPost->custom_number_key);
//        $returnedCategories = $returnedPost->terms(['taxonomy' => 'category']);
//        $this->assertInstanceOf(Category::class, $returnedCategories[0]);
//        $this->assertEquals(array_column($returnedCategories, 'term_id'), $updatedTerms);

        // Check if the thumbnail was set
        $this->assertEquals($thumbnail_id, $returnedPost->thumbnail_id(), 'The post thumbnail was not set correctly.');
    }
}