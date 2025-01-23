<?php

namespace Test\Timber;

use ReflectionClass;
use Timber\Timber;
use Timber\User;
use WPTest\Test\TestCase;

/**
 * class TimberPostTest
 * @package Test\Timber
 */
class TimberPostTest extends TestCase
{
    public function test_title_method()
    {
        $post_id = $this->factory()->post->create([
            'post_title' => 'Mock Post Title',
        ]);
        $post = Timber::get_post($post_id);
        $post->post_title = 'Mock Post Title';
        \add_filter('the_title', function ($title, $post_id) {
            return "Filtered: $title";
        }, 10, 2);
        $this->assertEquals('Filtered: Mock Post Title', $post->title());
        \remove_all_filters('the_title');
    }

    public function test_content_method()
    {
        // Test 1: Test content filtering
        $post_id = $this->factory()->post->create([
            'post_title' => 'Mock Post Title',
            'post_content' => 'Mock Post Content'
        ]);
        $post = Timber::get_post($post_id);
        \add_filter('the_content', function ($content) {
            return "Filtered: $content";
        });
        remove_filter('the_content', 'wpautop');
        $this->assertEquals('Filtered: Mock Post Content', trim($post->content()));
        \remove_all_filters('the_content');

        // Test 2: Test content pagination
        $post->post_content = 'Page 1 content<!--nextpage-->Page 2 content<!--nextpage-->Page 3 content';
        $this->assertEquals('Page 1 content', $post->content(1));
        $this->assertEquals('Page 2 content', $post->content(2));
        $this->assertEquals('Page 3 content', $post->content(3));
        add_filter('the_content', 'wpautop');
    }

    public function test_link_method()
    {
        $post_id = $this->factory()->post->create([
            'post_title' => 'Test Post'
        ]);
        $post = Timber::get_post($post_id);

        // Test 1: When _permalink is already set (via caching or other means)
        $reflection = new ReflectionClass(get_class($post));
        $property = $reflection->getProperty('_permalink');
        $property->setAccessible(true);
        $property->setValue($post, 'https://mock-link.com');
        $this->assertEquals('https://mock-link.com', $post->link());

        // Test 2: When _permalink is not set, it should call get_permalink() and return the permalink
        $property->setValue($post, null);
        $expected_permalink = get_permalink($post_id);
        $this->assertEquals($expected_permalink, $post->link());
    }

    public function test_date_method()
    {
        $post_id = $this->factory()->post->create([
            'post_title' => 'Test Post',
            'post_date' => '2025-01-23 12:00:00', // Set a fixed post date for testing
        ]);
        $post = Timber::get_post($post_id);

        // Test 1: When no date format is provided, it should use the default WordPress date format
        $default_date_format = get_option('date_format');
        $expected_default_date = wp_date($default_date_format, $post->timestamp());
        $this->assertEquals($expected_default_date, $post->date());

        // Test 2: When a custom date format is provided, it should use the custom format
        $custom_format = 'Y-m-d H:i:s';
        $expected_custom_date = wp_date($custom_format, $post->timestamp());
        $this->assertEquals($expected_custom_date, $post->date($custom_format));

        // Test 3: Ensure the get_the_date filter is applied to the date
        \add_filter('get_the_date', function ($date) {
            return "Modified: $date";
        });
        $modified_date = $post->date();
        $this->assertEquals("Modified: $expected_default_date", $modified_date);
        \remove_filter('get_the_date', 'my_custom_get_the_date_filter');
    }

