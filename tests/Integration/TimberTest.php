<?php

namespace Test\Timber;

use Sitchco\Model\Post;
use Timber\Timber;
use WPTest\Test\TestCase;

/**
 * class TimberPostTest
 * @package Test\Timber
 */
class TimberTest extends TestCase
{
    public function test_get_post()
    {
        $post_id = $this->factory()->post->create([
            'post_title' => 'Mock Post Title',
        ]);
        $Post = Timber::get_post($post_id);

        // Test 1: Test that custom mapping is working
        $this->assertInstanceOf(Post::class, $Post);

        // Test 2: Test an invalid custom mapping
        $post_id = $this->factory()->post->create([
            'post_title' => 'Mock Post Title',
            'post_type' => 'event'
        ]);
        $EventPost = Timber::get_post($post_id);
        $this->assertNotInstanceOf(Post::class, $EventPost);
    }
}
