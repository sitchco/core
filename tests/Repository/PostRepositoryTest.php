<?php

namespace Sitchco\Tests;

use Sitchco\Model\Category;
use Sitchco\Repository\PostRepository;
use Sitchco\Repository\RepositoryBase;
use Sitchco\Tests\Support\EventRepository;
use Sitchco\Tests\Support\PostTester;
use Timber\PostCollectionInterface;
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

        $test_term_id = $this->factory()->term->create([
            'name' => 'Test Category',
            'taxonomy' => 'category',
        ]);

        $second_test_term_id = $this->factory()->term->create([
            'name' => 'Second Test Category',
            'taxonomy' => 'category',
        ]);

        $thumbnail_id = $this->factory()->attachment->create_upload_object(
            SITCHCO_CORE_DIR . '/tests/assets/sample-image.jpg'
        );

        $createdPost->wp_object()->post_title = $title;
        $createdPost->custom_number_key = 123;
        $createdPost->addTerm($test_term_id)->addTerm($second_test_term_id)->removeTerm(1);
        $createdPost->thumbnail_id = $thumbnail_id;

        // Test checkBoundModelType()
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Model Class is not an instance of :Sitchco\Tests\Support\EventPost');
        (new EventRepository())->add($createdPost);

        (new PostRepository())->add($createdPost);
        $returnedPost = Timber::get_post($createdPost->ID);
        $this->assertEquals($createdPost->ID, $returnedPost->wp_object()->ID);
        $this->assertEquals($title, $returnedPost->post_title);
        $this->assertEquals(123, $returnedPost->custom_number_key);
        $returnedCategories = $returnedPost->terms(['taxonomy' => 'category']);
        $this->assertInstanceOf(Category::class, $returnedCategories[0]);
        $this->assertEquals(array_column($returnedCategories, 'term_id'), [$second_test_term_id, $test_term_id]);
        $this->assertEquals($thumbnail_id, $returnedPost->thumbnail_id());
    }

    public function test_update_post()
    {
        $title = 'Created Post';
        $post_id = $this->factory()->post->create([
            'post_title' => $title,
            'post_content' => 'This post currently has no meta attached.',
        ]);
        $test_term_id = $this->factory()->term->create([
            'name' => 'Test Category',
            'taxonomy' => 'category',
        ]);
        $second_test_term_id = $this->factory()->term->create([
            'name' => 'Second Test Category',
            'taxonomy' => 'category',
        ]);

        // Set a thumbnail (note: this does not set the thumbnail, instead just attaches to the post)
        $thumbnail_id = $this->factory()->attachment->create_upload_object(
            SITCHCO_CORE_DIR . '/tests/assets/sample-image.jpg',
            $post_id
        );

        $createdPost = Timber::get_post($post_id);
        $this->assertInstanceOf(PostTester::class, $createdPost);
        $modified_title = "Modified: {$title}";

        $createdPost->wp_object()->post_title = $modified_title;
        $createdPost->custom_string_key = 'some string';
        $createdPost->custom_number_key = 123;
        $createdPost->thumbnail_id = $thumbnail_id;
        $createdPost->addTerm($test_term_id)->addTerm($second_test_term_id)->removeTerm(1);

        (new PostRepository())->add($createdPost);

        $returnedPost = Timber::get_post($post_id);
        $this->assertEquals($modified_title, $returnedPost->post_title);
        $this->assertEquals('some string', $returnedPost->custom_string_key);
        $this->assertEquals(123, $returnedPost->custom_number_key);
        $returnedCategories = $returnedPost->terms(['taxonomy' => 'category']);
        $this->assertInstanceOf(Category::class, $returnedCategories[0]);
        $this->assertEquals(array_column($returnedCategories, 'term_id'), [$second_test_term_id, $test_term_id]);

        // Check if the thumbnail was set
        $this->assertEquals($thumbnail_id, $returnedPost->thumbnail_id());
    }

    public function test_find_method()
    {
        global $wp_query, $post;
        $first_post_id = $this->factory()->post->create(['post_title' => 'Post 1']);
        $second_post_id = $this->factory()->post->create(['post_title' => 'Post 2']);

        // Convert to Timber posts
        $post = Timber::get_post($first_post_id);
        $second_post = Timber::get_post($second_post_id);

        // Step 2: Set up a singular query (mocking a single post view)
        $wp_query->is_singular = true;
        $wp_query->queried_object = $post->wp_object;

        // Ensure get_the_ID() works by setting the global post
        $GLOBALS['post'] = $wp_query->queried_object;
        setup_postdata($GLOBALS['post']);
        $found_posts = (new PostRepository())->find([]);

        $this->assertInstanceOf(PostCollectionInterface::class, $found_posts);
        $this->assertCount(1, $found_posts);
        $this->assertEquals($second_post->ID, $found_posts[0]->ID);

        // Clean up global post state
        wp_reset_postdata();
    }

    public function test_find_all_method()
    {
        $first_post_id = $this->factory()->post->create(['post_title' => 'Post 1']);
        $second_post_id = $this->factory()->post->create(['post_title' => 'Post 2']);
        $third_post_id = $this->factory()->post->create(['post_title' => 'Post 3']);
        $found_posts = (new PostRepository())->findAll([]);
        $this->assertInstanceOf(PostCollectionInterface::class, $found_posts);
        $this->assertCount(3, $found_posts);
        $this->assertEquals([$first_post_id, $second_post_id, $third_post_id], array_column($found_posts->to_array(), 'ID'));
    }


}