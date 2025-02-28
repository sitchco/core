<?php

namespace Sitchco\Tests;

use InvalidArgumentException;
use Sitchco\Collection\Collection;
use Sitchco\Model\Category;
use Sitchco\Repository\PostRepository;
use Sitchco\Tests\Support\EventPostTester;
use Sitchco\Tests\Support\EventRepositoryTester;
use Sitchco\Tests\Support\PostTester;
use Sitchco\Tests\Support\TestCase;
use Timber\PostCollectionInterface;
use Timber\Timber;

/**
 * class RepositoryBaseTest
 * @package Sitchco\Tests
 */
class RepositoryBaseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        add_filter('timber/post/classmap', function($classmap) {
            $classmap['post'] = PostTester::class;
            $classmap['event'] = EventPostTester::class;
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
            SITCHCO_CORE_FIXTURES_DIR . '/sample-image.jpg'
        );

        $createdPost->wp_object()->post_title = $title;
        $createdPost->custom_number_key = 123;
        $createdPost->addTerm($test_term_id)->addTerm($second_test_term_id)->removeTerm(1);
        $createdPost->thumbnail_id = $thumbnail_id;

        // Test checkBoundModelType()
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Model Class is not an instance of :Sitchco\Tests\Support\EventPost');
        $this->container->get(EventRepositoryTester::class)->add($createdPost);

        $this->container->get(PostRepository::class)->add($createdPost);
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
        $third_test_term_id = $this->factory()->term->create([
            'name' => 'Third Test Category',
            'taxonomy' => 'category',
        ]);

        // Set a thumbnail (note: this does not set the thumbnail, instead just attaches to the post)
        $thumbnail_id = $this->factory()->attachment->create_upload_object(
            SITCHCO_CORE_FIXTURES_DIR . '/sample-image.jpg',
            $post_id
        );
        wp_set_post_terms($post_id, [$third_test_term_id], 'category');

        $createdPost = Timber::get_post($post_id);
        $this->assertInstanceOf(PostTester::class, $createdPost);
        $modified_title = "Modified: {$title}";

        $createdPost->wp_object()->post_title = $modified_title;
        $createdPost->custom_string_key = 'some string';
        $createdPost->custom_number_key = 123;
        $createdPost->thumbnail_id = $thumbnail_id;
        $createdPost->setTerms([$test_term_id, $second_test_term_id], 'category');

        $this->container->get(PostRepository::class)->add($createdPost);

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

    public function test_remove_post()
    {
        $repository = $this->container->get(PostRepository::class);

        // Scenario 1: Deleting an existing post
        $post_id = $this->factory()->post->create(['post_title' => 'Post to be deleted']);
        $event_post_id = $this->factory()->post->create(['post_title' => 'Post to be deleted', 'post_type' => 'event']);
        $post = Timber::get_post($post_id);

        // Ensure post exists before deletion
        $this->assertNotNull($post);
        $this->assertEquals($post_id, $post->ID);

        // Delete the post and verify
        $result = $repository->remove($post);
        $this->assertTrue($result);
        $this->assertEquals('trash', get_post_status($post_id));

        // Scenario 2: Trying to delete a post of a different post_type
        $event_post = Timber::get_post($event_post_id);

        // Attempt to delete and check response
        $this->expectException(InvalidArgumentException::class);
        $repository->remove($event_post);
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
        $found_posts = $this->container->get(PostRepository::class)->find();

        $this->assertInstanceOf(Collection::class, $found_posts);
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
        $found_posts = $this->container->get(PostRepository::class)->findAll();
        $this->assertInstanceOf(PostCollectionInterface::class, $found_posts);
        $this->assertCount(3, $found_posts);
        $this->assertEquals([$first_post_id, $second_post_id, $third_post_id], array_column($found_posts->to_array(), 'ID'));
    }

    public function test_find_by_id_method()
    {
        // Step 1: Create a test post
        $post_id = $this->factory()->post->create(['post_title' => 'Test Post']);

        // Step 2: Call findById() with a valid ID
        $found_post = $this->container->get(PostRepository::class)->findById($post_id);

        // Step 3: Assert the post is correctly retrieved
        $this->assertInstanceOf(PostTester::class, $found_post);
        $this->assertEquals($post_id, $found_post->ID);
        $this->assertEquals('Test Post', $found_post->post_title);

        // Step 4: Call findById() with an invalid ID
        $invalid_post = $this->container->get(PostRepository::class)->findById(999999);
        $this->assertNull($invalid_post);

        // Step 5: Call findById() with null
        $null_post = $this->container->get(PostRepository::class)->findById(null);
        $this->assertNull($null_post);

        // Step 6: Call findById() with an empty string
        $empty_post = $this->container->get(PostRepository::class)->findById('');
        $this->assertNull($empty_post);
    }

    public function test_find_one_method()
    {
        $first_post_id = $this->factory()->post->create(['post_title' => 'First Post', 'post_status' => 'pending']);
        $this->factory()->post->create(['post_title' => 'Second Post', 'post_status' => 'draft']);
        $this->factory()->post->create(['post_title' => 'Third Post', 'post_status' => 'draft']);

        // Step 2: Use findOne() to get the first post by title
        $repository = $this->container->get(PostRepository::class);
        $found_post = $repository->findOne(['post_status' => 'pending']);

        // Step 3: Assert the retrieved post is the first one
        $this->assertInstanceOf(PostTester::class, $found_post);
        $this->assertEquals($first_post_id, $found_post->ID);
        $this->assertEquals('First Post', $found_post->post_title);

        // Step 4: Test with a non-existent title
        $non_existent_post = $repository->findOne(['title' => 'Non-existent Post']);
        $this->assertNull($non_existent_post);
    }

    public function test_find_one_by_slug_method()
    {
        $first_post_id = $this->factory()->post->create([
            'post_title' => 'First Post',
            'post_name' => 'first-post',
        ]);

        $this->factory()->post->create([
            'post_title' => 'Second Post',
            'post_name' => 'second-post',
        ]);

        // Step 2: Use findOneBySlug() to retrieve the first post
        $repository = $this->container->get(PostRepository::class);
        $found_post = $repository->findOneBySlug('first-post');

        // Step 3: Assert that the retrieved post is correct
        $this->assertInstanceOf(PostTester::class, $found_post);
        $this->assertEquals($first_post_id, $found_post->ID);
        $this->assertEquals('first-post', $found_post->post_name);

        // Step 4: Test with a non-existent slug
        $non_existent_post = $repository->findOneBySlug('non-existent-post');
        $this->assertNull($non_existent_post);
    }

    public function test_find_one_by_author_method()
    {
        $repository = $this->container->get(PostRepository::class);

        // Scenario 1: Finding a post by author ID
        $author_id = $this->factory()->user->create(['user_login' => 'author1']);
        $post_id = $this->factory()->post->create([
            'post_title' => 'Post by Author 1',
            'post_author' => $author_id,
        ]);

        $author = get_user_by('id', $author_id);
        $post = Timber::get_post($post_id);

        // Ensure post exists before test
        $this->assertNotNull($post);
        $this->assertEquals($author_id, $post->post_author);

        // Call findOneByAuthor and ensure the post is returned
        $result = $repository->findOneByAuthor($author);
        $this->assertNotNull($result);
        $this->assertEquals($post_id, $result->ID);

        // Scenario 2: Finding a post by author object
        $resultByObject = $repository->findOneByAuthor($author);
        $this->assertNotNull($resultByObject);
        $this->assertEquals($post_id, $resultByObject->ID);

        // Scenario 3: Trying to find a post by non-existent author
        $non_existent_author = get_user_by('id', 999999);
        $resultNonExistent = $repository->findOneByAuthor($non_existent_author);
        $this->assertNull($resultNonExistent);
    }

    public function test_find_all_by_author_method()
    {
        $repository = $this->container->get(PostRepository::class);

        // Scenario 1: Finding posts by author ID
        $author_id = $this->factory()->user->create(['user_login' => 'author1']);

        // Create posts by the same author
        $post_id_1 = $this->factory()->post->create([
            'post_title' => 'Post 1 by Author 1',
            'post_author' => $author_id,
        ]);
        $post_id_2 = $this->factory()->post->create([
            'post_title' => 'Post 2 by Author 1',
            'post_author' => $author_id,
        ]);

        $author = get_user_by('id', $author_id);

        // Call findAllByAuthor with author ID and assert the posts are returned
        $result = $repository->findAllByAuthor($author);
        $this->assertCount(2, $result);
        $this->assertEquals($post_id_1, $result[0]->ID);
        $this->assertEquals($post_id_2, $result[1]->ID);

        // Scenario 2: Finding posts by author object
        $resultByObject = $repository->findAllByAuthor($author);
        $this->assertCount(2, $resultByObject);
        $this->assertEquals($post_id_1, $resultByObject[0]->ID);
        $this->assertEquals($post_id_2, $resultByObject[1]->ID);

        // Scenario 3: Trying to find posts by non-existent author
        $non_existent_author = get_user_by('id', 999999);
        $resultNonExistent = $repository->findAllByAuthor($non_existent_author);
        $this->assertTrue($resultNonExistent->isEmpty());
    }

    public function test_find_all_drafts_method()
    {
        $repository = $this->container->get(PostRepository::class);

        // Scenario 1: Creating drafts
        $draft_post_id_1 = $this->factory()->post->create([
            'post_title' => 'Draft Post 1',
            'post_status' => 'draft',
        ]);
        $draft_post_id_2 = $this->factory()->post->create([
            'post_title' => 'Draft Post 2',
            'post_status' => 'draft',
        ]);

        // Create a published post to ensure only drafts are returned
        $published_post_id = $this->factory()->post->create([
            'post_title' => 'Published Post',
            'post_status' => 'publish',
        ]);

        // Call findAllDrafts and assert the drafts are returned
        $result = $repository->findAllDrafts();
        $this->assertCount(2, $result);
        $this->assertEquals($draft_post_id_2, $result[0]->ID);
        $this->assertEquals($draft_post_id_1, $result[1]->ID);

        // Ensure published post is not in the result
        $this->assertNotContains($published_post_id, array_column($result->to_array(), 'ID'));
    }

    public function test_find_with_ids_method()
    {
        $repository = $this->container->get(PostRepository::class);

        // Scenario 1: Creating posts
        $post_ids = [
            $this->factory()->post->create(['post_title' => 'Post 1']),
            $this->factory()->post->create(['post_title' => 'Post 2']),
            $this->factory()->post->create(['post_title' => 'Post 3']),
        ];

        // Create some posts that are not in the specified list
        $other_post_id = $this->factory()->post->create(['post_title' => 'Post 4']);

        // Scenario 2: Testing findWithIds method with post_ids
        $result = $repository->findWithIds($post_ids);

        // Ensure the result is an instance of PostCollectionInterface
        $this->assertInstanceOf(PostCollectionInterface::class, $result);

        // Ensure the result contains the exact posts from the post_ids
        $result_ids = array_map(function($post) {
            return $post->ID;
        }, $result->to_array());

        foreach ($post_ids as $post_id) {
            $this->assertContains($post_id, $result_ids);
        }

        // Ensure other posts not in post_ids are not returned
        $this->assertNotContains($other_post_id, $result_ids);

        // Scenario 3: Test with an empty post_ids array (edge case)
        $result_empty = $repository->findWithIds([]);
        $this->assertTrue($result_empty->isEmpty());
    }

    public function test_find_with_term_ids_method()
    {
        $repository = $this->container->get(PostRepository::class);

        // Scenario 1: Creating terms and posts
        $category_1 = $this->factory()->term->create(['name' => 'Category 1', 'taxonomy' => 'category']);
        $category_2 = $this->factory()->term->create(['name' => 'Category 2', 'taxonomy' => 'category']);
        $category_3 = $this->factory()->term->create(['name' => 'Category 3', 'taxonomy' => 'category']);

        // Create posts and assign terms
        $post_1_id = $this->factory()->post->create(['post_title' => 'Post 1']);
        $post_2_id = $this->factory()->post->create(['post_title' => 'Post 2']);
        $post_3_id = $this->factory()->post->create(['post_title' => 'Post 3']);

        wp_set_post_terms($post_1_id, [$category_1], 'category');
        wp_set_post_terms($post_2_id, [$category_1, $category_2], 'category');
        wp_set_post_terms($post_3_id, [$category_2, $category_3], 'category');

        // Convert to Timber posts
        $term_ids = [$category_1, $category_2];
        $posts = Timber::get_posts([
            'tax_query' => [
                [
                    'taxonomy' => 'category',
                    'terms' => $term_ids,
                    'field' => 'term_id',
                    'compare' => 'IN'
                ]
            ]
        ]);

        // Ensure posts exist before the test
        $this->assertCount(3, $posts);  // Ensure there are 3 posts for testing

        // Scenario 2: Testing findWithTermIds method with term_ids
        $result = $repository->findWithTermIds($term_ids);

        // Ensure the result is an instance of PostCollectionInterface
        $this->assertInstanceOf(PostCollectionInterface::class, $result);

        // Ensure the result contains posts that belong to the specified term IDs
        $result_ids = array_map(function($post) {
            return $post->ID;
        }, $result->to_array());

        // Assert posts belong to category 1 or category 2
        $this->assertContains($post_1_id, $result_ids);
        $this->assertContains($post_2_id, $result_ids);
        $this->assertContains($post_3_id, $result_ids);

        // Scenario 3: Test with excluded post IDs
        $excluded_post_ids = [$post_2_id];
        $result_with_exclusions = $repository->findWithTermIds($term_ids, 'category', 10, $excluded_post_ids);

        // Ensure post 2 is excluded from the result
        $result_with_exclusions_ids = array_map(function($post) {
            return $post->ID;
        }, $result_with_exclusions->to_array());

        $this->assertNotContains($post_2_id, $result_with_exclusions_ids);

        // Scenario 4: Test with an empty term_ids array (edge case)
        $result_empty = $repository->findWithTermIds([]);
        $this->assertTrue($result_empty->isEmpty());
    }

}