    public function test_modified_date()
    {
        \update_option('date_format', 'F j, Y');
        $post_id = $this->factory()->post->create([
            'post_title' => 'Modified Date Test Post',
            'post_content' => 'This is a test for the modified_date method.',
            'post_date' => '2023-01-01 10:00:00',
        ]);
        global $wpdb;
        $wpdb->update(
            $wpdb->posts,
            ['post_modified' => '2023-01-05 15:30:00', 'post_modified_gmt' => '2023-01-05 15:30:00'],
            ['ID' => $post_id]
        );
        \clean_post_cache($post_id);
        $post = Timber::get_post($post_id);

        // Test 1: Default date format.
        $expected_date = 'January 5, 2023';
        $modified_date = $post->modified_date();
        $this->assertEquals($expected_date, $modified_date);

        // Test 2: Custom date format.
        $custom_format = 'Y-m-d H:i:s';
        $expected_custom_date = '2023-01-05 15:30:00';
        $custom_modified_date = $post->modified_date($custom_format);
        $this->assertEquals($expected_custom_date, $custom_modified_date);

        // Test 3: Ensure the `get_the_modified_date` filter is applied.
        \add_filter('get_the_modified_date', function ($date, $format, $post) {
            return "Filtered: $date";
        }, 10, 3);

        $filtered_date = $post->modified_date();
        $this->assertEquals("Filtered: January 5, 2023", $filtered_date);

        // Remove the filter to avoid side effects in other tests.
        \remove_filter('get_the_modified_date', '__return_false');
    }

    public function test_type_method()
    {
        $post_type = 'custom_post_type';
        register_post_type($post_type, ['label' => 'Custom Post Type']);

        $post_id = $this->factory()->post->create([
            'post_title' => 'Test Post',
            'post_type' => $post_type,
        ]);
        $post = Timber::get_post($post_id);

        // Test 1: Verify the returned type is an instance of PostType
        $type = $post->type();
        $this->assertInstanceOf(\Timber\PostType::class, $type);

        // Test 2: Verify the post type slug is correct
        $this->assertEquals($post_type, (string)$type);

        // Test 3: Verify the same instance is returned on subsequent calls
        $type_second_call = $post->type();
        $this->assertSame($type, $type_second_call);
        unregister_post_type($post_type);
    }

    public function test_wp_object_method()
    {
        $post_id = $this->factory()->post->create([
            'post_title' => 'Test Post',
            'post_content' => 'This is test post content.',
        ]);
        $post = Timber::get_post($post_id);

        // Test 1: Verify that wp_object() returns a WP_Post object
        $wp_post = $post->wp_object();
        $this->assertInstanceOf(\WP_Post::class, $wp_post);

        // Test 2: Verify that the ID of the WP_Post matches the original post ID
        $this->assertEquals($post_id, $wp_post->ID);

        // Test 3: Verify that the title of the WP_Post matches the original post title
        $this->assertEquals('Test Post', $wp_post->post_title);

        // Test 4: Verify that the content of the WP_Post matches the original post content
        $this->assertEquals('This is test post content.', $wp_post->post_content);
    }

    public function test_meta_method()
    {
        $post_id = $this->factory()->post->create([
            'post_title' => 'Meta Test Post',
            'post_content' => 'This post is for testing meta fields.',
        ]);
        \update_post_meta($post_id, 'custom_field_1', 'Value 1');
        \update_post_meta($post_id, 'custom_field_2', 'Value 2');
        $post = Timber::get_post($post_id);

        // Test 1: Get a single meta value
        $meta_value_1 = $post->meta('custom_field_1');
        $this->assertEquals('Value 1', $meta_value_1);

        // Test 2: Get another single meta value
        $meta_value_2 = $post->meta('custom_field_2');
        $this->assertEquals('Value 2', $meta_value_2);

        // Test 3: Get all meta values
        $all_meta = $post->meta();
        $this->assertIsArray($all_meta);
        $this->assertArrayHasKey('custom_field_1', $all_meta);
        $this->assertArrayHasKey('custom_field_2', $all_meta);
        $this->assertEquals('Value 1', $all_meta['custom_field_1']);
        $this->assertEquals('Value 2', $all_meta['custom_field_2']);

        // Test 4: Get a meta field that doesn’t exist (should return an empty string)
        $nonexistent_meta = $post->meta('nonexistent_field');
        $this->assertSame('', $nonexistent_meta);
    }

