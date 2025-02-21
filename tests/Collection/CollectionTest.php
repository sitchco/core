<?php

namespace Sitchco\Tests\Collection;

use PHPUnit\Framework\TestCase;
use Timber\PostQuery;
use Sitchco\Collection\Collection;
use WP_Query;

/**
 * class CollectionTest
 * @package Sitchco\Tests\Collection
 */
class CollectionTest extends TestCase
{
    protected array $post_ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Create sample posts in the database
        $this->post_ids = [];
        for ($i = 0; $i < 3; $i++) {
            $this->post_ids[] = wp_insert_post([
                'post_title'  => "Test Post {$i}",
                'post_status' => 'publish',
                'post_type'   => 'post',
            ]);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->post_ids as $post_id) {
            wp_delete_post($post_id, true);
        }
        parent::tearDown();
    }

    public function testCollectionWrapsPostQueryCorrectly()
    {
        $wp_query = new WP_Query([
            'post_type'      => 'post',
            'posts_per_page' => -1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
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
            'post_type'      => 'post',
            'posts_per_page' => 1, // Force pagination
            'paged'          => 1,
        ]);

        $post_query = new PostQuery($wp_query);
        $collection = new Collection($post_query);

        $this->assertEquals($post_query->pagination(), $collection->pagination());
    }

    public function testCollectionRealizeMatchesPostQuery()
    {
        $wp_query = new WP_Query([
            'post_type'      => 'post',
            'posts_per_page' => -1,
        ]);

        $post_query = new PostQuery($wp_query);
        $collection = new Collection($post_query);

        $this->assertSame($post_query->realize(), $collection->realize());
    }
}
