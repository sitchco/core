<?php

namespace Test\Timber;

use ReflectionClass;
use Timber\Timber;
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
        // Set up a real WordPress post in the test environment
        $post_id = $this->factory()->post->create([
            'post_title'   => 'Test Post'
        ]);

        // Retrieve the post using Timber::get_post()
        $post = Timber::get_post($post_id);

        // Test 1: When _permalink is already set (via caching or other means)
        // Use reflection to simulate the _permalink being set.
        $reflection = new ReflectionClass(get_class($post));
        $property = $reflection->getProperty('_permalink');
        $property->setAccessible(true);  // Make the property accessible
        $property->setValue($post, 'https://mock-link.com');  // Set the cached permalink

        // Assert that the link method returns the cached permalink
        $this->assertEquals('https://mock-link.com', $post->link());

        // Test 2: When _permalink is not set, it should call get_permalink() and return the permalink
        // Reset the _permalink value using reflection again
        $property->setValue($post, null);  // Simulate a scenario where _permalink is not set

        // Assert that the link method fetches the permalink from WordPress
        $expected_permalink = get_permalink($post_id);
        $this->assertEquals($expected_permalink, $post->link());
    }

//    public function test_content_method()
//    {
//        $mockPost = $this->getMockBuilder(Post::class)
//            ->disableOriginalConstructor()
//            ->onlyMethods([])
//            ->getMock();
//        $mockPost->post_content = 'Mock Post Content';
//
//        // Add a filter to modify content (if any)
//        \add_filter('the_content', function ($content) {
//            return "Filtered: $content";
//        });
//
//        // Temporarily disable wpautop filter
//        remove_filter('the_content', 'wpautop');
//
//        // Now assert without wpautop wrapping
//        $this->assertEquals('Filtered: Mock Post Content', $mockPost->content());
//
//        // Add wpautop back if needed
//        add_filter('the_content', 'wpautop');
//
//        // Remove all filters after the test
//        \remove_all_filters('the_content');
//    }
//
//    public function test_content_pagination()
//    {
//        $mockPost = $this->getMockBuilder(Post::class)
//            ->disableOriginalConstructor()
//            ->onlyMethods([])
//            ->getMock();
//        $mockPost->post_content = 'Page 1 content <!--nextpage--> Page 2 content <!--nextpage--> Page 3 content';
//
//        // Test getting the first page
//        $this->assertEquals('Page 1 content', $mockPost->content(1));
//
//        // Test getting the second page
//        $this->assertEquals('Page 2 content', $mockPost->content(2));
//
//        // Test getting the third page
//        $this->assertEquals('Page 3 content', $mockPost->content(3));
//    }

    //    public function test_get_post()
//    {
//        // Create a stub for the Timber\Post class
//        $mockPost = $this->createStub(Post::class);
//
//        // Set property values directly on the stub
//        $mockPost->ID = 123;
//        $mockPost->post_title = 'Mock Post Title';
//        $mockPost->content = 'This is a test post content.';
//
//        // Create a partial mock for the test class
//        $mockTest = $this->getMockBuilder(self::class)
//            ->onlyMethods(['get_post'])
//            ->getMock();
//
//        // Mock the get_post method
//        $mockTest->method('get_post')->with(123)->willReturn($mockPost);
//
//        // Call the mocked wrapper method
//        $post = $mockTest->get_post(123);
//
//        // Assertions
//        $this->assertInstanceOf(Post::class, $post);
//        $this->assertEquals('Mock Post Title', $post->post_title);
//        $this->assertEquals(123, $post->ID);
//    }

//
//    /**
//     * Test getting the post content.
//     */
//    public function test_get_post_content()
//    {
//        $post = new Post();
//        $post->content = 'This is a test post content.';
//        $this->assertEquals('This is a test post content.', $post->get_content());
//    }
//
//    /**
//     * Test getting post permalink.
//     */
//    public function test_get_post_permalink()
//    {
//        $post = new Post();
//        $post->ID = 1;
//        $post->slug = 'test-post';
//        $post->post_type = 'post';
//        $expected_permalink = 'http://example.com/test-post';
//        $this->assertEquals($expected_permalink, $post->get_permalink());
//    }
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