    public function test_thumbnail_methods()
    {
        $post_id = $this->factory()->post->create([
            'post_title' => 'Thumbnail Test Post',
            'post_content' => 'Testing thumbnail methods.',
        ]);

        // Create an attachment (e.g., image) and set it as the post's featured image.
        $attachment_id = $this->factory()->attachment->create_upload_object(
            SITCHCO_CORE_DIR . '/tests/assets/sample-image.jpg',
            $post_id
        );
        \update_post_meta($post_id, '_thumbnail_id', $attachment_id);
        $post = Timber::get_post($post_id);

        // Test 1: Verify the thumbnail ID.
        $this->assertEquals($attachment_id, $post->thumbnail_id(), 'The thumbnail ID should match the attachment ID.');

        // Test 2: Verify the thumbnail object.
        $thumbnail = $post->thumbnail();
        $this->assertInstanceOf(\Timber\Image::class, $thumbnail, 'The thumbnail method should return a Timber\Image object.');
        $this->assertEquals($attachment_id, $thumbnail->ID, 'The ID of the thumbnail object should match the attachment ID.');

        // Test 3: Ensure `thumbnail()` returns null if no thumbnail is set.
        $no_thumbnail_post_id = $this->factory()->post->create([
            'post_title' => 'No Thumbnail Test Post',
            'post_content' => 'This post has no thumbnail.',
        ]);

        $no_thumbnail_post = Timber::get_post($no_thumbnail_post_id);
        $this->assertNull($no_thumbnail_post->thumbnail(), 'The thumbnail method should return null if no thumbnail is set.');
    }

    public function test_excerpt_method()
    {
        // Test 1: Post with content and a manual excerpt.
        $post_id = $this->factory()->post->create([
            'post_title' => 'Test Post',
            'post_content' => 'This is a test post content. It is designed to check the excerpt functionality.',
            'post_excerpt' => 'This is a manual excerpt.',
        ]);
        $post = Timber::get_post($post_id);

        $default_excerpt = $post->excerpt();
        $this->assertEquals('This is a manual excerpt.', substr((string)$default_excerpt, 0, strlen('This is a manual excerpt.')), 'Default excerpt should use the manual excerpt.');

        // Check if "Read More" link is included in the excerpt
        $this->assertStringContainsString('<a href="http://example.org/?p=' . $post_id . '" class="read-more">Read More</a>', (string)$default_excerpt, 'Excerpt should include "Read More" link.');

        // Test 2: Post without a manual excerpt (should generate excerpt from content).
        $no_excerpt_post_id = $this->factory()->post->create([
            'post_title' => 'No Excerpt Post',
            'post_content' => 'This is a test post content. It is designed to check the excerpt functionality.',
            'post_excerpt' => '' // if left empty, auto string generated "Post excerpt 0000028"
        ]);
        $no_excerpt_post = Timber::get_post($no_excerpt_post_id);
        $content_excerpt = $no_excerpt_post->excerpt(['words' => 10]);
        $excerpt_string = (string)$content_excerpt;
        $this->assertStringContainsString('This is a test post content', $excerpt_string, 'Excerpt should be generated from content when no manual excerpt is set.');
        $this->assertStringContainsString('<a href="http://example.org/?p=' . $no_excerpt_post_id . '" class="read-more">Read More</a>', $excerpt_string, 'Excerpt should include "Read More" link when limited to a specified number of words.');

        // Test 3: Limit excerpt to a specific number of words (manual excerpt).
        $word_limited_excerpt = $post->excerpt(['words' => 5, 'read_more' => false]);
        $this->assertEquals('This is a manual excerpt.', (string)$word_limited_excerpt, 'Excerpt should be limited to the specified number of words.');

        // Test 4: Limit excerpt to a specific number of characters (content-based excerpt).
        $char_limited_excerpt = $no_excerpt_post->excerpt(['chars' => 30, 'read_more' => false]);
        $this->assertEquals('This is a test post content. I&hellip;', (string)$char_limited_excerpt, 'Excerpt should be limited to the specified number of characters.');

        // Test 5: Custom end string for the excerpt (content-based excerpt).
        $custom_end_excerpt = $no_excerpt_post->excerpt(['chars' => 30, 'end' => '...', 'read_more' => false]);
        $this->assertEquals('This is a test post content. I...', (string)$custom_end_excerpt, 'Excerpt should use the custom end string.');

        // Test 6: Strip HTML tags from the excerpt.
        $html_post_id = $this->factory()->post->create([
            'post_title' => 'HTML Post',
            'post_content' => '<p>This is <strong>HTML</strong> content.</p>',
            'post_excerpt' => '' // if left empty, auto string generated "Post excerpt 0000028"
        ]);
        $html_post = Timber::get_post($html_post_id);
        $stripped_excerpt = $html_post->excerpt(['strip' => true, 'read_more' => false]);
        $this->assertEquals('This is HTML content.', (string)$stripped_excerpt, 'Excerpt should strip HTML tags when the "strip" option is set to true.');

        // Test 7: Append a custom "Read More" text to the excerpt.
        $read_more_excerpt = $html_post->excerpt(['read_more' => 'Continue reading']);
        $this->assertStringContainsString('Continue reading', (string)$read_more_excerpt, 'Excerpt should include the custom "Read More" text.');
    }

