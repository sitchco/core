<?php


use Timber\Timber;
use WPTest\Test\TestCase;

/**
 * class PostBaseTest
 * @package Sitchco\Tests
 */
class PostBaseTest extends TestCase
{
    public function test_custom_get_method()
    {
        $post_id = $this->factory()->post->create([
            'post_title' => 'Post',
        ]);
        $Post = Timber::get_post($post_id);
        $this->assertEquals("Custom Getter: Test Custom Value", $Post->test_custom_value);
    }
}