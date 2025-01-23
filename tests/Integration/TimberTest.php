<?php

namespace Test\Timber;

use DI\DependencyException;
use DI\NotFoundException;
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
        $post = Timber::get_post($post_id);

        $this->assertInstanceOf(\Timber\Post::class, $post);
        $this->assertEquals('Mock Post Title', $post->post_title);
        $this->assertEquals($post_id, $post->ID);
    }
}