    public function test_author_method()
    {
        $user_id = $this->factory()->user->create([
            'user_login' => 'testuser',
            'user_email' => 'testuser@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
        ]);
        // Test 1: Test a post that has an author
        $post_id = $this->factory()->post->create([
            'post_title'   => 'Test Post',
            'post_content' => 'This is a test post content.',
            'post_author'  => $user_id,
        ]);
        $post = Timber::get_post($post_id);
        $author = $post->author();
        $this->assertInstanceOf(User::class, $author);
        $wp_user = get_user_by('id', $user_id);
        $this->assertEquals($wp_user->ID, $author->ID);

        // Test 2: Test with a post that has no author
        $post_id = $this->factory()->post->create([
            'post_title'   => 'Test Post',
            'post_content' => 'This is a test post content.',
            'post_author'  => '',
        ]);
        $post_no_author = Timber::get_post($post_id);
        $this->assertNull($post_no_author->author());
    }

    public function test_terms_method()
    {
        $category1 = $this->factory()->term->create([
            'taxonomy' => 'category',
            'name'     => 'Cheese',
        ]);
        $category2 = $this->factory()->term->create([
            'taxonomy' => 'category',
            'name'     => 'Food',
        ]);
        $post_id = $this->factory()->post->create([
            'post_title'   => 'Test Post with Terms',
            'post_content' => 'This post has multiple categories.',
            'post_category' => [$category1, $category2],
        ]);
        $post = Timber::get_post($post_id);

        // Test the terms() method with a single taxonomy argument (category)
        $terms = $post->terms('category');
        $this->assertIsArray($terms);
        $this->assertCount(2, $terms);
        $this->assertEquals('Cheese', $terms[0]->name);
        $this->assertEquals('Food', $terms[1]->name);

        // Test the terms() method with multiple taxonomy arguments (category, post_tag)
        $terms_multiple = $post->terms(['category', 'post_tag']);
        $this->assertIsArray($terms_multiple);

        // Verify that category terms are included
        $category_terms = array_filter($terms_multiple, function($term) {
            return $term->taxonomy === 'category';
        });
        $this->assertCount(2, $category_terms);

        // Verify that no post_tag terms are included (since they were not assigned)
        $post_tag_terms = array_filter($terms_multiple, function($term) {
            return $term->taxonomy === 'post_tag';
        });
        $this->assertCount(0, $post_tag_terms);

        // Test with query arguments for ordering terms
        $terms_ordered = $post->terms([
            'taxonomy' => 'category',
            'orderby'  => 'name',
            'order'    => 'ASC'
        ]);
        $this->assertIsArray($terms_ordered);
        $this->assertEquals('Cheese', $terms_ordered[0]->name);
        $this->assertEquals('Food', $terms_ordered[1]->name);

        // Test merge option (false means separate arrays for each taxonomy)
        $terms_separate = $post->terms([
            'taxonomy' => 'category',
            'orderby'  => 'name',
            'order'    => 'ASC'
        ], ['merge' => false]);
        $this->assertIsArray($terms_separate);
        $this->assertArrayHasKey('category', $terms_separate);
        $this->assertCount(2, $terms_separate['category']);
    }

