<?php

namespace Sitchco\Tests\Model;

use Sitchco\Tests\Support\PostTester;
use Sitchco\Tests\Support\TestCase;
use Timber\Timber;

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

    public function test_get_method_override()
    {
        $post_id = $this->factory()->post->create([
            'post_title' => 'Post',
        ]);
        $post = Timber::get_post($post_id);
        $this->assertInstanceOf(PostTester::class, $post);
        $this->assertEquals("Custom Getter: Test Custom Value", $post->test_custom_value);
    }

}