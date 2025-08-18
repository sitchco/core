<?php

namespace Sitchco\Tests;

use Sitchco\Collection;
use Timber\PostQuery;
use WP_Query;

/**
 * class CollectionTest
 * @package Sitchco\Tests\Collection
 */
class CollectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Use post factory to create sample posts
        $this->factory()->post->create_many(3);
    }

    public function testCollectionWrapsPostQueryCorrectly()
    {
        $wp_query = new WP_Query([
            'post_type' => 'post',
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
        ]);

        $post_query = new PostQuery($wp_query);
        $collection = new Collection($post_query);

        // Ensure Collection wraps the PostQuery properly
        $this->assertEquals($post_query->to_array(), $collection->to_array());
        $this->assertEquals($post_query->jsonSerialize(), $collection->jsonSerialize());
        $this->assertEquals($post_query->query(), $collection->query());
        $this->assertEquals($post_query->__debugInfo(), $collection->__debugInfo());
    }

    public function testCollectionPaginationMatchesPostQuery()
    {
        $wp_query = new WP_Query([
            'post_type' => 'post',
            'posts_per_page' => 1, // Force pagination
            'paged' => 1,
        ]);

        $post_query = new PostQuery($wp_query);
        $collection = new Collection($post_query);

        $this->assertEquals($post_query->pagination(), $collection->pagination());
    }

    public function testCollectionRealizeMatchesPostQuery()
    {
        $wp_query = new WP_Query([
            'post_type' => 'post',
            'posts_per_page' => -1,
        ]);

        $post_query = new PostQuery($wp_query);
        $collection = new Collection($post_query);

        $this->assertSame($post_query->realize(), $collection->realize());
    }
}