    public function test_terms_method_no_assigned_terms()
    {
        $post_id = $this->factory()->post->create([
            'post_title'   => 'Post without terms',
            'post_content' => 'This post has no terms.',
        ]);
        $post = Timber::get_post($post_id);
        $terms = $post->terms('category');
        $this->assertNotEmpty($terms);
        $uncategorized = array_filter($terms, function($term) {
            return $term->name === 'Uncategorized';
        });
        $this->assertCount(1, $uncategorized);
        $this->assertEquals('Uncategorized', $uncategorized[0]->name);
    }









//
//    /**
//     * Test saving a post.
//     *
//     * Note: This depends on how the saving functionality is implemented in Timber\Post.
//     */
//    public function test_save_post()
//    {
//        $post = new Post();
//        $post->title = 'Test Post';
//        $post->content = 'Test content.';
//        // Assuming save method is available.
//        $result = $post->save();
//        $this->assertTrue($result);
//    }
//
//    /**
//     * Test handling of post metadata.
//     */
//    public function test_post_metadata()
//    {
//        $post = new Post();
//        $post->ID = 1;
//        $post->set_meta('key', 'value');
//        $this->assertEquals('value', $post->get_meta('key'));
//    }
//
//    /**
//     * Test adding a term to a post.
//     */
//    public function test_add_term_to_post()
//    {
//        $post = new Post();
//        $post->ID = 1;
//        $post->add_term('category', 10); // Assuming term 10 is a valid category ID.
//        $this->assertTrue($post->has_term('category', 10));
//    }
//
//    /**
//     * Test removing a term from a post.
//     */
//    public function test_remove_term_from_post()
//    {
//        $post = new Post();
//        $post->ID = 1;
//        $post->remove_term('category', 10);
//        $this->assertFalse($post->has_term('category', 10));
//    }
//
//    /**
//     * Test post taxonomy query.
//     */
//    public function test_post_taxonomy_query()
//    {
//        $post = new Post();
//        $post->ID = 1;
//        $post->set_taxonomy('category', [10, 20]); // Example taxonomy setup.
//        $terms = $post->get_taxonomy('category');
//        $this->assertContains(10, $terms);
//        $this->assertContains(20, $terms);
//    }
//
//    /**
//     * Test checking for post status.
//     */
//    public function test_check_post_status()
//    {
//        $post = new Post();
//        $post->post_status = 'publish';
//        $this->assertTrue($post->is_published());
//    }
//
//    /**
//     * Test if the post is a draft.
//     */
//    public function test_is_draft()
//    {
//        $post = new Post();
//        $post->post_status = 'draft';
//        $this->assertTrue($post->is_draft());
//    }
//
//    /**
//     * Test handling the post creation process.
//     */
//    public function test_create_post()
//    {
//        $post = Post::create([
//            'title' => 'New Post',
//            'content' => 'This is new post content.',
//            'status' => 'publish',
//        ]);
//        $this->assertInstanceOf(Post::class, $post);
//        $this->assertEquals('New Post', $post->get_title());
//        $this->assertEquals('This is new post content.', $post->get_content());
//    }
}
